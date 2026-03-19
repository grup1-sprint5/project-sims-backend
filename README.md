# Backend

## Deployment
1) Copy the example and put the real creds on the .env
```bash
cp .env.example .env
```

2) Run container

```bash
docker compose up -d --build
```

3) Start app service

```bash
docker compose run --rm app composer install --no-interaction
```

4) Install dependencies:

```bash
docker compose exec app composer install --no-interaction
```

5) Generate app key:
```bash
docker compose exec app php artisan key:generate --force
```

6) Execute migrations:

```bash
docker compose exec app php artisan migrate --force
```

7) Run tenant migrations (required for business tables like users, vehicles, tickets, reservations):

```bash
docker compose exec app php artisan tenants:migrate --force --no-interaction
```

8) Seed tenant data (required if you want demo data in map/reservations/tickets):

```bash
docker compose exec app php artisan tenants:seed --class="Database\\Seeders\\DatabaseSeeder" --force --no-interaction
```

9) (Optional) Check tenant data counts:

```bash
docker compose exec app php artisan tinker --execute='foreach (App\\Models\\Tenant::all() as $t) { tenancy()->initialize($t); dump($t->id, ["vehicles"=>App\\Models\\Vehicle::count(), "tickets"=>App\\Models\\Ticket::count(), "reservations"=>App\\Models\\Reservation::count()]); } tenancy()->end();'
```

10) (Optional) If you also need central seeders:

```bash
docker compose exec app php artisan db:seed
```

## Quick start after pull from `develop`

If after pulling `develop` you see no vehicles/tickets/reservations, run:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan tenants:migrate --force --no-interaction
docker compose exec app php artisan tenants:seed --class="Database\\Seeders\\DatabaseSeeder" --force --no-interaction
```

Then login with tenant organization + credentials, for example:

- organization: `sims-corp`
- email: `client@test.com`
- password: `password`

## Scheduler (Cancel·lació automàtica de reserves)

El backend inclou un scheduler que cancel·la automàticament les reserves pendents que han superat el `activation_deadline`.

Per executar el scheduler en producció, afegeix aquest cron job al servidor:

```bash
* * * * * cd /path-to-project && docker compose exec app php artisan schedule:run >> /dev/null 2>&1
```

Per desenvolupament local, executa:

```bash
docker compose exec app php artisan schedule:work
```

Això executarà el command `reservations:cancel-expired` cada minut.

## Migrations
### Refresh migrations
This will delete all the DB and exec all the migrations again:
```bash
docker compose exec app php artisan migrate:fresh --force
docker compose exec app php artisan tenants:migrate-fresh --force --no-interaction
docker compose exec app php artisan tenants:seed --class="Database\\Seeders\\DatabaseSeeder" --force --no-interaction
```

## FOR TEST ONLY
### How to create a local domain to use 

On Windows, add an entry to the system `hosts` file so the local domain resolves to the test IP.

1. Open Notepad (or your editor) as Administrator.
2. Open the file `C:\Windows\System32\drivers\etc\hosts`.
3. Add the following line at the end of the file and save:

```text
192.168.1.154 sims.com
```
Note: Administrator privileges are required to edit the `hosts` file. Remove the entry when you no longer need the local domain.
Note 2: As we dont have SSL some browsers may refuse our connection
