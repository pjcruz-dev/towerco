#!/usr/bin/env bash
# Sync dnsmasq wildcard zones from TowerOS central `domains` table.
#
# When Platform creates tenants/environments under a new brand root
# (e.g. alliance.lan alongside toweros.lan), this script adds:
#   address=/alliance.lan/<LAN_IP>
# so staging.atc.alliance.lan / app.atc.alliance.lan resolve without
# editing .env or /etc/hosts per environment.
#
# One-time Ubuntu setup:
#   chmod +x scripts/sync-toweros-dnsmasq-brands.sh
#   export TOWEROS_DNS_IP=192.168.90.24   # Ubuntu LAN IP
#   sudo -E ./scripts/sync-toweros-dnsmasq-brands.sh
#
# Cron (every 5 minutes as root):
#   */5 * * * * TOWEROS_DNS_IP=192.168.90.24 /home/it-server-ubuntu/apps/TowerOS/scripts/sync-toweros-dnsmasq-brands.sh >>/var/log/toweros-dnsmasq-sync.log 2>&1
#
# Dry run (no write / no reload):
#   TOWEROS_DNS_IP=192.168.90.24 ./scripts/sync-toweros-dnsmasq-brands.sh --dry-run
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

DNS_IP="${TOWEROS_DNS_IP:-}"
if [[ -z "$DNS_IP" ]]; then
  DNS_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i=1;i<=NF;i++) if ($i=="src") {print $(i+1); exit}}' || true)"
fi
if [[ -z "$DNS_IP" ]]; then
  DNS_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
fi
if [[ -z "$DNS_IP" ]]; then
  echo "ERROR: Set TOWEROS_DNS_IP to the Ubuntu LAN IP (e.g. 192.168.90.24)." >&2
  exit 1
fi

ENV_FILE="${TOWEROS_COMPOSE_ENV_FILE:-.env.docker}"
CONF_PATH="${TOWEROS_DNSMASQ_CONF:-/etc/dnsmasq.d/toweros-brands.conf}"
COMPOSE=(docker compose --env-file "$ENV_FILE")

MYSQL_ROOT_PASSWORD="$(
  grep -E '^MYSQL_ROOT_PASSWORD=' "$ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '\r' || true
)"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-toweros}"
MYSQL_DATABASE="$(
  grep -E '^MYSQL_DATABASE=' "$ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '\r' || true
)"
MYSQL_DATABASE="${MYSQL_DATABASE:-toweros}"

if ! "${COMPOSE[@]}" ps --status running mysql 2>/dev/null | grep -q mysql; then
  echo "ERROR: toweros-mysql is not running. Start the stack first." >&2
  exit 1
fi

DOMAINS_RAW="$(
  "${COMPOSE[@]}" exec -T mysql \
    mysql -N -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" \
    -e "SELECT DISTINCT domain FROM domains WHERE domain IS NOT NULL AND domain != '' ORDER BY domain;" \
    2>/dev/null | tr -d '\r' || true
)"

# Brand root = last two labels for *.lan / *.local (toweros.lan, alliance.lan).
# Also keep explicit *.localhost for local-style tenants.
mapfile -t BRAND_ROOTS < <(
  printf '%s\n' "$DOMAINS_RAW" | awk '
    {
      host = tolower($0)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", host)
      if (host == "" || host ~ /^[0-9.]+$/ || host ~ /^\[/) next
      n = split(host, parts, ".")
      if (n < 2) next
      tld = parts[n]
      if (tld == "lan" || tld == "local") {
        print parts[n-1] "." tld
        next
      }
      if (tld == "localhost") {
        print "localhost"
      }
    }
  ' | sort -u
)

ALWAYS_ROOTS="${TOWEROS_DNS_ALWAYS_ROOTS:-toweros.lan}"
for root in $ALWAYS_ROOTS; do
  BRAND_ROOTS+=("$root")
done

mapfile -t BRAND_ROOTS < <(printf '%s\n' "${BRAND_ROOTS[@]}" | awk 'NF' | sort -u)

if [[ ${#BRAND_ROOTS[@]} -eq 0 ]]; then
  echo "WARNING: No brand roots found in domains table; writing always-roots only."
fi

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

{
  echo "# Managed by scripts/sync-toweros-dnsmasq-brands.sh — do not edit by hand."
  echo "# Generated: $(date -Iseconds)  DNS_IP=${DNS_IP}"
  echo "#"
  for root in "${BRAND_ROOTS[@]}"; do
    [[ -z "$root" ]] && continue
    echo "address=/${root}/${DNS_IP}"
  done
} >"$TMP"

echo "Brand roots → ${DNS_IP}:"
sed -n 's/^address=\/\([^/]*\)\/.*/  *.\1/p' "$TMP" || true

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo
  echo "Dry run — would write ${CONF_PATH}:"
  cat "$TMP"
  exit 0
fi

if [[ "$(id -u)" -ne 0 ]]; then
  echo "ERROR: Writing ${CONF_PATH} requires root. Re-run with sudo -E." >&2
  exit 1
fi

install -m 0644 "$TMP" "$CONF_PATH"

if systemctl is-active --quiet dnsmasq 2>/dev/null; then
  systemctl reload dnsmasq 2>/dev/null || systemctl restart dnsmasq
  echo "dnsmasq reloaded (${CONF_PATH})"
elif command -v dnsmasq >/dev/null 2>&1; then
  echo "WARNING: dnsmasq unit not active; config written to ${CONF_PATH}."
  echo "Start with: systemctl enable --now dnsmasq"
else
  echo "WARNING: dnsmasq not installed; config written to ${CONF_PATH}."
  echo "Install with: apt-get install -y dnsmasq"
fi

# Quick self-check for first non-localhost root
for root in "${BRAND_ROOTS[@]}"; do
  if [[ "$root" != "localhost" ]]; then
    probe="ping.${root}"
    resolved="$(dig +short "@${DNS_IP}" "$probe" 2>/dev/null | head -n1 || true)"
    if [[ "$resolved" == "$DNS_IP" ]]; then
      echo "OK: ${probe} → ${resolved}"
    else
      echo "NOTE: dig @${DNS_IP} ${probe} → '${resolved:-empty}' (clients must use this DNS)"
    fi
    break
  fi
done
