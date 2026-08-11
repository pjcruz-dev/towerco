#!/bin/sh
set -e
cd /app

if [ ! -f node_modules/.package-lock.json ] && [ ! -d node_modules/next ]; then
  echo "[web] Installing npm dependencies..."
  npm ci
fi

WEB_MODE="${TOWEROS_WEB_MODE:-dev}"

if [ "$WEB_MODE" = "prod" ] || [ "$WEB_MODE" = "production" ]; then
  if [ ! -f .next/BUILD_ID ] || [ "${TOWEROS_WEB_FORCE_BUILD:-0}" = "1" ]; then
    echo "[web] Building Next.js production bundle..."
    NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=3072}" npm run build
  fi
  echo "[web] Next.js production http://0.0.0.0:80"
  exec npm run start
fi

# Docker named volume mounts .next — do not `rm -rf .next` (fails on Windows: "Device or resource busy").
ROUTE_CACHE_STAMP="next16-proxy-v2"
STAMP_FILE="/app/.toweros-route-stamp"
if [ "$(cat "$STAMP_FILE" 2>/dev/null)" != "$ROUTE_CACHE_STAMP" ]; then
  echo "[web] Refreshing Next.js cache (${ROUTE_CACHE_STAMP})..."
  mkdir -p .next
  if [ -d .next ]; then
    find .next -mindepth 1 -delete 2>/dev/null || true
  fi
  echo "$ROUTE_CACHE_STAMP" > "$STAMP_FILE"
fi

export NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=1536}"
echo "[web] Next.js dev http://0.0.0.0:80 (${NODE_OPTIONS})"
exec npm run dev -- -H 0.0.0.0 -p 80
