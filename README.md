# pfSense 2.8.0 Monitoring Toolkit

Toolkit leve em PHP para coleta de métricas operacionais em instalações pfSense 2.8.0.

## Principais Métricas
- Latência e perda de pacotes de gateways (`gateway-latency`)
- Recursos de sistema (memória, load, uptime) (`system-resources`)
- State table (entradas, inserts, removals) (`state-table`)
- Speedtest com cache (download, upload, ping) (`speedtest` – depende de CLI externa)
- Futuro: Throughput de interfaces, DNS health, iperf3, gateways avançados

## Filosofia
- Arquitetura modular: cada coletor implementa uma interface comum
- Saída padrão: JSON line; opcional Prometheus exposition format
- Falhas previsíveis: códigos de saída diferenciados + campo `error`
- Zero dependências externas além do PHP CLI e ferramentas nativas do sistema

## Estrutura
```
src/
  Lib/        Utilidades (Logger, Exec, Format, Validation)
  Monitor/    Coletores (GatewayLatency, SystemResources, ...)
bin/          Wrappers executáveis (collect)
  zabbix/     Bridge de compatibilidade para template Zabbix
scripts/      Manutenção/validação
tests/        Testes PHPUnit
```

## Uso Rápido (exemplos)
```bash
# Latência (3 pings, destino customizado)
php bin/collect gateway-latency --gateway 1.1.1.1 --count 3

# Recursos de sistema (JSON)
php bin/collect system-resources --format json

# State table (Prometheus)
php bin/collect state-table --format prometheus

# Speedtest (usa cache de 6h por padrão)
php bin/collect speedtest --ttl 3600 --format json

# Speedtest em formato Prometheus
php bin/collect speedtest --format prometheus
```

## Bridge para Template Zabbix (Experimental)
Para facilitar migração gradual de um ambiente que já utiliza um template Zabbix legado, existe o script de bridge:

```bash
php bin/zabbix/pfsense_zabbix_bridge.php <comando> [args]
```

Objetivo: aceitar comandos esperados (ex: `discovery`, `gw_value`, `service_value`, `if_speedtest_value`, etc.) e devolver saídas compatíveis mínimas, permitindo que itens já definidos no Zabbix não quebrem enquanto a implementação nativa evolui.

Status atual:
- Implementado: `if_speedtest_value`, `system (uptime/script_version)`, `state_table` (helper extra), `file_exists`, `test`
- Placeholders retornando neutro/vazio: `discovery` (todas as seções), `gw_value`, `gw_status`, `service_value`, `carp_status`, `smart_status`, `temperature`
- Próximos passos: adicionar discovery real de interfaces/gateways e serviços usando coletores dedicados

Exemplo rápido:
```bash
php bin/zabbix/pfsense_zabbix_bridge.php test | jq
```

ATENÇÃO: Este bridge apenas se inspira conceitualmente em scripts públicos de terceiros, sem reutilização literal de código. Expanda gradualmente substituindo placeholders por lógica real puxando APIs/arquivos do pfSense.  Autoria original de Thiago Motta Massensini.

## Formato JSON (exemplo)
```json
{"metric":"gateway_latency_ms","value":12.4,"timestamp":1692480000,"labels":{"gateway":"WAN_DHCP"}}
```

## Roadmap Inicial
- [x] Coletor: Latência de gateway
- [x] Coletor: Recursos de sistema
- [x] Coletor: State table (pfctl -si)
- [x] Export: Formato Prometheus (HELP/TYPE)
- [x] Cache de resultados de speedtest
- [x] Coletor: Speedtest (com cache)
- [ ] Plugin: Escrita em arquivo / spool
- [ ] Script: push para Pushgateway
- [ ] Coletor: Interface throughput
- [ ] Coletor: DNS resolver health
- [ ] Zabbix bridge: discovery real de gateways/interfaces/serviços

## Licença
MIT © 2025 Thiago Motta Massensini. Veja `LICENSE` e `NOTICE.md`.

## Automação Pós-Instalação (pfSense 2.8.0)
Para acelerar a preparação de um novo firewall com monitoramento:

1. Acesse Diagnostics > Command Prompt (Shell) no pfSense recém-instalado 2.8.0.
2. Rode (ajuste URL se for usar tag específica):
```bash
fetch -o /root/postinstall.sh https://raw.githubusercontent.com/thiagomassensini/pfsense-monitoring-toolkit/main/scripts/postinstall_pfsense_2.8.0.sh
chmod +x /root/postinstall.sh
sh /root/postinstall.sh
```
3. Ajuste credenciais de e-mail em `/root/pfsense-backup.py` se quiser envio automático de backup.
4. Verifique `zabbix_agentd.conf` e `zabbix_agentd.conf.d/pfsense-monitoring.conf` para confirmar UserParameters.

O script executa:
- Atualização de índice e instalação de pacotes (Cron, pfBlockerNG-devel, Zabbix Agent/Proxy 6, etc.)
- Criação dos scripts: `gateway.php` (discovery/status), `pfsense_zbx.php` (bridge baixada do repositório)
- Configuração de `AllowRoot=1` e UserParameters Zabbix padronizados
- Script de backup `/root/pfsense-backup.py`

Rollback rápido: restaurar backup original do `zabbix_agentd.conf` gerado (`*.bak.<timestamp>`).
