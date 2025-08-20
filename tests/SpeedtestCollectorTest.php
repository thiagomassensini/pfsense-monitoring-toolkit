<?php
use PHPUnit\Framework\TestCase;
use PfSense\Monitoring\Monitor\SpeedtestCollector;

final class SpeedtestCollectorTest extends TestCase
{
    public function testSpeedtestCollectorHandlesMissingBinary(): void
    {
        $c = new SpeedtestCollector();
        $r = $c->collect(['ttl'=>1,'timeout'=>1,'cache_dir'=>__DIR__.'/../cache_test']);
        $this->assertIsArray($r);
        $this->assertArrayHasKey('metric', $r[0]);
    }
}
