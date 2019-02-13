<?php

namespace Violinist\ComposerUpdater;

use Psr\Log\LoggerInterface;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\ComposerUpdater\Exception\ComposerUpdateProcessFailedException;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class Updater
{

    /**
     * @var int
     */
    protected $timeout = 600;

    /**
     * @var bool
     */
    protected $withUpdate = true;

    /**
     * @var ProcessFactoryInterface
     */
    protected $processFactory;

    /**
     * @var string
     */
    protected $package;

    /**
     * @var string
     */
    protected $cwd;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct($cwd, $package)
    {
        $this->cwd = $cwd;
        $this->package = $package;
    }

    /**
     * @return ProcessFactoryInterface
     */
    public function getProcessFactory()
    {
        if (!$this->processFactory) {
            $this->processFactory = new ProcessFactory();
        }
        return $this->processFactory;
    }

    /**
     * @param ProcessFactoryInterface $processFactory
     */
    public function setProcessFactory(ProcessFactoryInterface $processFactory)
    {
        $this->processFactory = $processFactory;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new DefaultLogger();
        }
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    public function executeUpdate()
    {
        $pre_update_lock = ComposerLockData::createFromFile($this->cwd . '/composer.lock');
        $pre_update_data = $pre_update_lock->getPackageData($this->package);
        $commands = $this->getRecipies($this->package);
        foreach ($commands as $command) {
            try {
                $full_command = sprintf('%s %s', $command, $this->withUpdate ? '--with-dependencies' : '');
                $process = $this->getProcessFactory()->getProcess($full_command, $this->cwd, $this->getEnv(), null, $this->timeout);
                $process->run();
                if ($process->getExitCode()) {
                    $this->log('Problem running composer update:');
                    $this->log($process->getErrorOutput());
                    throw new ComposerUpdateProcessFailedException('Composer update exited with exit code ' . $process->getExitCode());
                }
                $new_lock_data = @json_decode(@file_get_contents(sprintf('%s/composer.lock', $this->cwd)));
                if (!$new_lock_data) {
                    $message = sprintf('No composer.lock found after updating %s', $this->package);
                    $this->log($message);
                    $this->log('This is the stdout:');
                    $this->log($process->getOutput());
                    $this->log('This is the stderr:');
                    $this->log($process->getErrorOutput());
                    throw new \Exception($message);
                }
                $post_update_data = ComposerLockData::createFromString(json_encode($new_lock_data))->getPackageData($this->package);
                $version_to = $post_update_data->version;
                if (isset($post_update_data->source) && $post_update_data->source->type == 'git' && isset($pre_update_data->source)) {
                    $version_from = $pre_update_data->source->reference;
                    $version_to = $post_update_data->source->reference;
                }
                if ($version_to === $version_from) {
                    // Nothing has happened here. Although that can be alright (like we
                    // have updated some dependencies of this package) this is not what
                    // this service does, currently, and also the title of the PR would be
                    // wrong.
                    $this->log($process->getErrorOutput(), [
                        'package' => $this->package,
                    ]);
                    throw new NotUpdatedException('The version installed is still the same after trying to update.');
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    protected function log($message, $context = [])
    {
        $this->getLogger()->log('info', $message, $context);
    }

    protected function getRecipies()
    {
        return [
            'composer update -n --no-ansi ' .  $this->package
        ];
    }

    protected function getEnv()
    {
        return [
            'COMPOSER_ALLOW_SUPERUSER' => 1,
            'COMPOSER_DISCARD_CHANGES' => 'true',
        ];
    }
}
