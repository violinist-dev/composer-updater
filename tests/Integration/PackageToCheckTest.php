<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use Violinist\ComposerUpdater\Updater;

class PackageToCheckTest extends IntegrationBase
{
    protected $package = 'drupal/captcha';
    protected $directory = 'drupal-captcha';

    protected function createUpdater($directory)
    {
        $updater = new Updater($directory, 'drupal/recaptcha');
        $updater->setPackageToCheckHasUpdated($this->package);
        return $updater;
    }
}
