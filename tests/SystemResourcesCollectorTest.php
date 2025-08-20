<?php
use PHPUnit\Framework\TestCase;
use PfSense\Monitoring\Monitor\SystemResourcesCollector;

final class SystemResourcesCollectorTest extends TestCase
{
    public function testCollectsSystemResourceMetrics(): void
    {
        $c = new SystemResourcesCollector();
        $r = $c->collect();
        $this->assertIsArray($r);
        $found = array_column($r, 'metric');
        $this->assertContains('system_uptime_seconds', $found);
    }
}
