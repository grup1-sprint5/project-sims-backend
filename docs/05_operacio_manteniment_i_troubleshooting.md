# 5) Operació, manteniment i troubleshooting

## Arrencada local (resum)

1. Configurar `.env`
2. Alçar contenidors Docker
3. Instal·lar dependències PHP
4. Migrar base central
5. Migrar bases tenant
6. Seed de dades demo

(Comandes detallades disponibles a `README.md`.)

## Operació diària

- Login amb tenant correcte.
- Comprovar que `X-Tenant` i token coincideixen.
- Validar que cada mòdul treballa sobre les dades del tenant actiu.

## Scheduler

La comanda `reservations:cancel-expired` s’executa cada minut.

Això evita reserves penjades i allibera vehicles automàticament quan expira el termini d’activació.

## Diagnòstic ràpid d’errors comuns

## Error: "Tenant not identified"

Causes típiques:

- falta capçalera `X-Tenant`,
- domini no registrat a `domains`,
- tenant incorrecte/inactiu.

## Error: `401 Unauthenticated`

Causes típiques:

- token no enviat,
- token expirat o invàlid,
- token d’un tenant usat en un altre tenant.

## Error: `403 Unauthorized`

Causes típiques:

- usuari autenticat però sense permís en aquell recurs,
- política de rol/tenant bloqueja l’operació.

## Vehicle no apareix al mapa

Checklist:

- vehicle creat correctament a SQL,
- coordenades enviades (`latitude/longitude` o `lat/lng/lon`),
- ubicació guardada a Mongo `vehicle_locations` amb `tenant_id` correcte,
- consulta de mapa feta al tenant correcte.

## Bones pràctiques operatives

- Mantindre seeders i permisos coherents.
- Testejar fluxos admin i client després de canvis de policies.
- Distingir sempre errors 401 vs 403 al front.
- Documentar canvis d’autenticació i tenancy abans de release.
