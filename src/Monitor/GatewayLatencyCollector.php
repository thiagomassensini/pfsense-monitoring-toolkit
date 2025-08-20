<?php
namespace PfSense\Monitoring\Monitor;

use PfSense\Monitoring\Lib\Metric;
use PfSense\Monitoring\Lib\Exec;

final class GatewayLatencyCollector implements Metric
{
    public function collect(array $options = []): array
    {
        $gateway = $options['gateway'] ?? '8.8.8.8';
        $count   = (int)($options['count'] ?? 3);
        $size    = (int)($options['size'] ?? 56);

        $cmd = sprintf('ping -n -q -c %d -s %d %s', $count, $size, escapeshellarg($gateway));
        [$exit, $stdout, $stderr] = Exec::command($cmd);
        $ts = time();
        if ($exit !== 0) {
            return [[
                'metric' => 'gateway_latency_ms',
                'value' => 0,
                'timestamp' => $ts,
                'labels' => ['gateway' => $gateway],
                'error' => trim($stderr) ?: 'ping failed'
            ]];
        }
        // Extrai rtt min/avg/max/mdev
        $lat = 0.0;
        if (preg_match('/= ([0-9.]+)\/([0-9.]+)\/([0-9.]+)\//', $stdout, $m)) {
            $lat = (float)$m[2]; // média
        }
        return [[
            'metric' => 'gateway_latency_ms',
            'value' => $lat,
            'timestamp' => $ts,
            'labels' => ['gateway' => $gateway]
        ]];
    }
}
