<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use Violinist\ComposerUpdater\Exception\ComposerUpdateProcessFailedException;

class DrupalTest extends IntegrationBase
{
    protected $package = 'drupal/core';
    protected $directory = 'drupal-core';

    public function testEndToEnd()
    {
        if (getenv('COMPOSER_VERSION') == 2) {
            self::expectException(ComposerUpdateProcessFailedException::class);
        }
        if (version_compare(phpversion(), "8.0.0", ">=")) {
            self::expectException(ComposerUpdateProcessFailedException::class);
        }
        parent::testEndToEnd();
    }
}
