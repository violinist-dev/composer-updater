<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class RequireTest extends IntegrationBase
{
    protected $package = 'drupal/captcha';
    protected $directory = 'drupal-captcha';
    protected $useUpdate = false;
    protected $newVersion = '2.0.5';
}