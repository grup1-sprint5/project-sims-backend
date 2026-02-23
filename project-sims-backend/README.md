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

## Migrations
### Refresh migrations
This will delete all the DB and exec all the migrations again:
```bash
docker compose exec app php artisan migrate:refresh --seed;
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
