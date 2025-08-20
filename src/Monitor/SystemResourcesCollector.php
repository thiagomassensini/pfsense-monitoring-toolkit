<?php
namespace PfSense\Monitoring\Monitor;

use PfSense\Monitoring\Lib\Metric;
use PfSense\Monitoring\Lib\Exec;

final class SystemResourcesCollector implements Metric
{
    public function collect(array $options = []): array
    {
        $ts = time();
        $metrics = [];

        // Load average
        if (is_readable('/proc/loadavg')) {
            $load = trim(file_get_contents('/proc/loadavg'));
            [$l1,$l5,$l15] = array_map('floatval', array_slice(preg_split('/\s+/', $load),0,3));
            $metrics[] = ['metric'=>'system_load1','value'=>$l1,'timestamp'=>$ts,'labels'=>[]];
            $metrics[] = ['metric'=>'system_load5','value'=>$l5,'timestamp'=>$ts,'labels'=>[]];
            $metrics[] = ['metric'=>'system_load15','value'=>$l15,'timestamp'=>$ts,'labels'=>[]];
        }

        // Memory info
        if (is_readable('/proc/meminfo')) {
            $meminfo = file('/proc/meminfo');
            $parsed = [];
            foreach ($meminfo as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                    $parsed[$m[1]] = (int)$m[2];
                }
            }
            if (isset($parsed['MemTotal'],$parsed['MemAvailable'])) {
                $used = $parsed['MemTotal'] - $parsed['MemAvailable'];
                $metrics[] = ['metric'=>'memory_used_kb','value'=>$used,'timestamp'=>$ts,'labels'=>[]];
                $metrics[] = ['metric'=>'memory_total_kb','value'=>$parsed['MemTotal'],'timestamp'=>$ts,'labels'=>[]];
            }
        }

        // Uptime
        if (is_readable('/proc/uptime')) {
            $up = (float)explode(' ', trim(file_get_contents('/proc/uptime')))[0];
            $metrics[] = ['metric'=>'system_uptime_seconds','value'=>$up,'timestamp'=>$ts,'labels'=>[]];
        }

        return $metrics;
    }
}
