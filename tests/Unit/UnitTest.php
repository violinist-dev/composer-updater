<?php

namespace Violinist\ComposerUpdater\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Violinist\ComposerUpdater\Updater;

class UnitTest extends TestCase
{
    public function testRegularUpdateNoComposerLock()
    {
        $updater = new Updater('/tmp/bogus' . uniqid(), 'bogus/package');
        $this->expectException(\InvalidArgumentException::class);
        $updater->executeUpdate();
    }
}
