<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use Violinist\ComposerUpdater\Exception\ComposerUpdateProcessFailedException;

class DrupalDropzoneTest extends IntegrationBase
{
    protected $package = 'drupal/dropzonejs';
    protected $directory = 'drupal-dropzonejs';

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
