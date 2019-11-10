<?php

namespace Violinist\ComposerUpdater;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
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
     * @var bool
     */
    protected $devPackage = false;

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

    /**
     * @var \stdClass
     */
    protected $postUpdateData;

    /**
     * @var string
     */
    protected $constraint;

    public function __construct($cwd, $package)
    {
        $this->cwd = $cwd;
        $this->package = $package;
    }

    /**
     * @return string
     */
    public function getConstraint()
    {
        return $this->constraint;
    }

    /**
     * @param string $constraint
     */
    public function setConstraint($constraint)
    {
        $this->constraint = $constraint;
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

    /**
     * @return bool
     */
    public function isWithUpdate()
    {
        return $this->withUpdate;
    }

    /**
     * @param bool $withUpdate
     */
    public function setWithUpdate($withUpdate)
    {
        $this->withUpdate = $withUpdate;
    }

    /**
     * @return bool
     */
    public function isDevPackage()
    {
        return $this->devPackage;
    }

    /**
     * @param bool $devPackage
     */
    public function setDevPackage($devPackage)
    {
        $this->devPackage = $devPackage;
    }

    public function executeRequire($new_version)
    {
        $pre_update_lock = ComposerLockData::createFromFile($this->cwd . '/composer.lock');
        $pre_update_data = $pre_update_lock->getPackageData($this->package);
        $commands = $this->getRequireRecipes($new_version);
        $exception = null;
        $success = false;
        foreach ($commands as $command) {
            if ($success) {
                continue;
            }
            try {
                $full_command = sprintf('%s %s', $command, $this->isWithUpdate() ? '--update-with-dependencies' : '');
                $process = $this->getProcessFactory()->getProcess($full_command, $this->cwd, $this->getEnv(), null, $this->timeout);
                $process->run();
                $this->handlePostComposerCommand($pre_update_data, $process);
                $success = true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        if (!$success) {
            // Re-throw the last exception.
            throw $e;
        }
    }


    /**
     * @throws \Throwable
     * @throws ComposerUpdateProcessFailedException
     * @throws NotUpdatedException
     */
    public function executeUpdate()
    {
        $pre_update_lock = ComposerLockData::createFromFile($this->cwd . '/composer.lock');
        $pre_update_data = $pre_update_lock->getPackageData($this->package);
        $commands = $this->getUpdateRecipies($this->package);
        $exception = null;
        $success = false;
        foreach ($commands as $command) {
            try {
                $full_command = sprintf('%s %s', $command, $this->isWithUpdate() ? '--with-dependencies' : '');
                $this->log("Creating command $full_command", [
                    'command' => $full_command,
                ]);
                $process = $this->getProcessFactory()->getProcess($full_command, $this->cwd, $this->getEnv(), null, $this->timeout);
                $process->run();
                if ($process->getExitCode()) {
                    $exception = new ComposerUpdateProcessFailedException('Composer update exited with exit code ' . $process->getExitCode());
                    $exception->setErrorOutput($process->getErrorOutput());
                    throw $exception;
                }
                $this->handlePostComposerCommand($pre_update_data, $process);
                $success = true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        if (!$success) {
            throw $e;
        }
    }

    protected function handlePostComposerCommand($pre_update_data, Process $process)
    {
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
        $version_from = $pre_update_data->version;
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
        $this->postUpdateData = $post_update_data;
    }

    /**
     * @return \stdClass
     */
    public function getPostUpdateData()
    {
        return $this->postUpdateData;
    }

    protected function log($message, $context = [])
    {
        $this->getLogger()->log('info', $message, $context);
    }

    protected function getRequireRecipes($version)
    {
        return [
            sprintf('composer %s -n --no-ansi %s:%s%s', $this->isDevPackage() ? 'require --dev' : 'require', $this->package, $this->constraint, $version)
        ];
    }

    protected function getUpdateRecipies()
    {
        $map = [
            'drupal/core' => [
                'composer update -n --no-ansi drupal/core webflo/drupal-core-require-dev symfony/*'
            ],
            'drupal/dropzonejs' => [
                'composer update -n --no-ansi drupal/dropzonejs drupal/dropzonejs_eb_widget'
            ]
        ];
        $return = [
            'composer update -n --no-ansi ' .  $this->package
        ];
        if (isset($map[$this->package])) {
            $return = array_merge($return, $map[$this->package]);
        }
        return $return;
    }

    protected function getEnv()
    {
        return [
            // Need the path to composer.
            'PATH' => getenv('PATH'),
            // And we need a "HOME" environment.
            'HOME' => getenv('HOME'),
            'COMPOSER_ALLOW_SUPERUSER' => 1,
            'COMPOSER_DISCARD_CHANGES' => 'true',
        ];
    }
}
