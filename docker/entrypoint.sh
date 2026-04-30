#!/usr/bin/env sh
set -e

cd /var/www/html

RUN_MIGRATIONS_VALUE="${RUN_MIGRATIONS:-}"
RUN_TENANT_MIGRATIONS_VALUE="${RUN_TENANT_MIGRATIONS:-}"

should_run_migrations=false
case "$RUN_MIGRATIONS_VALUE" in
  1|true|TRUE|yes|YES|on|ON) should_run_migrations=true ;;
  *) should_run_migrations=false ;;
esac

if [ "$should_run_migrations" = "true" ]; then
  echo "[entrypoint] Running central migrations..."
  php artisan migrate --force --no-interaction

  should_run_tenant_migrations=true
  case "$RUN_TENANT_MIGRATIONS_VALUE" in
    0|false|FALSE|no|NO|off|OFF) should_run_tenant_migrations=false ;;
    *) should_run_tenant_migrations=true ;;
  esac

  if [ "$should_run_tenant_migrations" = "true" ]; then
    echo "[entrypoint] Running tenant migrations..."
    php artisan tenants:migrate --force --no-interaction
  fi
fi

echo "[entrypoint] Starting: $*"
exec "$@"
