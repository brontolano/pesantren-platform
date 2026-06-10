#!/usr/bin/env bash
# Smoke test produksi PWA Peserta (/spmb) & Wali (/wali) per tenant.
# Pakai: bash scripts/smoke-pwa.sh [host]   (default demo.pesantren.online)
# Exit 0 = semua sehat; exit 1 = ada endpoint gagal (cocok untuk cron alert).
set -u
HOST="${1:-demo.pesantren.online}"
FAIL=0

check() {
  local path="$1" want="$2"
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://${HOST}${path}")
  if [ "$code" = "$want" ]; then
    echo "OK   ${code} ${path}"
  else
    echo "FAIL ${code} (harap ${want}) ${path}"
    FAIL=1
  fi
}

echo "== Smoke PWA @ ${HOST} =="
# Peserta SPMB
check /spmb/login 200
check /spmb/manifest.json 200
check /spmb/sw.js 200
check "/spmb/icon.svg?size=192" 200
check /spmb 302            # guest -> redirect login
# Wali
check /wali/manifest.json 200
check /wali/sw.js 200
check "/wali/icon.svg?size=192" 200
# Panel & landing
check /admin/login 200
check / 200

exit $FAIL
