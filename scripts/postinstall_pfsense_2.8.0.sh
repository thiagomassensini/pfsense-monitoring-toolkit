#!/bin/sh
###############################################################################
# Script de pós-instalação pfSense 2.8.0
# Autor: Thiago Motta Massensini / M3Solutions (2025)
# Licença: MIT (este script faz parte do repositório pfsense-monitoring-toolkit)
#
# Objetivos:
#   1. Atualizar índice de pacotes.
#   2. Instalar pacotes úteis (Cron, OpenVPN Client Export, pfBlockerNG-devel,
#      sudo, System_Patches, Zabbix Agent / Proxy 6).
#   3. Criar diretório /scripts e adicionar:
#        /scripts/gateway.php            (discovery + status de gateways)
#        /scripts/pfsense_zbx.php        (baixado do GitHub - bridge Zabbix)
#   4. Criar /root/pfsense-backup.py     (envio de backup por e-mail).
#
# Diferenças em relação ao script legado:
#   - NÃO embute o código original de terceiros do pfsense_zbx.php.
#   - Faz download da versão pública mantida neste repositório:
#       https://github.com/thiagomassensini/pfsense-monitoring-toolkit
#   - Mantém compatibilidade de nome de arquivo (pfsense_zbx.php) para o template.
#
# Uso sugerido: Executar via Diagnostics > Command Prompt (Shell) após instalação.
###############################################################################

set -e

echo "[INFO] Iniciando pós-instalação para pfSense 2.8.0" 1>&2

###############################################################################
# 1) Atualiza índice de pacotes
###############################################################################
echo "[INFO] Atualizando índice de pacotes (pkg update -f)" 1>&2
pkg update -f

###############################################################################
# 2) Instala pacotes necessários
###############################################################################
echo "[INFO] Instalando pacotes principais" 1>&2
pkg install -y \
    pfSense-pkg-Cron \
    pfSense-pkg-openvpn-client-export \
    pfSense-pkg-pfBlockerNG-devel \
    pfSense-pkg-sudo \
    pfSense-pkg-System_Patches \
    pfSense-pkg-zabbix-agent6 \
    pfSense-pkg-zabbix-proxy6 || echo "[WARN] Alguns pacotes podem não ter sido instalados." 1>&2

# (Opcional) Python para script de backup
if ! command -v python3.11 >/dev/null 2>&1; then
  echo "[INFO] Instalando Python 3.11" 1>&2
  pkg install -y python311 || echo "[WARN] Falha ao instalar python311" 1>&2
fi

###############################################################################
# 3) Diretório /scripts
###############################################################################
mkdir -p /scripts
chmod 755 /scripts

###############################################################################
# 3.1) gateway.php (adaptado / compatível)
###############################################################################
cat <<'EOF_GATEWAY' > /scripts/gateway.php
#!/usr/local/bin/php -f
<?php
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
        case 'delay': return 3;
        default: return 4;
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
EOF_GATEWAY
chmod +x /scripts/gateway.php

###############################################################################
# 3.2) pfsense_zbx.php (baixado do GitHub em vez de embutido)
###############################################################################
ZABBIX_BRIDGE_URL="https://raw.githubusercontent.com/thiagomassensini/pfsense-monitoring-toolkit/main/bin/zabbix/pfsense_zabbix_bridge.php"

echo "[INFO] Baixando bridge Zabbix de: $ZABBIX_BRIDGE_URL" 1>&2
if command -v curl >/dev/null 2>&1; then
  curl -fSL -o /scripts/pfsense_zbx.php "$ZABBIX_BRIDGE_URL" || {
    echo "[ERRO] Falha ao baixar bridge Zabbix" 1>&2; exit 2; }
elif command -v fetch >/dev/null 2>&1; then
  fetch -o /scripts/pfsense_zbx.php "$ZABBIX_BRIDGE_URL" || {
    echo "[ERRO] Falha ao baixar bridge Zabbix (fetch)" 1>&2; exit 2; }
else
  echo "[ERRO] Nem curl nem fetch disponíveis." 1>&2; exit 2
fi
chmod +x /scripts/pfsense_zbx.php

###############################################################################
# 4) Script de backup /root/pfsense-backup.py
###############################################################################
cat <<'EOF_BACKUP' > /root/pfsense-backup.py
#!/usr/bin/env python3.11
"""Envia config.xml do pfSense por e-mail (ajustar parâmetros antes de usar)"""
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.application import MIMEApplication
import socket
import os

SENDER = "remetente@dominio.com.br"
SENDER_PASSWORD = "SENHA"
RECEIVER = "destinatario@dominio.com.br"
SMTP_SERVER = "smtp.dominio.com.br"
SMTP_PORT = 465
CONFIG_FILE = "/cf/conf/config.xml"

hostname = socket.gethostname()
subject = f"{hostname} - Backup Config XML"

def main():
    if not os.path.isfile(CONFIG_FILE):
        raise SystemExit(f"Arquivo não encontrado: {CONFIG_FILE}")

    msg = MIMEMultipart()
    msg["From"] = SENDER
    msg["To"] = RECEIVER
    msg["Subject"] = subject

    with open(CONFIG_FILE, "rb") as f:
        part = MIMEApplication(f.read(), _subtype="xml")
    part.add_header("Content-Disposition", "attachment", filename="config.xml")
    msg.attach(part)

    with smtplib.SMTP_SSL(SMTP_SERVER, SMTP_PORT) as srv:
        srv.login(SENDER, SENDER_PASSWORD)
        srv.sendmail(SENDER, RECEIVER, msg.as_string())

    print("Backup enviado com sucesso.")

if __name__ == "__main__":
    main()
EOF_BACKUP
chmod 700 /root/pfsense-backup.py

echo "" 1>&2
echo "[OK] Pós-instalação concluída." 1>&2
echo "[INFO] Scripts criados em /scripts e /root." 1>&2
echo "[INFO] Ajuste parâmetros de e-mail em /root/pfsense-backup.py antes de usar." 1>&2
echo "[INFO] Autoria: Thiago Motta Massensini (2025)" 1>&2

exit 0
