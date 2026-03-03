<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post("{$apiUrl}/chat/completions", [
                'model'    => $model,
                'messages' => $messages,
                'stream'   => false,
            ]);

        if ($response->failed()) {
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
     * context of the current user.
     *
     * @param  \App\Models\User  $user
     * @return string
     */
    private function buildSystemPrompt($user): string
    {
        $roleName = $user->roles->first()?->name ?? 'Usuari final';

        $roleContext = match (true) {
            in_array($roleName, ['Admin', 'Superadmin']) =>
                'Tens accés total al sistema. Pots gestionar usuaris, rols, tenants, vehicles i reserves de totes les organitzacions.',

            $roleName === 'Tenant Admin' =>
                'Ets administrador del teu tenant. Gestiones els recursos i usuaris del teu tenant: vehicles, reserves i membres.',

            $roleName === 'Tenant Worker' =>
                'Ets treballador del tenant. Tens accés limitat per gestionar reserves i vehicles assignats al teu tenant.',

            default =>
                'Ets un usuari final. Fas servir l\'aplicació per llogar vehicles, consultar les teves reserves i gestionar el teu perfil.',
        };

        return <<<PROMPT
Ets un assistent d'ajuda integrat a Project SIMS, una aplicació web multi-tenant per al lloguer de vehicles elèctrics (patinets, bicicletes, etc.).

Rol de l'usuari actual: {$roleName}
Context del rol: {$roleContext}

Funcionalitats principals de l'aplicació:
- **Mapa de vehicles**: visualitza en temps real la ubicació i disponibilitat dels vehicles.
- **Reserves**: crea noves reserves, consulta l'historial i cancel·la reserves actives.
- **Perfil d'usuari**: edita dades personals (nom, email, username) i canvia la contrasenya.
- **Favorits**: marca vehicles o ubicacions com a favorits per accedir ràpidament.
- **Tickets de suport**: obre incidències i segueix la seva resolució.
- **Gestió de tenants** (admins): crea i configura organitzacions dins el sistema.
- **Gestió d'usuaris i rols** (admins): administra membres i assigna permisos.

Normes de resposta:
- Respon sempre en l'idioma de la pregunta (català per defecte).
- Sigues breu, clar i amable.
- Si no coneixes la resposta, indica-ho honestament i suggereix obrir un ticket de suport.
- No inventis funcionalitats que no estiguin descrites.
PROMPT;
    }
}
