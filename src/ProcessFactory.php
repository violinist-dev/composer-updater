<?php

namespace Violinist\ComposerUpdater;

use Symfony\Component\Process\Process;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class ProcessFactory implements ProcessFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getProcess(array $command, ?string $cwd = null, ?array $env = null, $input = null, ?float $timeout = 60)
    {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}
