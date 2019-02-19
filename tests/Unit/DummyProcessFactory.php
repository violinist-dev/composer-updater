<?php

namespace Violinist\ComposerUpdater\Tests\Unit;

use Symfony\Component\Process\Process;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class DummyProcessFactory implements ProcessFactoryInterface
{

    /**
     * @var Process
     */
    protected $process;

    /**
     * Get a process instance.
     *
     * The function signature is the same as the symfony process command.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function getProcess(
        $commandline,
        $cwd = null,
        array $env = null,
        $input = null,
        $timeout = 60,
        array $options = null
    ) {
        return $this->process;
    }

    public function setProcess(Process $process)
    {
        $this->process = $process;
    }
}
