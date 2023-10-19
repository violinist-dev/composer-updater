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

    protected $jsonString;

    protected $preLockData;

    protected $postLockData;

    protected $useUpdate = true;

    protected $newVersion;

    public function tearDown() : void
    {
        parent::tearDown();
        $lock_file = $this->getLockFile();
        // Now make sure we reset it after us.
        @file_put_contents($lock_file, $this->lockString);
        $json_file = $this->getComposerJsonFile();
        @file_put_contents($json_file, $this->jsonString);
    }

    protected function getLockFile()
    {
        $directory = $this->getDirectory();
        return "$directory/composer.lock";
    }

    protected function getComposerJsonFile()
    {
        $directory = $this->getDirectory();
        return "$directory/composer.json";
    }

    protected function getDirectory()
    {
        return __DIR__ . '/../assets/' . $this->directory;
    }

    public function testEndToEnd()
    {
        $file = $this->getLockFile();
        $this->lockString = @file_get_contents($this->getLockFile());
        $this->jsonString = @file_get_contents($this->getComposerJsonFile());
        $this->preLockData = ComposerLockData::createFromString($this->lockString);
        $directory = $this->getDirectory();
        $updater = $this->createUpdater($directory);
        try {
            if ($this->useUpdate) {
                $updater->executeUpdate();
            } else {
                $updater->executeRequire($this->newVersion);
            }
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
