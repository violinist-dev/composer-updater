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
     * @var ProcessFactoryInterface|null
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
     * @var LoggerInterface|null
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
     * @var array
     */
    protected $packagesToCheck = [];

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
     * @deprecated This method should not be used, and instead we should use the
     * one that accepts an array of packages. @see ::setPackagesToCheckHasUpdated
     */
    public function setPackageToCheckHasUpdated($package)
    {
        $this->setPackagesToCheckHasUpdated([$package]);
    }

    public function setPackagesToCheckHasUpdated(array $packages)
    {
        $this->packagesToCheck = $packages;
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
            $this->bundledPackages = array_filter($bundledPackages, function ($package) use ($lock) {
                try {
                    return $lock->getPackageData($package);
                } catch (\Throwable $e) {
                    // Probably means the package is not there.
                    // Let's also see if we can find a match by wildcard.
                    $lock_data = $lock->getData();
                    foreach (['packages', 'packages-dev'] as $type) {
                        if (empty($lock_data->{$type})) {
                            continue;
                        }
                        foreach ($lock_data->{$type} as $package_data) {
                            if (empty($package_data->name)) {
                                continue;
                            }
                            if (fnmatch($package, $package_data->name)) {
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
        $this->setPackagesToCheckHasUpdated([$package]);
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
        if (!$this->processFactory instanceof ProcessFactoryInterface) {
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
        if (!$this->logger instanceof LoggerInterface) {
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

    protected function getPreUpdateData() : array
    {
        $pre_update_lock = ComposerLockData::createFromFile($this->cwd . '/composer.lock');
        return array_map(function($package) use ($pre_update_lock) {
            return $pre_update_lock->getPackageData($package);
        }, $this->packagesToCheck);
    }

    public function executeRequire($new_version)
    {
        $pre_update_data = $this->getPreUpdateData();
        $commands = $this->getRequireRecipes($new_version);
        $exception = null;
        $success = false;
        $e = null;
        foreach ($commands as $command) {
            if ($success) {
                continue;
            }
            try {
                $full_command = array_merge(
                    $command,
                    array_filter([
                        ($this->isWithUpdate() ? '--update-with-dependencies' : ''),
                        (!$this->shouldRunScripts() ? '--no-scripts' : ''),
                    ])
                );
                $log_command = implode(' ', $full_command);
                $this->log("Creating command $log_command", [
                    'command' => $log_command,
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
        $pre_update_data = $this->getPreUpdateData();
        $commands = $this->getUpdateRecipies();
        $exception = null;
        $success = false;
        $e = null;
        foreach ($commands as $command) {
            try {
                $full_command = array_merge(
                    $command,
                    array_filter([
                        ($this->isWithUpdate() ? '--with-dependencies' : ''),
                        ($this->shouldRunScripts() ? '' : '--no-scripts'),
                    ])
                );
                $log_command = implode(' ', $full_command);
                $this->log("Creating command $log_command", [
                    'command' => $log_command,
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

    protected function handlePostComposerCommand(array $pre_update_data_array, Process $process)
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
        $has_updated_at_least_one_package = false;
        foreach ($this->packagesToCheck as $package) {
            $pre_update_data = $this->getPreUpdataDataForPackageFromArray($pre_update_data_array, $package);
            $post_update_data = ComposerLockData::createFromString(json_encode($new_lock_data))->getPackageData($package);
            $version_to = $post_update_data->version;
            $version_from = $pre_update_data->version;
            if (isset($post_update_data->source) && $post_update_data->source->type == 'git' && isset($pre_update_data->source)) {
                $version_from = $pre_update_data->source->reference;
                $version_to = $post_update_data->source->reference;
            }
            if ($version_from === $version_to) {
                // In theory though, the reference sources can be the same (the same commit), but the
                // version is different. In which case it does not really matter much to update, but it
                // can be frustrating to get an error. So let's not give an error.
                if ($post_update_data->version === $pre_update_data->version) {
                    $this->log($process->getErrorOutput(), [
                        'package' => $this->package,
                    ]);
                } else {
                    $has_updated_at_least_one_package = true;
                }
            } else {
                $has_updated_at_least_one_package = true;
            }
        }
        if ($this->shouldThrowOnUnupdated && !$has_updated_at_least_one_package) {
            // Nothing has happened here. Although that can be alright (like we
            // have updated some dependencies of this package), we have at least
            // not updated any of the expected dependencies at this point.
            throw new NotUpdatedException('The version installed is still the same after trying to update.');
        }
        // We still want the post update data to be from the actual package though, no matter if we were actually
        // checking if a dependency was updated or not.
        $actual_package_post_update_data = ComposerLockData::createFromString(json_encode($new_lock_data))->getPackageData($this->package);
        $this->postUpdateData = $actual_package_post_update_data;
        // And make sure we log this as well.
        $this->log($process->getOutput());
        $this->log($process->getErrorOutput());
    }

    protected function getPreUpdataDataForPackageFromArray(array $pre_update_data_array, $package) : ?\stdClass
    {
        foreach ($pre_update_data_array as $item) {
            if ($item->name === $package) {
                return $item;
            }
        }
        return null;
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
            array_filter([
                'composer',
                'require',
                $this->isDevPackage() ? '--dev' : '',
                '-n',
                '--no-ansi',
                sprintf('%s:%s%s', $this->package, $this->constraint, $version),
            ]),
        ];
    }

    protected function getUpdateRecipies()
    {
        $map = [
            'drupal/core' => [
                ['composer', 'update', 'drupal/core', 'drupal/core-*', '--with-all-dependencies'],
                ['composer', 'update', '-n', '--no-ansi', 'drupal/core', 'webflo/drupal-core-require-dev', 'symfony/*'],
            ],
            'drupal/core-recommended' => [
                ['composer', 'update', 'drupal/core', 'drupal/core-*', '--with-all-dependencies'],
            ],
            'drupal/dropzonejs' => [
                ['composer', 'update', '-n', '--no-ansi', 'drupal/dropzonejs', 'drupal/dropzonejs_eb_widget'],
            ],
            'drupal/commerce' => [
                ['composer', 'update', '-n', '--no-ansi', 'drupal/commerce', 'drupal/commerce_price', 'drupal/commerce_product', 'drupal/commerce_order', 'drupal/commerce_payment', 'drupal/commerce_payment_example', 'drupal/commerce_checkout', 'drupal/commerce_tax', 'drupal/commerce_cart', 'drupal/commerce_log', 'drupal/commerce_store', 'drupal/commerce_promotion', 'drupal/commerce_number_pattern'],
            ],
            'drupal/league_oauth_login' => [
                ['composer', 'update', '-n', '--no-ansi', 'drupal/league_oauth_login', 'drupal/league_oauth_login_github', 'drupal/league_oauth_login_gitlab'],
            ],
        ];
        $return = [
            ['composer', 'update', '-n', '--no-ansi', $this->package],
        ];
        if ($this->hasBundledPackages()) {
            $return = [
                array_merge(['composer', 'update', '-n', '--no-ansi', $this->package], $this->getBundledPackages()),
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
