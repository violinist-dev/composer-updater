<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class SymfonyTest extends IntegrationBase
{
    protected $package = 'symfony/console';
    protected $directory = 'symfony';

    /**
     * {@inheritdoc}
     */
    public function testEndToEnd()
    {
        if (version_compare(phpversion(), "7.2.0", "<=")) {
            self::assertTrue(true, sprintf('Skipping integration test %s for version %s since PHP version is less than 7.2.0', get_class($this), phpversion()));
            return;
        }
        parent::testEndToEnd();
        // Now check that the other symfony packages were updated too.
        $packages = [
            'symfony/console',
            'symfony/dotenv',
            'symfony/framework-bundle',
            'symfony/runtime',
            'symfony/yaml',
        ];
        foreach ($packages as $package) {
            $log_package_data_pre = $this->preLockData->getPackageData($package);
            $log_package_data_post = $this->postLockData->getPackageData($package);
            self::assertNotEquals($log_package_data_post->version, $log_package_data_pre->version);
        }
    }

    protected function createUpdater($directory)
    {
        $updater = parent::createUpdater($directory);
        $updater->setBundledPackages(['symfony/*']);
        return $updater;
    }
}
