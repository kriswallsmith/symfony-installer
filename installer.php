<?php

function _exec()
{
  global $filesystem;
  $args = func_get_args();
  $command = array_shift($args);
  return $filesystem->execute(vsprintf($command, array_map('escapeshellarg', $args)));
}

global $filesystem;
$filesystem = $this->getFilesystem();

##############################################################################
# CONFIGURE                                                                  #
##############################################################################

$remove = array(
  sfConfig::get('sf_config_dir').'/databases.yml',
  sfConfig::get('sf_config_dir').'/rsync_exclude.txt',
  sfConfig::get('sf_data_dir').'/fixtures',
  sfConfig::get('sf_web_dir').'/.htaccess',
  sfConfig::get('sf_web_dir').'/css/main.css',
  sfConfig::get('sf_web_dir').'/uploads/assets',
);

$ignore = array(
  sfConfig::get('sf_cache_dir'),
  sfConfig::get('sf_log_dir'),
  sfConfig::get('sf_upload_dir'),
);

$plugins = array(
  'sfDoctrineGuardPlugin' => 'http://svn.symfony-project.com/plugins/sfDoctrineGuardPlugin/branches/1.3',
  'sfTaskExtraPlugin'     => 'http://svn.symfony-project.com/plugins/sfTaskExtraPlugin/branches/1.3',
);

##############################################################################
# EXECUTE                                                                    #
##############################################################################

// install files
$this->getFilesystem()->remove(sfConfig::get('sf_config_dir').'/ProjectConfiguration.class.php');
$this->installDir(dirname(__FILE__).'/project');

// javascript
$properties = parse_ini_file(sfConfig::get('sf_config_dir').'/properties.ini', true);
$this->getFilesystem()->rename(
  sfConfig::get('sf_web_dir').'/js/project',
  sfConfig::get('sf_web_dir').'/js/'.str_replace('.', '_', $properties['symfony']['name'])
);
$this->replaceTokens(sfConfig::get('sf_web_dir').'/js', array(
  'YEAR'    => date('Y'),
  'AUTHOR'  => isset($properties['symfony']['author']) ? $properties['symfony']['author'] : 'Your name here',
  'PROJECT' => sfInflector::camelize($properties['symfony']['name']),
));

// remove
array_map(array('sfToolkit', 'clearDirectory'), $remove);
$filesystem->remove($remove);

// svn
_exec('svn add *');

$this->logSection('file+', $tmp = sfConfig::get('sf_cache_dir').'/svnprop.tmp');
file_put_contents($tmp, '*');
foreach ($ignore as $directory)
{
  _exec('svn ps svn:ignore --file=%s %s', $tmp, $directory);
}
file_put_contents($tmp, '*.php');
_exec('svn ps svn:ignore --file=%s %s', $tmp, sfConfig::get('sf_web_dir'));
$filesystem->remove($tmp);

_exec('svn ps svn:ignore databases.yml %s', sfConfig::get('sf_config_dir'));

// plugins
if (count($plugins))
{
  $externals = '';
  foreach ($plugins as $name => $path)
  {
    _exec('svn co %s %s', $path, sfConfig::get('sf_plugins_dir').'/'.$name);
    $externals .= $name.' '.$path."\n";
  }
  _exec('svn ps svn:externals %s %s', trim($externals), sfConfig::get('sf_plugins_dir'));
  $filesystem->replaceTokens(sfConfig::get('sf_config_dir').'/ProjectConfiguration.class.php', '##', '##', array(
    'PLUGINS' => "      '".implode("',\n      '", array_keys($plugins))."',",
  ));
}
else
{
  $filesystem->replaceTokens(sfConfig::get('sf_config_dir').'/ProjectConfiguration.class.php', '##', '##', array('PLUGINS' => ''));
}

// copy core into project
if (sfConfig::get('sf_symfony_lib_dir') != sfConfig::get('sf_lib_dir').'/vendor/symfony')
{
  _exec('svn mkdir %s', sfConfig::get('sf_lib_dir').'/vendor');
  _exec('svn ps svn:externals %s %s', 'symfony http://svn.symfony-project.com/branches/1.4', sfConfig::get('sf_lib_dir').'/vendor');
  _exec('cp -R %s %s', sfConfig::get('sf_symfony_lib_dir').'/..', sfConfig::get('sf_lib_dir').'/vendor/symfony');
}
