<?php
namespace PfSense\Monitoring\Lib;

use RuntimeException;

final class Exec
{
    /**
     * Executa comando com timeout em segundos.
     * Retorna [exitCode, stdout, stderr]. ExitCode -1 indica timeout forçado.
     */
    public static function command(string $cmd, int $timeout = 10): array
    {
        $descriptor = [0=>['pipe','r'],1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell'=>false]);
        if (!is_resource($process)) {
            throw new RuntimeException("Falha ao iniciar comando: $cmd");
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $start = microtime(true);
        $stdout = '';
        $stderr = '';
        $timedOut = false;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if ((microtime(true) - $start) > $timeout) {
                $pid = $status['pid'];
                if (function_exists('posix_kill') && $pid) {
                    @posix_kill($pid, 9);
                } else {
                    proc_terminate($process);
                }
                $timedOut = true;
                break;
            }
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            usleep(50000);
        }
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $exit = $timedOut ? -1 : ($status['exitcode'] ?? 0);
        return [$exit, $stdout, $stderr];
    }
}
