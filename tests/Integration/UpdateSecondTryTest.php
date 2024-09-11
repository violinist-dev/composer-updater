<?php

namespace Integration;

use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\ComposerUpdater\Tests\Integration\IntegrationBase;

class UpdateSecondTryTest extends IntegrationBase
{
    protected $package = 'drupal/captcha';
    protected $directory = 'drupal-captcha';
    protected $newVersion = '2.0.5';

    public function testEndToEnd()
    {
        if (version_compare(phpversion(), "8.0.0", "<")) {
            return;
        }
        if (version_compare(phpversion(), "8.3.0", ">=")) {
            return;
        }
        parent::testEndToEnd();
    }

    protected function createUpdater($directory)
    {
        $updater = parent::createUpdater($directory);
        $updater->setPackagesToCheckHasUpdated(['doctrine/annotations', 'drupal/captcha']);
        return $updater;
    }
}
