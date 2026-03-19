# Backend

## Local setup (Docker + Tenancy)

1) Copy env file

```bash
cp .env.example .env
```

2) Start containers

```bash
docker compose up -d --build
```

3) Install PHP dependencies

```bash
docker compose exec app composer install --no-interaction
```

4) Generate app key

```bash
docker compose exec app php artisan key:generate --force
```

5) Run central migrations

```bash
docker compose exec app php artisan migrate --force
```

6) Run tenant migrations (users, vehicles, tickets, reservations, ...)

```bash
docker compose exec app php artisan tenants:migrate --no-interaction
```

7) Seed tenant data (required for demo data in map/bookings/tickets)

```bash
docker compose exec app php artisan tenants:seed --class="Database\\Seeders\\DatabaseSeeder" --force --no-interaction
```

8) Optional check: counts by tenant

```bash
docker compose exec app php artisan tinker --execute='foreach (App\\Models\\Tenant::all() as $t) { tenancy()->initialize($t); dump($t->id, ["vehicles"=>App\\Models\\Vehicle::count(), "tickets"=>App\\Models\\Ticket::count(), "reservations"=>App\\Models\\Reservation::count()]); } tenancy()->end();'
```

## Quick start after pull from `develop`

If after pulling `develop` you see no data, run:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan tenants:migrate --no-interaction
docker compose exec app php artisan tenants:seed --class="Database\\Seeders\\DatabaseSeeder" --force --no-interaction
```

## Login test credentials

Use `organization` on login (`/central/login` flow):

### SIMS (`organization: sims-corp`)
- `client@test.com` / `password`
- `maria@simscorp.com` / `password`
- `carlos@simscorp.com` / `password`

### EcoMove (`organization: ecomove`)
- `laura@ecomove.es` / `password`
- `jorge@ecomove.es` / `password`
- `ana@ecomove.es` / `password` (inactive user, expected to fail)

## Current multi-tenant behavior (expected)

- Data is isolated per company (`tenant`): users, vehicles, reservations, tickets.
- `sims-corp` and `ecomove` have separate fleets and bookings.
- Vehicle map locations are tenant-scoped in Mongo (`tenant_id`).
- `ecomove` demo fleet is 4 vehicles (La Ràpita area).
- `sims-corp` demo fleet is 5 vehicles (Amposta area).

## Scheduler (Cancel·lació automàtica de reserves)

The backend includes a scheduler that auto-cancels pending reservations after `activation_deadline`.

Production cron:

```bash
* * * * * cd /path-to-project && docker compose exec app php artisan schedule:run >> /dev/null 2>&1
```

Local development:

```bash
docker compose exec app php artisan schedule:work
```

## Full reset (danger: deletes tenant data)

```bash
docker compose exec app php artisan migrate:fresh --force
docker compose exec app php artisan db:seed --class="Database\\Seeders\\TenantsSeeder" --no-interaction
docker compose exec app php artisan tenants:migrate --no-interaction
docker compose exec app php artisan tenants:seed --class="Database\\Seeders\\DatabaseSeeder" --force --no-interaction
```

## Troubleshooting

### 422 Incorrect credentials on `/api/login`

- Ensure correct `organization` for the user (`sims-corp` vs `ecomove`).
- Clear browser cookies (`token`, `tenant`) and retry.
- If testing with custom host + header, send correct `X-Tenant`.

### 401 on `/api/user`

- Token expired/invalid or tenant mismatch.
- Login again and verify `X-Tenant` matches the intended organization.

## FOR TEST ONLY: local domains on Windows

If you test subdomains locally, edit hosts file as Administrator:

1. Open `C:\Windows\System32\drivers\etc\hosts`
2. Add entries:

```text
127.0.0.1 sims-corp.localhost
127.0.0.1 ecomove.localhost
```

Remove entries when no longer needed.
