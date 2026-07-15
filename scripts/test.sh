#!/usr/bin/env bash
# TowerOS scenario test runner (macOS / Linux / Git Bash)
#
# Usage:
#   ./scripts/test.sh
#   ./scripts/test.sh all
#   ./scripts/test.sh list
#   ./scripts/test.sh scenario team-access
#   ./scripts/test.sh backend rollout
#   ./scripts/test.sh frontend

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"

COMMAND="${1:-smoke}"
TARGET="${2:-}"

declare -A SCENARIO_SUITE=(
  [smoke]=Smoke
  [team-access]=TeamAccess
  [admin]=TeamAccess
  [e-approval]=EApproval
  [rollout]=Rollout
  [procurement]=ProcurementOne
  [documents]=Documents
  [project]=ProjectOne
  [ticketing]=Ticketing
  [platform]=Platform
  [infrastructure]=Infrastructure
  [notifications]=Notifications
  [unit]=Unit
  [feature]=Feature
)

header() {
  printf '\n==> %s\n' "$1"
}

run_backend_suite() {
  local suite="$1"
  header "Backend: $suite"
  (
    cd "$BACKEND"
    if [[ "$suite" == "Feature" ]]; then
      php -d memory_limit=1G artisan test --testsuite=Feature
    elif [[ "$suite" == "Unit" ]]; then
      php artisan test --testsuite=Unit
    else
      php -d memory_limit=1G artisan test --testsuite="$suite"
    fi
  )
}

run_frontend_unit_tests() {
  header "Frontend: vitest"
  (cd "$FRONTEND" && npm run test)
}

run_frontend_checks() {
  run_frontend_unit_tests
  header "Frontend: typecheck"
  (cd "$FRONTEND" && npm run typecheck)
  header "Frontend: eslint"
  (cd "$FRONTEND" && npm run lint)
}

run_all() {
  run_backend_suite Feature
  run_backend_suite Unit
  run_frontend_checks
}

print_list() {
  cat <<'EOF'
Available backend scenarios:
  smoke            Smoke (AdminOne + Http + Workspace)
  team-access      Team & Access / IAM
  admin            Team & Access (alias)
  e-approval       E-Approval
  rollout          Rollout / Project-One gates
  procurement      Procurement-One
  documents        Documents
  project          Project-One
  ticketing        Ticketing
  platform         Platform superadmin
  infrastructure   Infrastructure
  notifications    Notifications
  unit             Backend unit tests
  feature          All backend feature tests

Examples:
  ./scripts/test.sh
  ./scripts/test.sh scenario team-access
  ./scripts/test.sh all
EOF
}

case "$COMMAND" in
  list)
    print_list
    ;;
  smoke)
    run_backend_suite Smoke
    run_frontend_unit_tests
    ;;
  all)
    run_all
    ;;
  backend)
    key="${TARGET:-feature}"
    suite="${SCENARIO_SUITE[$key]:-}"
    if [[ -z "$suite" ]]; then
      echo "Unknown backend suite '$key'. Run ./scripts/test.sh list" >&2
      exit 1
    fi
    run_backend_suite "$suite"
    ;;
  frontend)
    run_frontend_checks
    ;;
  scenario)
    if [[ -z "$TARGET" ]]; then
      echo "Specify a scenario name. Example: ./scripts/test.sh scenario team-access" >&2
      exit 1
    fi
    suite="${SCENARIO_SUITE[$TARGET]:-}"
    if [[ -z "$suite" ]]; then
      echo "Unknown scenario '$TARGET'. Run ./scripts/test.sh list" >&2
      exit 1
    fi
    run_backend_suite "$suite"
    ;;
  *)
    echo "Unknown command '$COMMAND'. Run ./scripts/test.sh list" >&2
    exit 1
    ;;
esac

printf '\nDone.\n'
