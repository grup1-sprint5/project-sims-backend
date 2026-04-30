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

## 4.3 Geofencing (nou)

### Endpoints

- `POST /api/geofences`
- `GET /api/geofences?active=&name=&assign_type=&assign_id=`
- `GET /api/geofences/{id}`
- `PATCH /api/geofences/{id}`
- `DELETE /api/geofences/{id}`
- `POST /api/geofences/{id}/assignments`
- `DELETE /api/geofences/{id}/assignments/{assignmentId}`
- `GET /api/geofence-events?vehicle_id=&geofence_id=&from=&to=&event_type=`
- `POST /api/vehicle-positions` (ingesta GPS, amb `throttle:120,1`)

### Seguretat i aïllament tenant

- El `tenant_id` sempre es força des del context autenticat.
- Cap endpoint de geofencing accepta `tenant_id` des del `body`.
- Totes les consultes de lectura/escriptura filtren per `tenant_id` (excepte `SuperAdmin`).
- Si un usuari prova d’accedir un recurs d’un altre tenant, rep `404`/`403`.

### Exemple request: crear geofence polígon

```json
{
	"name": "Zona Port",
	"type": "polygon",
	"polygon": [
		[2.1700, 41.3800],
		[2.1800, 41.3800],
		[2.1800, 41.3900],
		[2.1700, 41.3900],
		[2.1700, 41.3800]
	],
	"rule_type": "allow",
	"active": true,
	"hysteresis_m": 15,
	"schedule": {
		"timezone": "Europe/Madrid",
		"days": [1, 2, 3, 4, 5],
		"start": "08:00",
		"end": "20:00"
	}
}
```

### Exemple request: crear geofence cercle

```json
{
	"name": "No Parking Centre",
	"type": "circle",
	"center": { "lat": 41.3851, "lng": 2.1734 },
	"radius_m": 150,
	"rule_type": "forbid",
	"active": true
}
```

### Exemple request: assignació

```json
{
	"assign_type": "vehicle",
	"assign_id": "12"
}
```

### Exemple request: ingesta de posició

```json
{
	"vehicle_id": 12,
	"lat": 41.38512,
	"lng": 2.17350,
	"timestamp": "2026-04-23T16:20:00Z",
	"metadata": {
		"source": "gps-device",
		"accuracy_m": 8
	}
}
```

### Exemple response: events generats

```json
{
	"message": "Position processed successfully.",
	"data": {
		"events_count": 2,
		"events": [
			{
				"id": 101,
				"geofence_id": "ad4af9bf-4f7a-4ea8-bf80-7e31862072e8",
				"vehicle_id": 12,
				"event_type": "enter",
				"position": { "lat": 41.38512, "lng": 2.17350 },
				"occurred_at": "2026-04-23T16:20:00.000000Z",
				"metadata": { "source": "gps-device", "accuracy_m": 8 }
			},
			{
				"id": 102,
				"geofence_id": "ad4af9bf-4f7a-4ea8-bf80-7e31862072e8",
				"vehicle_id": 12,
				"event_type": "violation",
				"position": { "lat": 41.38512, "lng": 2.17350 },
				"occurred_at": "2026-04-23T16:20:00.000000Z",
				"metadata": { "source": "gps-device", "accuracy_m": 8 }
			}
		]
	}
}
```
