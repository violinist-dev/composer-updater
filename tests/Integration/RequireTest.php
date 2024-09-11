<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use Violinist\ComposerUpdater\Exception\NotUpdatedException;

class RequireTest extends IntegrationBase
{
    protected $package = 'drupal/captcha';
    protected $directory = 'drupal-captcha';
    protected $useUpdate = false;
    protected $newVersion = '2.0.5';

    public function testEndToEnd()
    {
        if (version_compare(phpversion(), "8.0.0", "<")) {
            $this->expectException(NotUpdatedException::class);
        }
        if (version_compare(phpversion(), "8.3.0", ">=")) {
            self::expectException(NotUpdatedException::class);
        }
        parent::testEndToEnd();
    }
}
