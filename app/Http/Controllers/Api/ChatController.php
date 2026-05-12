<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Send a user message to the AI chatbot and return the assistant reply.
     *
     * POST /api/chat
     *
     * Request body:
     *   - message  (string, required) : the user's new message
     *   - history  (array, optional)  : previous turns [{role, content}, ...]
     *
     * Response:
     *   { "reply": "<assistant response>" }
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        // ── Validate ────────────────────────────────────────────────────────
        $validated = $request->validate([
            'message'           => 'required|string|max:2000',
            'history'           => 'nullable|array|max:20',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:4000',
        ]);

        // ── Build messages array ─────────────────────────────────────────────
        $systemPrompt = $this->buildSystemPrompt($request->user());

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach (array_slice($validated['history'] ?? [], -10) as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $validated['message']];

        $apiUrl = rtrim(config('services.ia.url'), '/');
        $apiKey = config('services.ia.key');
        $model  = config('services.ia.model');

        if (empty($apiKey)) {
            Log::error('ChatController: IA_API_KEY is not configured');
            return response()->json(
                ['error' => 'El servei d\'IA no està configurat. Contacta amb l\'administrador.'],
                503
            );
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post("{$apiUrl}/chat/completions", [
                    'model'    => $model,
                    'messages' => $messages,
                    'stream'   => false,
                ]);
        } catch (ConnectionException $e) {
            Log::error('ChatController: connection error', ['error' => $e->getMessage(), 'url' => $apiUrl]);
            return response()->json(
                ['error' => 'El servei d\'IA no respon. Torna-ho a intentar en uns moments.'],
                503
            );
        }

        if ($response->status() === 429) {
            Log::warning('ChatController: AI rate limit hit', ['model' => $model]);
            return response()->json(
                ['error' => 'L\'assistent IA ha rebut moltes peticions. Espera uns segons i torna-ho a intentar.'],
                429
            );
        }

        if ($response->failed()) {
            Log::error('ChatController: AI API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'url'    => $apiUrl,
                'model'  => $model,
            ]);
            return response()->json(
                ['error' => 'El servei d\'IA no està disponible en aquest moment. Torna-ho a intentar més tard.'],
                503
            );
        }

        // ── Parse response ───────────────────────────────────────────────────
        $data  = $response->json();
        $reply = $data['choices'][0]['message']['content']
            ?? 'No he pogut obtenir una resposta. Torna-ho a intentar.';

        return response()->json(['reply' => $reply]);
    }

    /**
     * Build a role-aware system prompt so the assistant understands the
     * context of the current user and their available features.
     *
     * @param  \App\Models\User  $user
     * @return string
     */
    private function buildSystemPrompt($user): string
    {
        $userName = $user->name ?? 'Usuari';
        $roleName = $user->getRoleNames()->first() ?? 'Client';

        $roleLabel = match (strtolower($roleName)) {
            'admin'         => 'Superadmin',
            'tenant_admin'  => 'Tenant Admin',
            'tenant_worker' => 'Tenant Worker',
            default         => 'Usuari final (Client)',
        };

        $base = <<<PROMPT
Ets l'assistent virtual de Project SIMS, una aplicacio per llogar vehicles electrics.

Estas parlant amb: {$userName}
Rol: {$roleLabel}

PROMPT;

        $docs = $this->getDocsForRole($roleName);

        $rules = <<<RULES

---
Normes de resposta:
- Respon sempre en l'idioma en que et pregunten (catala per defecte).
- Sigues breu, concret i amable. Usa llistes si cal per ser mes clar.
- No inventis funcionalitats que no estiguin descrites aqui.
- Si no pots ajudar, suggereix obrir un ticket de suport a /tickets.
- Adapta les teves respostes al rol de l'usuari. No expliquis funcionalitats d'administracio a un client, ni funcionalitats de client a un admin.
RULES;

        return $base . $docs . $rules;
    }

    /**
     * Return the documentation sections relevant to the given role.
     */
    private function getDocsForRole(string $roleName): string
    {
        $role = strtolower($roleName);

        // ── Shared: Tickets & AI assistant ──────────────────────────────────
        $ticketsDocs = <<<'DOCS'
## Tickets de suport (/tickets)
- Crear un ticket: si tens un problema o incidencia, obre un nou ticket de suport.
- Seguiment: pots veure l'estat dels teus tickets (oberts i resolts) i llegir les respostes de suport.
- Missatges: pots enviar missatges addicionals dins d'un ticket obert.

## Assistent IA (aquest xat)
- Pots preguntar qualsevol dubte sobre l'us de l'aplicacio.
- Si el problema no es pot resoldre des d'aqui, obre un ticket de suport a /tickets.
DOCS;

        // ── Client-specific docs ────────────────────────────────────────────
        $clientDocs = <<<'DOCS'
## Mapa (pagina principal)
- Mapa en temps real amb la ubicacio de tots els vehicles disponibles.
- Cada vehicle indica si esta disponible o ocupat.
- Des del mapa pots iniciar una nova reserva seleccionant un vehicle disponible.

## Reserves (/bookings)
- Llistar reserves: veus totes les teves reserves amb estat (pending, active, completed, canceled).
- Nova reserva (/bookings/new): selecciona un vehicle i confirma la reserva.
- Detall d'una reserva (/bookings/:id): informacio completa (vehicle, estat, durada, preu).
- Accions segons l'estat:
  - Pending: pots activar-la o cancel·lar-la.
  - Active: pots finalitzar-la.
  - Completed / Cancelled: nomes consulta.

## Perfil (/perfil)
- Dades personals: editar nom complet, nom d'usuari (username) i correu electronic.
- Canvi de contrasenya: introdueix la nova contrasenya i confirma-la (minim 8 caracters).

## Favorits (/favoritos)
- Marcar vehicles com a favorits per accedir-hi rapidament.
- Des de la llista de favorits pots iniciar una nova reserva directament.
DOCS;

        // ── Admin / management docs ─────────────────────────────────────────
        $adminDocs = <<<'DOCS'
## Dashboard (/admin)
- Vista general amb estadistiques del sistema: total d'usuaris, vehicles, reserves actives, tickets oberts.

## Mapa admin (/admin/map)
- Mapa amb tots els vehicles del sistema, incloent els no disponibles.
- Visualitzacio per a gestio i supervisio.

## Gestio d'Usuaris (/admin/users)
- Llistar tots els usuaris amb cerca, filtre per rol, tenant i estat (actiu/inactiu).
- Crear nous usuaris amb rol i tenant assignats.
- Editar dades d'un usuari (nom, email, username, contrasenya, rol, tenant, estat actiu).
- Eliminar (soft delete) i restaurar usuaris.

## Gestio de Rols (/admin/roles)
- Llistar tots els rols amb els seus permisos.
- Crear i editar rols assignant permisos especifics.
- Eliminar rols (si no tenen usuaris assignats).

## Gestio de Vehicles (/admin/vehicles)
- Llistar vehicles amb filtre per estat i tenant.
- Crear nous vehicles (matricula, marca, model, preu per minut, imatge, tenant).
- Editar i activar/desactivar vehicles.
- Eliminar vehicles (soft delete).

## Gestio de Reserves (/admin/bookings)
- Llistar totes les reserves de tots els usuaris.
- Veure detall de qualsevol reserva.
- Editar reserves (canviar estat, vehicle, dates).
- Forcar finalitzacio d'una reserva activa.
- Eliminar reserves.

## Gestio de Tenants (/admin/tenants)
- Llistar tots els tenants (empreses / organitzacions).
- Crear nous tenants (nom, slug, NIF, email, telefon, adreca).
- Editar dades d'un tenant.
- Activar / desactivar tenants.
- Eliminar tenants (soft delete).

## Gestio de Tickets (/admin/tickets)
- Veure tots els tickets de suport del sistema.
- Respondre a tickets d'usuaris.
- Tancar / reobrir tickets.
DOCS;

        // ── Tenant Admin docs (subset of admin + own tenant scope) ──────────
        $tenantAdminDocs = <<<'DOCS'
## Dashboard (/admin)
- Vista general amb estadistiques del teu tenant: vehicles, reserves, tickets.

## Gestio d'Usuaris (/admin/users)
- Llistar els usuaris del teu tenant.
- Crear nous usuaris dins del teu tenant.
- Editar i activar/desactivar usuaris del teu tenant.

## Gestio de Vehicles (/admin/vehicles)
- Llistar els vehicles del teu tenant.
- Crear nous vehicles per al teu tenant.
- Editar, activar/desactivar i eliminar vehicles.

## Gestio de Reserves (/admin/bookings)
- Veure les reserves associades al teu tenant.
- Editar i forcar finalitzacio de reserves.

## Gestio de Tickets (/admin/tickets)
- Veure els tickets dels usuaris del teu tenant.
- Respondre i gestionar tickets.
DOCS;

        // ── Tenant Worker docs ──────────────────────────────────────────────
        $tenantWorkerDocs = <<<'DOCS'
## Mapa (/admin/map)
- Visualitzar els vehicles del teu tenant al mapa.

## Vehicles (/admin/vehicles)
- Consultar l'estat dels vehicles del teu tenant (disponible, ocupat, manteniment).

## Reserves (/admin/bookings)
- Consultar les reserves actives del teu tenant.
- Reportar incidencies o problemes amb reserves.

## Tickets (/admin/tickets)
- Crear tickets de suport per reportar incidencies amb vehicles.
- Seguiment dels tickets que has creat.
DOCS;

        return match ($role) {
            'admin'         => $adminDocs . "\n" . $ticketsDocs,
            'tenant_admin'  => $tenantAdminDocs . "\n" . $ticketsDocs,
            'tenant_worker' => $tenantWorkerDocs . "\n" . $ticketsDocs,
            default         => $clientDocs . "\n" . $ticketsDocs,
        };
    }
}
