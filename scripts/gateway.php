#!/usr/local/bin/php -f
<?php
/**
 * gateway.php - Discovery e status de gateways para Zabbix / automação
 * Autoria: Thiago Motta Massensini (2025)
 * Licença: MIT
 */
require_once("/etc/inc/globals.inc");
require_once("certs.inc");
require_once("gwlb.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");
require_once("services.inc");
require_once("system.inc");
require_once("classes/autoload.inc.php");

$op = $argv[1] ?? '';

function getGateways(): array {
    $a_gateways = return_gateways_array();
    $dados = [];
    $i = 0;
    foreach ($a_gateways as $gw) {
        $dados[$i]['{#NAME}']      = (string)($gw['name'] ?? '');
        $dados[$i]['{#GW}']        = (string)($gw['gateway'] ?? '');
        $dados[$i]['{#DESCR}']     = (string)($gw['descr'] ?? '');
        $dados[$i]['{#IFALIAS}']   = (string)($gw['friendlyiface'] ?? '');
        $dados[$i]['{#ISDEFAULT}'] = (string)($gw['isdefaultgw'] ?? '');
        $dados[$i]['{#INTERFACE}'] = (string)($gw['interface'] ?? '');
        $i++;
    }
    return $dados;
}

function map_status_value(string $raw): int {
    switch ($raw) {
        case 'online': return 1;
        case 'down': return 0;
        case 'loss': return 2;
        case 'delay': return 3; // compat legado
        default: return 4;      // desconhecido / outro
    }
}

function map_substatus_value(string $raw): int {
    switch ($raw) {
        case 'none': return 0;
        case 'highloss': return 1;
        case 'highdelay': return 2;
        default: return 3;
    }
}

function getStatusGateways(string $nome, string $item) {
    if ($nome === '') return 'ZBX_NOTSUPPORTED';
    $gateways_status = return_gateways_status(true) ?: [];
    if (!isset($gateways_status[$nome])) return 'ZBX_NOTSUPPORTED';

    $gw = $gateways_status[$nome];
    switch ($item) {
        case 'status':
            return map_status_value($gw['status'] ?? '');
        case 'substatus':
            return map_substatus_value($gw['substatus'] ?? 'none');
        case 'delay':
        case 'stddev':
            return (float)str_replace('ms','', $gw[$item] ?? '0');
        case 'loss':
            return (float)str_replace('%','', $gw[$item] ?? '0');
        default:
            return $gw[$item] ?? '';
    }
}

switch ($op) {
    case 'discovery':
        echo json_encode(['data' => getGateways()], JSON_UNESCAPED_SLASHES) . "\n";
        break;
    case 'status':
        echo getStatusGateways($argv[2] ?? '', $argv[3] ?? '') . "\n";
        break;
    default:
        fwrite(STDERR, "Uso: gateway.php discovery | status <NOME> <item>\n");
        exit(1);
}
