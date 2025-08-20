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

## Licença
MIT © 2025 Thiago Motta Massensini. Veja `LICENSE` e `NOTICE.md`.
