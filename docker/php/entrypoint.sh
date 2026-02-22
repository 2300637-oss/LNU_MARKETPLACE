#!/bin/sh
set -eu

cd /var/www

read_env_value() {
  key="$1"
  eval "value=\${$key:-}"

  if [ -z "$value" ] && [ -f .env ]; then
    line="$(grep -E "^${key}=" .env | tail -n 1 || true)"
    value="${line#*=}"
  fi

  case "$value" in
    \"*\")
      value="${value#\"}"
      value="${value%\"}"
      ;;
    \'*\')
      value="${value#\'}"
      value="${value%\'}"
      ;;
  esac

  printf '%s' "$value"
}

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
  echo "[bootstrap] Created .env from .env.example."
fi

if [ ! -f .env ]; then
  echo "[bootstrap] Missing .env and .env.example."
  exit 1
fi

if [ ! -d vendor ]; then
  echo "[bootstrap] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --no-progress
else
  echo "[bootstrap] vendor/ exists, skipping Composer install."
fi

mkdir -p storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

app_key="$(read_env_value APP_KEY)"
if [ -z "$app_key" ]; then
  echo "[bootstrap] APP_KEY missing, generating..."
  php artisan key:generate --ansi
else
  echo "[bootstrap] APP_KEY exists, skipping key:generate."
fi

php artisan optimize:clear

if [ -L public/storage ]; then
  echo "[bootstrap] public/storage symlink exists, skipping storage:link."
elif [ -e public/storage ]; then
  echo "[bootstrap] public/storage exists and is not a symlink, skipping storage:link."
else
  php artisan storage:link
fi

run_migrations="$(read_env_value RUN_MIGRATIONS)"
run_migrations="$(printf '%s' "${run_migrations:-false}" | tr '[:upper:]' '[:lower:]')"
if [ "$run_migrations" = "true" ] || [ "$run_migrations" = "1" ] || [ "$run_migrations" = "yes" ]; then
  max_tries="$(read_env_value DB_WAIT_MAX_TRIES)"
  sleep_seconds="$(read_env_value DB_WAIT_SLEEP_SECONDS)"
  tries=0

  [ -n "$max_tries" ] || max_tries=30
  [ -n "$sleep_seconds" ] || sleep_seconds=2

  db_host="$(read_env_value DB_HOST)"
  db_port="$(read_env_value DB_PORT)"
  db_name="$(read_env_value DB_DATABASE)"
  db_user="$(read_env_value DB_USERNAME)"
  db_password="$(read_env_value DB_PASSWORD)"

  [ -n "$db_host" ] || db_host="db"
  [ -n "$db_port" ] || db_port="3306"

  echo "[bootstrap] RUN_MIGRATIONS enabled. Waiting for DB before migrate --seed..."
  until DB_HOST="$db_host" DB_PORT="$db_port" DB_DATABASE="$db_name" DB_USERNAME="$db_user" DB_PASSWORD="$db_password" php -r 'try { $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "db", getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE") ?: ""); new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: ""); exit(0); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }'; do
    tries=$((tries + 1))
    if [ "$tries" -ge "$max_tries" ]; then
      echo "[bootstrap] Database not ready after $max_tries attempts."
      exit 1
    fi
    sleep "$sleep_seconds"
  done

  php artisan migrate --seed
else
  echo "[bootstrap] RUN_MIGRATIONS not enabled; skipping migrate --seed."
fi

exec "$@"