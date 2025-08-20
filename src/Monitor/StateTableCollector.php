<?php
namespace PfSense\Monitoring\Monitor;

use PfSense\Monitoring\Lib\Metric;
use PfSense\Monitoring\Lib\Exec;

final class StateTableCollector implements Metric
{
    public function collect(array $options = []): array
    {
        $ts = time();
        [$exit,$out,$err] = Exec::command('pfctl -si 2>&1', $options['timeout'] ?? 5);
        if ($exit !== 0) {
            return [[
                'metric'=>'pf_state_current_entries',
                'value'=>0,
                'timestamp'=>$ts,
                'labels'=>[],
                'error'=> trim($err ?: $out) ?: 'pfctl not available'
            ]];
        }
        $lines = preg_split('/\r?\n/', trim($out));
        $map = [
            'current entries' => 'pf_state_current_entries',
            'searches' => 'pf_state_searches_total',
            'inserts' => 'pf_state_inserts_total',
            'removals' => 'pf_state_removals_total',
        ];
        $metrics = [];
        foreach ($lines as $ln) {
            if (preg_match('/^(.*?):\s*(\d+)/', strtolower($ln), $m)) {
                $key = trim($m[1]);
                $val = (int)$m[2];
                if (isset($map[$key])) {
                    $metrics[] = [
                        'metric'=>$map[$key],
                        'value'=>$val,
                        'timestamp'=>$ts,
                        'labels'=>[]
                    ];
                }
            }
        }
        if (!$metrics) {
            $metrics[] = [
                'metric'=>'pf_state_current_entries',
                'value'=>0,
                'timestamp'=>$ts,
                'labels'=>[],
                'error'=>'parse_failed'
            ];
        }
        return $metrics;
    }
}
