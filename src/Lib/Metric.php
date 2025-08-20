<?php
namespace PfSense\Monitoring\Lib;

interface Metric
{
    /**
     * @return array<int, array{metric:string, value:float|int, timestamp:int, labels:array<string,string>, error?:string}>
     */
    public function collect(array $options = []): array;
}
