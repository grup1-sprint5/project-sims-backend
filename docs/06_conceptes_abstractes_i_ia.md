# 6) Conceptes abstractes (explicació no tècnica)

Aquest document està pensat per explicar idees del sistema a públic no tècnic.

## Què vol dir “multi-tenant” en paraules simples

És com un edifici d’oficines:

- l’edifici és la plataforma,
- cada oficina és una empresa (tenant),
- comparteixen infraestructures,
- però cada oficina té els seus documents separats.

## Diferència entre “autenticació” i “autorització”

- **Autenticació:** "qui eres?" (login, token).
- **Autorització:** "què pots fer?" (permisos i rols).

Exemple:

- si no tens targeta d’entrada → no entres (401).
- si tens targeta però no pots entrar a una sala concreta → denegat (403).

## Per què és important separar dades per empresa

Per tres motius de negoci:

1. confiança dels clients,
2. compliment i seguretat,
3. facilitat per escalar a més empreses.

## Per què hi ha SQL i Mongo al mateix temps

- SQL és ideal per dades de negoci estructurades (reserves, usuaris, facturació).
- Mongo és útil per dades de posició/telemetria més dinàmiques (ubicacions de vehicles).

No és “duplicar per duplicar”; és usar cada eina on aporta més.

## “Per què una IA té memòria?” (explicació general)

En general, una IA pot “tindre memòria” de dues formes:

1. **Memòria de conversa curta (context):**
   - recorda el que s’ha dit durant una sessió,
   - ajuda a mantindre coherència immediata.

2. **Memòria persistida (llarg termini):**
   - informació guardada fora del model (base de dades, vector store, etc.),
   - es recupera quan cal per personalitzar respostes futures.

Important: moltes IAs no “recorden” per defecte entre sessions si no hi ha un sistema explícit de persistència.

## L’app actual té memòria d’IA?

Amb el codi actual d’aquest backend, **no hi ha un mòdul d’IA conversacional amb memòria pròpia**.

El que sí que hi ha és memòria d’aplicació clàssica (bases de dades) per guardar:

- usuaris,
- vehicles,
- reserves,
- incidències,
- ubicacions.

## Com explicar això en una presentació

Frase útil:

> “La plataforma no és una IA, però sí que aplica els mateixos principis de memòria estructurada: guardar context rellevant i recuperar-lo en el moment adequat.”
