<?php

class BaseApplicationConfiguration extends sfApplicationConfiguration
{
  /**
   * @see sfApplicationConfiguration
   */
  public function initConfiguration()
  {
    parent::initConfiguration();

    // speed up the dev environment
    //sfAutoloadAgain::getInstance()->unregister();
  }
}
