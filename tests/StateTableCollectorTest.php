<?php
use PHPUnit\Framework\TestCase;
use PfSense\Monitoring\Monitor\StateTableCollector;

final class StateTableCollectorTest extends TestCase
{
    public function testStateCollectorReturnsArray(): void
    {
        $c = new StateTableCollector();
        $r = $c->collect(['timeout'=>1]);
        $this->assertIsArray($r);
        $this->assertArrayHasKey('metric', $r[0]);
    }
}
