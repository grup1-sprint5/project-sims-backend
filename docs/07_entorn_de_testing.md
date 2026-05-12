# 7) Entorn de testing

## Requisits previs

- Docker i docker-compose alçats (`docker compose up -d`)
- PostgreSQL accessible al port `5433` de la màquina host (mapejat des del contenidor `db`)

---

## Configuració inicial (una sola vegada)

### 1. Crear el fitxer `.env.testing`

```bash
cp .env.testing.example .env.testing
```

Edita `.env.testing` i configura:

| Variable | Des de la màquina host | Des de dins del contenidor |
|---|---|---|
| `DB_HOST` | `localhost` | `db` |
| `DB_PORT` | `5433` | `5432` |
| `DB_PASSWORD` | la contrasenya de `project_user` | igual |

### 2. Generar la clau de l'aplicació

```bash
php artisan key:generate --env=testing
```

### 3. Crear la base de dades de test

```bash
psql -h localhost -p 5433 -U project_user -d postgres -c "CREATE DATABASE sims_test;"
```

O des de dins del contenidor:

```bash
docker compose exec app php artisan key:generate --env=testing
docker compose exec db psql -U project_user -d postgres -c "CREATE DATABASE sims_test;"
```

### 4. Executar les migracions sobre la BD de test

```bash
php artisan migrate --env=testing
```

O des del contenidor:

```bash
docker compose exec app php artisan migrate --env=testing
```

---

## Executar els tests

### Tots els tests

```bash
php artisan test --env=testing
```

### Només tests unitaris

```bash
php artisan test --testsuite=Unit --env=testing
```

### Només tests de feature

```bash
php artisan test --testsuite=Feature --env=testing
```

### Un fitxer concret

```bash
php artisan test --env=testing tests/Feature/MultiTenancy/TenantScopeTest.php
```

---

## Credencials de prova

Utilitza el camp `organization` al login (`/central/login`).

### Tenant `sims-corp`
| Email | Contrasenya | Rol |
|---|---|---|
| `client@test.com` | `password` | Client |
| `maria@simscorp.com` | `password` | Tenant Admin |
| `carlos@simscorp.com` | `password` | Tenant Worker |

---

## Notes importants

- La BD de test (`sims_test`) és completament independent de la BD de desenvolupament. Mai s'esborren dades reals.
- Els tests utilitzen transaccions que es reverteixen automàticament després de cada test (no cal buidar la BD manualment).
- `CACHE_STORE=array` i `QUEUE_CONNECTION=sync` estan configurats al `.env.testing` perquè els tests no depenguin de Redis ni de workers externs.
- Si afegeixes un test nou, assegura't d'usar el trait `RefreshDatabase` o `DatabaseTransactions` per aïllar l'estat.
