# 1) Resum executiu i context

## Què és aquest projecte

Aquest projecte és el backend d’una plataforma de mobilitat compartida per empreses (multi-tenant).

En paraules simples: una mateixa aplicació dona servei a diverses empreses, però cada empresa només veu les seues dades.

## Problema que resol

Abans d’una arquitectura d’aquest tipus, és habitual que hi haja:

- Dades barrejades entre empreses.
- Dificultat per escalar a més clients/organitzacions.
- Confusions de permisos (què pot fer admin vs client).
- Fluxos inestables en reserves, vehicles i incidències.

El projecte resol aquests punts amb separació per tenant, autenticació robusta i permisos per rol.

## Objectiu de negoci

- Permetre gestionar flotes de vehicles per empresa.
- Donar autonomia als administradors de cada empresa.
- Permetre als clients reservar vehicles i gestionar el seu ús.
- Mantindre un model de seguretat i traçabilitat coherent.

## Valor aportat

- **Seguretat de dades:** cada empresa té dades aïllades.
- **Escalabilitat:** es poden afegir nous tenants sense redissenyar tot.
- **Control operatiu:** hi ha mòduls de vehicles, reserves, usuaris i tiquets.
- **Experiència d’ús millorada:** s’han corregit casos crítics de permisos i autenticació.

## Resultat global del sistema

El backend està pensat per a entorns reals de producció i inclou:

- login central i login per tenant,
- API multi-tenant,
- permisos per rols,
- mapa de vehicles amb ubicació,
- gestió del cicle complet de reserva,
- i processos automàtics (scheduler).
