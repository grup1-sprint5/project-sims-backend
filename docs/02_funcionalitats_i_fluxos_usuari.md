# 2) Funcionalitats i fluxos d’usuari

## Rols principals

El sistema treballa principalment amb aquests rols:

- **SuperAdmin:** visió global del sistema (operacions cross-tenant quan activa mode global).
- **TenantAdmin:** administra recursos de la seua empresa.
- **TenantWorker:** gestió operativa de vehicles, tickets i reserves dins del tenant.
- **Client:** reserva vehicles i gestiona els seus tickets/reserves.

## Mòduls funcionals

## 2.1 Usuaris

Permet:

- llistar usuaris,
- crear usuaris,
- editar-los,
- eliminar/restaurar (segons permisos).

El comportament depén del rol i del tenant actiu.

## 2.2 Rols i permisos

Permet:

- consultar rols,
- crear rols nous,
- assignar permisos a rols,
- editar/eliminar rols no protegits.

Hi ha rols protegits del sistema (`SuperAdmin`, `TenantAdmin`, `TenantWorker`, `Client`).

## 2.3 Vehicles

Permet:

- crear, llistar, editar i eliminar vehicles,
- consultar mapa de vehicles per a client (`/vehicles-map`) i per a admin (`/vehicles-map-admin`),
- guardar ubicació de vehicle en crear/editar (`latitude/longitude` o `lat/lng/lon`).

La ubicació es guarda a Mongo i es mostra al mapa.

## 2.4 Reserves

Flux funcional principal:

1. El client crea una reserva (`pending`).
2. Pot activar-la dins la finestra de temps (`activation_deadline`) → passa a `active`.
3. Pot finalitzar-la (`completed`) amb càlcul de cost.
4. Pot cancel·lar-la si encara està `pending`.

Existeix també una part d’admin per:

- llistar totes les reserves del tenant,
- editar/eliminar reserves,
- fer `force-finish` en casos especials.

## 2.5 Tiquets (incidències)

Permet:

- crear incidències,
- respondre amb missatges,
- editar/eliminar segons permisos.

Els clients normalment veuen les seues incidències; admin/gestors poden tindre visió ampliada.

## Fluxos destacats (resum)

## Flux A: Login i sessió

- Login central (`/central/login`) amb `email/password` i `organization` opcional.
- Si el compte existeix en diversos tenants, la API retorna `organization_required`.
- En login correcte retorna token final + `tenant_id` (sense exchange token ni callback).
- Les peticions autenticades usen `X-Tenant` per fixar context tenant.

## Flux B: Crear vehicle amb ubicació

- Admin envia dades del vehicle + coordenades.
- Es crea vehicle en SQL.
- Es guarda ubicació en Mongo (`vehicle_locations`).
- El vehicle apareix al mapa.

## Flux C: Reserva completa

- Crear reserva (validació d’overlap i horari).
- Activar reserva.
- Finalitzar reserva (càlcul de minuts i cost).
- Si no s’activa a temps: scheduler la marca `expired`.
