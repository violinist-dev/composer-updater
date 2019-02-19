<?php

namespace Violinist\ComposerUpdater\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\ComposerUpdater\Updater;

class UnitTest extends TestCase
{
    public function testRegularUpdateNoComposerLock()
    {
        $mock_process = $this->createMock(Process::class);
        $factory = new DummyProcessFactory();
        $factory->setProcess($mock_process);
        $updater = new Updater(__DIR__ . '/../assets/unit/issue9', 'harvesthq/chosen');
        $updater->setProcessFactory($factory);
        $this->expectException(NotUpdatedException::class);
        $updater->executeUpdate();
    }
}
