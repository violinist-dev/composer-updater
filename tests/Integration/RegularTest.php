<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\ComposerUpdater\Updater;

class RegularTest extends TestCase
{
    public function testRegularEndToEnd()
    {
        // Run the updater on a version 1.0.0 of psr/log.
        $package = 'psr/log';
        $directory = __DIR__ . '/../assets/psr-log';
        $file = "$directory/composer.lock";
        $lock_string = @file_get_contents($file);
        $pre_lock_data = ComposerLockData::createFromString($lock_string);
        $updater = new Updater($directory, $package);
        $updater->executeUpdate();
        // Now read the lock data of it.
        $post_lock_data = ComposerLockData::createFromFile($file);
        $log_package_data_pre = $pre_lock_data->getPackageData($package);
        $log_package_data_post = $post_lock_data->getPackageData($package);
        $this->assertNotEquals($log_package_data_post->version, $log_package_data_pre->version);
        // Now make sure we reset it after us.
        @file_put_contents($file, $lock_string);
    }
}
