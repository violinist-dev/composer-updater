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
        if (version_compare(phpversion(), "8.0.0", ">=")) {
            self::assertTrue(true, sprintf('Skipping integration test %s for version %s since PHP version is more than 8.0.0', get_class($this), phpversion()));
            return;
        }
        parent::testEndToEnd();
    }
}
