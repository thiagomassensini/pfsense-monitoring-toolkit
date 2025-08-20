<?php
namespace PfSense\Monitoring\Lib;

final class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO  = 'INFO';
    public const LEVEL_WARN  = 'WARN';
    public const LEVEL_ERROR = 'ERROR';

    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if ($level === self::LEVEL_DEBUG && !$this->debug) {
            return;
        }
        $line = [
            'ts' => time(),
            'level' => $level,
            'msg' => $message,
        ];
        if ($context) {
            $line['context'] = $context;
        }
        fwrite(STDERR, json_encode($line, JSON_UNESCAPED_SLASHES) . "\n");
    }

    public function debug(string $m, array $c = []): void { $this->log(self::LEVEL_DEBUG, $m, $c); }
    public function info(string $m, array $c = []): void  { $this->log(self::LEVEL_INFO,  $m, $c); }
    public function warn(string $m, array $c = []): void  { $this->log(self::LEVEL_WARN,  $m, $c); }
    public function error(string $m, array $c = []): void { $this->log(self::LEVEL_ERROR, $m, $c); }
}
