<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\ComposerUpdater\Updater;

abstract class IntegrationBase extends TestCase
{
    protected $package;

    protected $directory;

    public function testEndToEnd()
    {
        $directory = __DIR__ . '/../assets/' . $this->directory;
        $file = "$directory/composer.lock";
        $lock_string = @file_get_contents($file);
        $pre_lock_data = ComposerLockData::createFromString($lock_string);
        $updater = new Updater($directory, $this->package);
        try {
            $updater->executeUpdate();
        }
        catch (\Throwable $e) {
            if (method_exists($e, 'getErrorOutput')) {
                var_export($e->getErrorOutput());
            }
            throw $e;
        }
        // Now read the lock data of it.
        $post_lock_data = ComposerLockData::createFromFile($file);
        $log_package_data_pre = $pre_lock_data->getPackageData($this->package);
        $log_package_data_post = $post_lock_data->getPackageData($this->package);
        $this->assertNotEquals($log_package_data_post->version, $log_package_data_pre->version);
        // Now make sure we reset it after us.
        @file_put_contents($file, $lock_string);
    }
}
