<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\ComposerUpdater\Exception\ComposerUpdateProcessFailedException;
use Violinist\ComposerUpdater\Updater;

abstract class IntegrationBase extends TestCase
{
    protected $package;

    protected $directory;

    protected $lockString;

    protected $preLockData;

    protected $postLockData;

    public function tearDown()
    {
        parent::tearDown();
        $file = $this->getFile();
        // Now make sure we reset it after us.
        @file_put_contents($file, $this->lockString);
    }

    protected function getFile()
    {
        $directory = $this->getDirectory();
        return "$directory/composer.lock";
    }

    protected function getDirectory()
    {
        return __DIR__ . '/../assets/' . $this->directory;
    }

    public function testEndToEnd()
    {
        $file = $this->getFile();
        $this->lockString = @file_get_contents($file);
        $this->preLockData = ComposerLockData::createFromString($this->lockString);
        $directory = $this->getDirectory();
        $updater = $this->createUpdater($directory);
        try {
            $updater->executeUpdate();
        } catch (\Throwable $e) {
            if (method_exists($e, 'getErrorOutput')) {
                /** @var ComposerUpdateProcessFailedException $ex */
                $ex = $e;
                var_export($ex->getErrorOutput());
            }
            throw $e;
        }
        // Now read the lock data of it.
        $this->postLockData = ComposerLockData::createFromFile($file);
        $log_package_data_pre = $this->preLockData->getPackageData($this->package);
        $log_package_data_post = $this->postLockData->getPackageData($this->package);
        $this->assertNotEquals($log_package_data_post->version, $log_package_data_pre->version);
    }

    protected function createUpdater($directory)
    {
        $updater = new Updater($directory, $this->package);
        return $updater;
    }
}
