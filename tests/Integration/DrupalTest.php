<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class DrupalTest extends IntegrationBase
{
    protected $package = 'drupal/core';
    protected $directory = 'drupal-core';

    public function testEndToEnd()
    {
        if (getenv('COMPOSER_VERSION') == 2) {
            self::assertTrue(true, 'Skipping test on composer version 2');
            return;
        }
        parent::testEndToEnd();
    }
}
