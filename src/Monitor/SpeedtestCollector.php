<?php
namespace PfSense\Monitoring\Monitor;

use PfSense\Monitoring\Lib\Metric;
use PfSense\Monitoring\Lib\Exec;

final class SpeedtestCollector implements Metric
{
    public function collect(array $options = []): array
    {
        $ttl = (int)($options['ttl'] ?? 21600);
        $cacheDir = $options['cache_dir'] ?? __DIR__ . '/../../cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
        $cacheFile = rtrim($cacheDir,'/').'/speedtest.json';
        $now = time();
        if (is_file($cacheFile) && ($now - filemtime($cacheFile) < $ttl)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            return $this->toMetrics($data, true);
        }
        $cmds = ['speedtest --format=json','speedtest -f json'];
        $result = null; $errAcc = [];
        foreach ($cmds as $cmd) {
            [$exit,$out,$err] = Exec::command($cmd, $options['timeout'] ?? 60);
            if ($exit === 0 && trim($out) !== '') {
                $dec = json_decode($out, true);
                if (is_array($dec)) { $result = $dec; break; }
            }
            $errAcc[] = $err ?: $out;
        }
        if ($result) {
            @file_put_contents($cacheFile, json_encode($result));
            return $this->toMetrics($result, false);
        }
        return [[
            'metric'=>'speedtest_download_mbps',
            'value'=>0,
            'timestamp'=>$now,
            'labels'=>['cached'=>'false'],
            'error'=>'speedtest_failed:'.substr(implode(';', $errAcc),0,120)
        ]];
    }

    private function toMetrics(array $data, bool $cached): array
    {
        $ts = time();
        $dl = $data['download']['bandwidth'] ?? null;
        $ul = $data['upload']['bandwidth'] ?? null;
        $ping = $data['ping']['latency'] ?? null;
        $metrics = [];
        if ($dl !== null) {
            $metrics[] = ['metric'=>'speedtest_download_mbps','value'=>round(($dl*8)/1_000_000,2),'timestamp'=>$ts,'labels'=>['cached'=>$cached?'true':'false']];
        }
        if ($ul !== null) {
            $metrics[] = ['metric'=>'speedtest_upload_mbps','value'=>round(($ul*8)/1_000_000,2),'timestamp'=>$ts,'labels'=>['cached'=>$cached?'true':'false']];
        }
        if ($ping !== null) {
            $metrics[] = ['metric'=>'speedtest_ping_ms','value'=>round($ping,2),'timestamp'=>$ts,'labels'=>['cached'=>$cached?'true':'false']];
        }
        return $metrics ?: [[
            'metric'=>'speedtest_download_mbps',
            'value'=>0,
            'timestamp'=>$ts,
            'labels'=>['cached'=>$cached?'true':'false'],
            'error'=>'missing_fields'
        ]];
    }
}
