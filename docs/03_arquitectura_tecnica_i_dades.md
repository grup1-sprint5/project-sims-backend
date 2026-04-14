# 3) Arquitectura tècnica i model de dades

## Stack principal

- **Backend:** Laravel (PHP)
- **Base de dades relacional:** PostgreSQL
- **Base de dades documental:** MongoDB (ubicacions de vehicles)
- **Tenancy:** `stancl/tenancy` (aïllament per schemas a PostgreSQL)
- **Auth tokens:** Laravel Sanctum (model de token personalitzat)
- **Contenidors:** Docker Compose

## Arquitectura multi-tenant

El sistema està dissenyat amb:

- **Context central** (taules i funcionalitat comuna, ex: `tenants`, `domains`, login central).
- **Context tenant** (dades de negoci: usuaris, vehicles, reserves, tiquets, etc.).

Cada tenant s’identifica pel seu `id` (slug), per exemple:

- `sims-corp`
- `ecomove`

La connexió de tenant usa schema separat a PostgreSQL (`tenant_<id>`), i a més s’inclou `public` al `search_path` per accedir a dades centrals quan cal.

## Inicialització del tenant

La resolució del tenant es fa en aquest ordre:

1. capçalera `X-Tenant` (o alternatives configurades),
2. domini/subdomini definit a la taula `domains`.

Si no es pot identificar tenant, la request falla amb error controlat.

## Components tècnics clau

- **Middleware de tenancy:** inicialitza context abans de resoldre bindings.
- **Middleware d’autenticació:** valida Bearer token i abilitat `tenant:<id>`.
- **Policies:** controlen accés per mòdul i rol.
- **Global behavior de tenant:** els models usen trait `BelongsToTenant` per auto-associar `tenant_id` en creació.

## Dades principals del domini

Entitats principals:

- `Tenant`
- `User`
- `Vehicle`
- `Reservation`
- `Trip`
- `Ticket`
- `TicketMessage`

## SQL + Mongo (responsabilitats)

- **SQL (PostgreSQL):** dades transaccionals i de negoci.
- **MongoDB:** ubicacions de vehicles (`vehicle_locations`) amb `tenant_id`, `license_plate`, `latitude`, `longitude`, `active`.

## Estat de vehicle per mapa

L’estat final del vehicle al mapa combina:

- reserves actives a SQL,
- activitat/posició a Mongo.

Resultat funcional:

- `available`,
- `occupied`,
- `running`.

## Scheduler i automatismes

S’executa periòdicament la comanda `reservations:cancel-expired`.

Objectiu:

- passar reserves `pending` fora de termini a `expired`,
- evitar bloquejos de vehicles per reserves no activades.
