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

set -eu

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
# 3.1) Baixar gateway.php do repositório
###############################################################################
GATEWAY_URL="https://raw.githubusercontent.com/thiagomassensini/pfsense-monitoring-toolkit/main/scripts/gateway.php"
echo "[INFO] Baixando gateway.php" 1>&2
if command -v curl >/dev/null 2>&1; then
    curl -fSL -o /scripts/gateway.php "$GATEWAY_URL" || { echo "[ERRO] Falha download gateway.php" 1>&2; exit 2; }
else
    fetch -o /scripts/gateway.php "$GATEWAY_URL" || { echo "[ERRO] Falha download gateway.php (fetch)" 1>&2; exit 2; }
fi
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
# 3.3) Configuração Zabbix Agent (UserParameters + AllowRoot)
###############################################################################
ZBX_AGENT_CONF="/usr/local/etc/zabbix_agentd.conf"
ZBX_INCLUDE_DIR="/usr/local/etc/zabbix_agentd.conf.d"
ZBX_MON_CONF="$ZBX_INCLUDE_DIR/pfsense-monitoring.conf"

echo "[INFO] Configurando Zabbix Agent" 1>&2
mkdir -p "$ZBX_INCLUDE_DIR"

# Garante AllowRoot=1 (necessário para vários itens)
if ! grep -q '^AllowRoot=1' "$ZBX_AGENT_CONF" 2>/dev/null; then
    cp "$ZBX_AGENT_CONF" "$ZBX_AGENT_CONF.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
    echo 'AllowRoot=1' >> "$ZBX_AGENT_CONF"
    echo "[INFO] Adicionado AllowRoot=1 em $ZBX_AGENT_CONF" 1>&2
fi

cat > "$ZBX_MON_CONF" <<'EOF_ZBX'
# Arquivo gerado automaticamente pelo postinstall_pfsense_2.8.0.sh
# Autoria: Thiago Motta Massensini (2025)

# Descobertas & valores gerais via bridge
UserParameter=pfsense.discovery[*],/usr/local/bin/php /scripts/pfsense_zbx.php discovery $1
UserParameter=pfsense.value[*],/usr/local/bin/php /scripts/pfsense_zbx.php $1 $2 $3

# Gateways (script dedicado)
UserParameter=pfsense.gateway.discovery,/usr/local/bin/php /scripts/gateway.php discovery
UserParameter=pfsense.gateway.status[*],/usr/local/bin/php /scripts/gateway.php status $1 $2

# Exemplo adicional: state table (helper extra do bridge)
UserParameter=pfsense.statetable.json,/usr/local/bin/php /scripts/pfsense_zbx.php state_table
EOF_ZBX

echo "[INFO] UserParameters gravados em $ZBX_MON_CONF" 1>&2

# Reinicia agente se presente
if command -v service >/dev/null 2>&1 && service zabbix_agentd onestatus >/dev/null 2>&1; then
    service zabbix_agentd restart || echo "[WARN] Falha ao reiniciar zabbix_agentd" 1>&2
else
    echo "[INFO] zabbix_agentd não ativo ainda (será iniciado após reboot ou manualmente)." 1>&2
fi

###############################################################################
# 4) Baixar script de backup
###############################################################################
BACKUP_URL="https://raw.githubusercontent.com/thiagomassensini/pfsense-monitoring-toolkit/main/scripts/pfsense-backup.py"
echo "[INFO] Baixando pfsense-backup.py" 1>&2
if command -v curl >/dev/null 2>&1; then
    curl -fSL -o /root/pfsense-backup.py "$BACKUP_URL" || { echo "[WARN] Falha download backup.py" 1>&2; }
else
    fetch -o /root/pfsense-backup.py "$BACKUP_URL" || { echo "[WARN] Falha download backup.py (fetch)" 1>&2; }
fi
chmod 700 /root/pfsense-backup.py || true

echo "" 1>&2
echo "[OK] Pós-instalação concluída." 1>&2
echo "[INFO] Scripts criados em /scripts e /root." 1>&2
echo "[INFO] Ajuste parâmetros de e-mail em /root/pfsense-backup.py antes de usar." 1>&2
echo "[INFO] Autoria: Thiago Motta Massensini (2025)" 1>&2

exit 0
