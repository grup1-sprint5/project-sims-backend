# 4) API, seguretat i permisos

## Estructura de rutes

## 4.1 Rutes centrals (`routes/api.php`)

- `GET /api/health`
- `POST /api/central/login`

Aquestes rutes no depenen d’un tenant concret.

## 4.2 Rutes tenant (`routes/tenant.php`)

Inclouen:

- autenticació tenant (`/login`, `/auth/exchange-token`, `/logout`, `/user`),
- mòduls de negoci (`users`, `roles`, `vehicles`, `tickets`, `reservations`, `tenants`, etc.),
- subrutes admin de reserves (`/api/admin/reservations/...`).

## Model d’autenticació

## Fase 1: Login central (opcional)

- l’usuari indica `organization`, `email`, `password`.
- el sistema valida tenant i credencials.
- retorna un `exchange_token` curt per completar login al domini del tenant.

## Fase 2: Exchange token al tenant

- `POST /api/auth/exchange-token`
- retorna Bearer token amb habilitat `tenant:<id>`.

## Login directe tenant

- `POST /api/login` amb `X-Tenant`.
- retorna token Bearer.

## Validacions de seguretat clau

- Sense Bearer token: `401 Unauthenticated`.
- Token invàlid: `401`.
- Token de tenant diferent al context actual: `401`.
- Usuari sense permís: `403 Unauthorized`.

## Autorització per policies

El sistema usa policies Laravel per mòdul:

- `UserPolicy`
- `RolePolicy`
- `VehiclePolicy`
- `TicketPolicy`
- `ReservationPolicy`
- `TenantPolicy`

Cada policy combina:

- permisos (`*.view`, `*.manage`, `*.delete`),
- rol (`SuperAdmin`, `TenantAdmin`, etc.),
- i coincidència de `tenant_id`.

## Notes pràctiques de permisos

En millores recents, s’ha fet fallback controlat perquè `TenantAdmin` amb permís `manage` puga executar certes accions d’admin on abans es requeria estrictament `delete`, sempre dins del seu tenant.

Això redueix errors operatius habituals sense obrir accés entre empreses.

## Criteri per distingir errors al front

- **401:** problema de sessió/token/context de tenant.
- **403:** sessió vàlida però sense autorització.

Aquesta distinció és important per mostrar missatges correctes a l’usuari.
