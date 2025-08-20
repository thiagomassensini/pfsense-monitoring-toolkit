<?php
use PHPUnit\Framework\TestCase;
use PfSense\Monitoring\Monitor\GatewayLatencyCollector;

final class GatewayLatencyCollectorTest extends TestCase
{
    public function testReturnsMetricStructureEvenOnFailure(): void
    {
        $c = new GatewayLatencyCollector();
        $r = $c->collect(['gateway'=>'198.51.100.254','count'=>1]); // TEST-NET-2 IP (provável timeout)
        $this->assertIsArray($r);
        $this->assertArrayHasKey('metric', $r[0]);
        $this->assertSame('gateway_latency_ms', $r[0]['metric']);
        $this->assertArrayHasKey('timestamp', $r[0]);
        $this->assertArrayHasKey('labels', $r[0]);
    }
}
