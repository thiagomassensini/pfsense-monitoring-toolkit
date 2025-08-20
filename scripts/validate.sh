#!/usr/bin/env bash
set -euo pipefail

fail=0

echo "[validate] Verificando PHP >= 8.1" >&2
if ! php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=")?0:1);'; then
  echo "ERRO: PHP >= 8.1 necessário" >&2
  fail=1
fi

echo "[validate] Checando composer.json" >&2
if [ ! -f composer.json ]; then
  echo "ERRO: composer.json não encontrado" >&2
  fail=1
fi

if [ $fail -ne 0 ]; then
  echo "Falhou" >&2
  exit 1
fi

echo "OK" >&2
