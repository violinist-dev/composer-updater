<?php

namespace Violinist\ComposerUpdater;

use Symfony\Component\Process\Process;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class ProcessFactory implements ProcessFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getProcess($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        return new Process($commandline, $cwd, $env, $input, $timeout);
    }
}
