<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use Violinist\ComposerUpdater\Updater;
use Violinist\ComposerUpdater\Exception\ComposerUpdateProcessFailedException;

class PackageToCheckTest extends IntegrationBase
{
    protected $package = 'drupal/captcha';
    protected $directory = 'drupal-captcha';

    protected function createUpdater($directory)
    {
        if (version_compare(phpversion(), "7.3.0", "<")) {
            self::expectException(ComposerUpdateProcessFailedException::class);
        }
        if (version_compare(phpversion(), "8.4.0", ">=")) {
            self::expectException(ComposerUpdateProcessFailedException::class);
        }
        $updater = new Updater($directory, 'drupal/recaptcha');
        $updater->setPackageToCheckHasUpdated($this->package);
        return $updater;
    }
}
