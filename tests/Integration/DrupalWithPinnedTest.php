<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class DrupalWithPinnedTest extends IntegrationBase
{
    protected $package = 'drupal/core-recommended';
    protected $directory = 'drupal-core-with-pinned';

    /**
     * {@inheritdoc}
     */
    public function testEndToEnd()
    {
        if (version_compare(phpversion(), "7.1.0", "<=")) {
            self::assertTrue(true, sprintf('Skipping integration test %s for version %s since PHP version is less than 7.1.0', get_class($this), phpversion()));
            return;
        }
        parent::testEndToEnd();
    }
}
