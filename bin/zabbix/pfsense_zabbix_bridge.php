#!/usr/bin/env php
<?php
/**
 * pfSense 2.8.0 Monitoring Toolkit - Zabbix Bridge
 *
 * Compat layer para permitir uso de um template Zabbix existente
 * reutilizando nomes de comandos/argumentos esperados (ex: discovery, gw_value, etc.).
 *
 * Autoria original deste bridge e toolkit: Thiago Motta Massensini (2025)
 * Inspiração: conceitualmente inspirado em scripts públicos de monitoramento de pfSense
 * sem reutilização literal de código.
 *
 * Licença: MIT
 */

// Estratégia:
//  - Manter os nomes de "main commands" aceitos pelo template Zabbix
//  - Fornecer resultados mínimos ou placeholder usando coletores já existentes
//  - Facilitar futura expansão (TODO tags) sem bloquear integração inicial

require_once __DIR__ . '/../../vendor/autoload.php';

use PfSense\Monitoring\Lib\Logger;
use PfSense\Monitoring\Monitor\GatewayLatencyCollector;
use PfSense\Monitoring\Monitor\SystemResourcesCollector;
use PfSense\Monitoring\Monitor\StateTableCollector;
use PfSense\Monitoring\Monitor\SpeedtestCollector;

$logger = new Logger();

if ($argc < 2) {
    fwrite(STDERR, "Uso: php pfsense_zabbix_bridge.php <comando> [args...]\n");
    exit(1);
}

$command = strtolower($argv[1]);

// Helper: saída JSON simples para discovery (Zabbix LLD)
function lld(array $rows): void {
    echo json_encode(['data' => $rows], JSON_UNESCAPED_SLASHES) . "\n";
}

// Execução segura de coletor existente retornando mapa chave->valor
function runCollector($collector, array $options = []): array {
    try {
        $data = $collector->collect($options);
        $flat = [];
        foreach ($data as $entry) {
            if (!isset($entry['metric']) || !array_key_exists('value', $entry)) { continue; }
            $labelSuffix = '';
            if (!empty($entry['labels'])) {
                foreach ($entry['labels'] as $lk=>$lv) { $labelSuffix .= "_{$lk}_{$lv}"; }
            }
            $flat[$entry['metric'] . $labelSuffix] = $entry['value'];
        }
        return $flat;
    } catch (\Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

switch ($command) {
    case 'discovery':
        $section = $argv[2] ?? '';
        switch ($section) {
            case 'gw': // Gateways discovery (placeholder)
                // TODO: Implement gateway parsing real (pfSense específico)
                lld([]); // vazio por ora
                break;
            case 'interfaces':
            case 'wan':
                // Reutilizar state-table collector só para produzir LLD mínima? Melhor vazio até haver collector dedicado.
                lld([]);
                break;
            case 'services':
            case 'openvpn_server':
            case 'openvpn_client':
            case 'openvpn_server_user':
            case 'ipsec_ph1':
            case 'ipsec_ph2':
            case 'temperature_sensors':
            case 'certificates':
            case 'dhcpfailover':
                lld([]); // Não implementado ainda
                break;
            default:
                lld([]);
        }
        break;

    case 'gw_value':
        // Placeholder: sem implementação de consulta a gateways ainda
        // Zabbix vai receber vazio
        echo "0\n";
        break;

    case 'gw_status':
        echo "\n"; // lista vazia
        break;

    case 'service_value':
        echo "0\n"; // placeholder
        break;

    case 'carp_status':
        echo "0\n"; // 0 = sem CARP configurado
        break;

    case 'if_speedtest_value':
        $if = $argv[2] ?? 'wan';
        $field = $argv[3] ?? 'download_mbps';
        $collector = new SpeedtestCollector();
        $res = runCollector($collector, []);
        // Mapear campos conhecidos
        $map = [
            'download_mbps' => $res['speedtest_download_mbps'] ?? '',
            'upload_mbps' => $res['speedtest_upload_mbps'] ?? '',
            'ping_ms' => $res['speedtest_ping_ms'] ?? ''
        ];
        echo ($map[$field] ?? '') . "\n";
        break;

    case 'system':
        $section = $argv[2] ?? '';
        $collector = new SystemResourcesCollector();
        $res = runCollector($collector, []);
        switch ($section) {
            case 'script_version':
                echo 'bridge-0.1.0' . "\n";
                break;
            case 'uptime':
                echo ($res['system_uptime_seconds'] ?? '') . "\n";
                break;
            default:
                echo "\n";
        }
        break;

    case 'state_table': // custom helper (não parte do original, mas útil)
        $collector = new StateTableCollector();
        $res = runCollector($collector, []);
        echo json_encode($res, JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'smart_status':
        echo "0\n"; // placeholder sem S.M.A.R.T parsing
        break;

    case 'file_exists':
        $fname = $argv[2] ?? '';
        echo (is_file($fname) ? '1' : '0') . "\n";
        break;

    case 'temperature':
        echo "\n"; // não implementado
        break;

    case 'test':
    default:
        // Exibe um resumo das métricas disponíveis através dos coletores atuais
        $summary = [
            'gateway_latency' => runCollector(new GatewayLatencyCollector(), ['gateway' => '1.1.1.1', 'count' => 1]),
            'system_resources' => runCollector(new SystemResourcesCollector(), []),
            'state_table' => runCollector(new StateTableCollector(), []),
            'speedtest' => runCollector(new SpeedtestCollector(), ['ttl' => 0])
        ];
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
