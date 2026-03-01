# Laravel Backend Runbook

## Tests (Docker + MySQL)

Run Auth feature tests inside the PHP container so PHPUnit uses the MySQL/MariaDB test connection:

```bash
docker compose exec app php artisan test --filter=AuthTest
```

## Reset DB + Seed (Docker + MySQL)

```bash
docker compose exec app php artisan migrate:fresh --seed
```
