<?php

namespace Violinist\ComposerUpdater\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\ComposerUpdater\Updater;

class UpdateWithAllowedExceptionTest extends TestCase
{
    /**
     * @dataProvider getAllowExceptions
     */
    public function testShouldThrow($should)
    {
        $mock_process = $this->createMock(Process::class);
        $factory = new DummyProcessFactory();
        $factory->setProcess($mock_process);
        $updater = new Updater(__DIR__ . '/../assets/unit/allowed-exception', 'psr/log');
        $updater->setProcessFactory($factory);
        $updater->setShouldThrowOnUnupdated($should);
        if ($should) {
            $this->expectException(NotUpdatedException::class);
        }
        $updater->executeUpdate();
        $data = $updater->getPostUpdateData();
        self::assertEquals("1.0.0", $data->version);
    }

    public function getAllowExceptions()
    {
        return [
            [true],
            [false],
        ];
    }
}
