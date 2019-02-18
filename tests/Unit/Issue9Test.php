<?php

namespace Violinist\ComposerUpdater\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\ComposerUpdater\Updater;

class UnitTest extends TestCase
{
    public function testRegularUpdateNoComposerLock()
    {
        $updater = new Updater(__DIR__ . '/../assets/unit/issue9', 'harvesthq/chosen');
        $this->expectException(NotUpdatedException::class);
        $updater->executeUpdate();
    }
}
