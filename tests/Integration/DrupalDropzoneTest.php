<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class DrupalDropzoneTest extends IntegrationBase
{
    protected $package = 'drupal/dropzonejs';
    protected $directory = 'drupal-dropzonejs';

    public function testEndToEnd() 
    {
        if (getenv('COMPOSER_VERSION') == 2) {
            self::assertTrue(true, 'Skipping test on composer version 2');
            return;
        }
        parent::testEndToEnd();
    }
}
