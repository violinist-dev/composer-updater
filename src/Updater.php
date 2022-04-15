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
     * @var bool
     */
    protected $runScripts = true;

    /**
     * @var int
     */
    protected $timeout = 1200;

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

    /**
     * Bundled packages.
     *
     * @var array
     */
    protected $bundledPackages;

    /**
     * @var bool
     */
    protected $shouldThrowOnUnupdated = true;

    /**
     * @return bool
     */
    public function shouldThrowOnUnupdated(): bool
    {
        return $this->shouldThrowOnUnupdated;
    }

    /**
     * @param bool $shouldThrowOnUnupdated
     */
    public function setShouldThrowOnUnupdated(bool $shouldThrowOnUnupdated)
    {
        $this->shouldThrowOnUnupdated = $shouldThrowOnUnupdated;
    }



    /**
     * @return bool
     */
    public function shouldRunScripts(): bool
    {
        return $this->runScripts;
    }

    /**
     * @param bool $runScripts
     */
    public function setRunScripts(bool $runScripts)
    {
        $this->runScripts = $runScripts;
    }

    /**
     * @return array
     */
    public function getBundledPackages()
    {
        if (empty($this->bundledPackages)) {
            return [];
        }
        return $this->bundledPackages;
    }

    public function hasBundledPackages()
    {
        return (bool) count($this->getBundledPackages());
    }

    /**
     * @param array $bundledPackages
     */
    public function setBundledPackages($bundledPackages)
    {
        $this->bundledPackages = [];
        // Now filter them. There should only be bundled packages that we can also find in composer.lock
        try {
            $lock = ComposerLockData::createFromFile($this->cwd . '/composer.lock');
            $lock_data = $lock-getData();
            $this->bundledPackages = array_filter($bundledPackages, function ($package) use ($lock) {
                try {
                    return $lock->getPackageData($package);
                } catch (\Throwable $e) {
                    // Probably means the package is not there.
                    // Let's also see if we can find a match by wildcard.
                    foreach (['packages', 'packages-dev'] as $type) {
                        if (empty ($lock_data->{$type})) {
                            continue;
                        }
                        foreach ($lock_data->{$type} as $package_data) {
                            if (empty ($package_data->name)) {
                                continue;
                            }
                            if (fnmatch ($package, $package_data->name)) {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            });
        } catch (\Throwable $e) {
            // So no bundled packages it is. Also. This probably means no updates, but that will be an exception for
            // another method.
        }
    }

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
        $e = null;
        foreach ($commands as $command) {
            if ($success) {
                continue;
            }
            try {
                $full_command = sprintf(
                    '%s %s %s',
                    $command,
                    ($this->isWithUpdate() ? '--update-with-dependencies' : ''),
                    (!$this->shouldRunScripts() ? '--no-scripts' : '')
                );
                $this->log("Creating command $full_command", [
                    'command' => $full_command,
                ]);
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
            if ($e) {
                throw $e;
            }
            throw new \Exception('The result was not successful, but we are not sure what failed');
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
        $commands = $this->getUpdateRecipies();
        $exception = null;
        $success = false;
        $e = null;
        foreach ($commands as $command) {
            try {
                $full_command = sprintf(
                    '%s %s %s',
                    $command,
                    ($this->isWithUpdate() ? '--with-dependencies' : ''),
                    ($this->shouldRunScripts() ? '' : '--no-scripts')
                );
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
            // Re-throw the last exception.
            if ($e) {
                throw $e;
            }
            throw new \Exception('The result was not successful, but we are not sure what failed');
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
        if ($this->shouldThrowOnUnupdated && $version_to === $version_from) {
            // Nothing has happened here. Although that can be alright (like we
            // have updated some dependencies of this package) this is not what
            // this service does, currently, and also the title of the PR would be
            // wrong.
            // In theory though, the reference sources can be the same (the same commit), but the
            // version is different. In which case it does not really matter much to update, but it
            // can be frustrating to get an error. So let's not give an error.
            if ($post_update_data->version === $pre_update_data->version) {
                $this->log($process->getErrorOutput(), [
                    'package' => $this->package,
                ]);
                throw new NotUpdatedException('The version installed is still the same after trying to update.');
            }
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
                'composer update drupal/core "drupal/core-*" --with-all-dependencies',
                'composer update -n --no-ansi drupal/core webflo/drupal-core-require-dev symfony/*',
            ],
            'drupal/core-recommended' => [
                'composer update drupal/core "drupal/core-*" --with-all-dependencies',
            ],
            'drupal/dropzonejs' => [
                'composer update -n --no-ansi drupal/dropzonejs drupal/dropzonejs_eb_widget'
            ],
            'drupal/commerce' => [
                'composer update -n --no-ansi drupal/commerce drupal/commerce_price drupal/commerce_product drupal/commerce_order drupal/commerce_payment drupal/commerce_payment_example drupal/commerce_checkout drupal/commerce_tax drupal/commerce_cart drupal/commerce_log drupal/commerce_store drupal/commerce_promotion drupal/commerce_number_pattern'
            ],
            'drupal/league_oauth_login' => [
                'composer update -n --no-ansi drupal/league_oauth_login drupal/league_oauth_login_github drupal/league_oauth_login_gitlab'
            ]
        ];
        $return = [
            'composer update -n --no-ansi ' .  $this->package
        ];
        if ($this->hasBundledPackages()) {
            $return = [
                sprintf('composer update -n --no-ansi %s %s', $this->package, implode(' ', $this->getBundledPackages())),
            ];
        }
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
            'COMPOSER_NO_INTERACTION' => 1,
        ];
    }
}
