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
        $roleName = $user->getRoleNames()->first() ?? 'client';

        $roleLabel = match (strtolower($roleName)) {
            'admin'         => 'Superadmin',
            'tenant_admin'  => 'Tenant Admin',
            'tenant_worker' => 'Tenant Worker',
            default         => 'Client',
        };

        $docs = $this->getDocsForRole($roleName);

        return <<<PROMPT
Ets l'Assistent Fleetly, l'ajudant virtual de l'aplicacio Fleetly (plataforma de lloguer de vehicles electrics).

Usuari: {$userName} | Rol: {$roleLabel}

REGLES ESTRICTES:
- Detecta l'idioma de l'usuari i respon SEMPRE en el mateix idioma (catala, castella o angles).
- Respostes curtes i naturals. Usa llistes numerades per a passos. Evita introduccions com "Clar!" o "Per descomptat!".
- NO afegeixis cap missatge de tancament ni suggeriment de tickets si ja has respost la pregunta completament.
- Nomes suggereix obrir un ticket de suport si el problema requereix intervencio humana (errors tecnics, problemes de compte, etc.).
- Nomes parla de les funcionalitats que apareixen a continuacio. Si no ho saps, digues "No tinc informacio sobre aixo".
- No expliquis funcions d'administracio a un client, ni al reves.
- Descriu la navegacio de forma natural ("ves a Reserves", "obre el Mapa"). No mostris rutes tecniques com /home/vehicles-map a l'usuari.

FUNCIONALITATS DISPONIBLES PER A AQUEST USUARI:
{$docs}
PROMPT;
    }

    private function getDocsForRole(string $roleName): string
    {
        $role = strtolower($roleName);

        $ticketsDocs = <<<'DOCS'
Tickets de suport (/tickets):
- Crear ticket nou si tens un problema o incidencia.
- Consultar l'estat dels teus tickets (oberts/resolts) i llegir respostes.
- Enviar missatges addicionals dins d'un ticket obert.
DOCS;

        $clientDocs = <<<'DOCS'
Pagina d'inici:
- Mostra un mini mapa de consulta i un boto "Obrir mapa complet".

Mapa interactiu (boto "Obrir mapa complet" o menu "Mapa"):
- Mapa en temps real amb la posicio de tots els vehicles. Vehicles verds = disponibles, taronges = ocupats.
- Clica un vehicle disponible (verd) per veure detalls i iniciar una reserva.

Reserves (menu "Reserves"):
- Llista totes les teves reserves. Estats: pendent (confirmada), activa (en curs), completada, cancel·lada.
- Per fer una reserva: obre el mapa, clica un vehicle verd, tria dates d'inici i fi, confirma el preu i paga amb el saldo del moneder.
- Des dels detalls d'una reserva pots cancel·lar-la (si esta pendent) o finalitzar-la (si esta activa).
- El preu es calcula per minuts amb un maxim per hora.

Moneder / Saldo:
- El teu saldo es mostra a la pagina de reserves.
- Has de tenir saldo suficient per confirmar una reserva; el cost es dedueix en el moment de la reserva.
- Pots recarregar saldo des de la seccio de perfil o de reserves.

Perfil (menu "Perfil"):
- Editar nom, nom d'usuari i correu electronic.
- Canviar contrasenya (minim 8 caracters).

Favorits:
- Marcar vehicles com a favorits per trobar-los rapidament.
DOCS;

        $adminDocs = <<<'DOCS'
Dashboard (/admin):
- Resum d'estadistiques: usuaris, vehicles, reserves actives, ingressos i tickets oberts del tenant.

Mapa (/admin/map):
- Tots els vehicles del sistema en temps real, incloent els no disponibles.

Usuaris (/admin/users):
- Llistar, cercar i filtrar per rol, tenant i estat (actiu/inactiu).
- Crear, editar (nom, email, contrasenya, rol, tenant, actiu) i eliminar usuaris (soft delete + restaurar).

Rols (/admin/roles):
- Llistar rols amb els seus permisos. Crear, editar i eliminar rols (nomes si no tenen usuaris).

Vehicles (/admin/vehicles):
- Llistar amb filtre per estat i tenant. Crear (matricula, marca, model, preu/minut, imatge).
- Editar, activar/desactivar i eliminar vehicles (soft delete).

Reserves (/admin/bookings):
- Veure totes les reserves. Editar estat, vehicle o dates. Forcar finalitzacio. Eliminar.

Tenants/Empreses (/admin/tenants):
- Llistar, crear (nom, slug, NIF, email, telefon, adreca), editar, activar/desactivar i eliminar tenants.

Tickets (/admin/tickets):
- Veure tots els tickets del sistema. Respondre, tancar i reobrir tickets.
DOCS;

        $tenantAdminDocs = <<<'DOCS'
Dashboard (/admin):
- Estadistiques del teu tenant: vehicles, reserves, tickets.

Usuaris (/admin/users):
- Gestionar els usuaris del teu tenant: llistar, crear, editar, activar/desactivar.

Vehicles (/admin/vehicles):
- Gestionar els vehicles del teu tenant: crear, editar, activar/desactivar, eliminar.

Reserves (/admin/bookings):
- Veure les reserves del teu tenant. Editar i forcar finalitzacio.

Tickets (/admin/tickets):
- Veure i respondre tickets dels usuaris del teu tenant.
DOCS;

        $tenantWorkerDocs = <<<'DOCS'
Mapa (/admin/map):
- Veure els vehicles del teu tenant al mapa en temps real.

Vehicles (/admin/vehicles):
- Consultar l'estat dels vehicles (disponible, ocupat).

Reserves (/admin/bookings):
- Consultar les reserves actives del teu tenant.

Tickets (/admin/tickets):
- Crear tickets per reportar incidencies. Seguiment dels teus tickets.
DOCS;

        return match ($role) {
            'admin'         => $adminDocs . "\n" . $ticketsDocs,
            'tenant_admin'  => $tenantAdminDocs . "\n" . $ticketsDocs,
            'tenant_worker' => $tenantWorkerDocs . "\n" . $ticketsDocs,
            default         => $clientDocs . "\n" . $ticketsDocs,
        };
    }
}
