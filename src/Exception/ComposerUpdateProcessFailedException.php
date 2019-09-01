<?php

namespace Violinist\ComposerUpdater\Exception;

class ComposerUpdateProcessFailedException extends \Exception
{
    protected $errorOutput;

    public function setErrorOutput($output)
    {
        $this->errorOutput = $output;
    }

    public function getErrorOutput()
    {
        return $this->errorOutput;
    }
}
