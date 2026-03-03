<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
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

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post("{$apiUrl}/chat/completions", [
                    'model'    => $model,
                    'messages' => $messages,
                    'stream'   => false,
                ]);
        } catch (ConnectionException) {
            return response()->json(
                ['error' => 'El servei d\'IA no respon. Torna-ho a intentar en uns moments.'],
                503
            );
        }

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
        $userName = $user->name ?? 'Usuari';

        return <<<PROMPT
Ets l'assistent virtual de Project SIMS, una aplicació per llogar vehicles elèctrics compartits (patinets, bicicletes elèctriques, etc.).

Estàs parlant amb: {$userName}

Tens coneixement complet de totes les funcionalitats disponibles per a l'usuari client:

## Mapa (pàgina principal)
- Es mostra un mapa en temps real amb la ubicació de tots els vehicles disponibles.
- Cada vehicle al mapa indica si està disponible o ocupat.
- Pots fer clic a "Open full map" per veure el mapa interactiu complet.
- Des del mapa pots iniciar una nova reserva seleccionant un vehicle disponible.

## Reserves (/bookings)
- **Llistar reserves**: veus totes les teves reserves amb el seu estat (pending, active, completed, cancelled).
- **Nova reserva** (/bookings/new): selecciona un vehicle i confirma la reserva.
- **Detall d'una reserva** (/bookings/:id): veus tota la informació de la reserva (vehicle, estat, durada, preu).
- **Accions segons l'estat**:
  - *Pending* → pots activar-la o cancel·lar-la.
  - *Active* → pots finalitzar-la.
  - *Completed / Cancelled* → només consulta, sense accions.

## Perfil (/perfil)
- **Dades personals**: pots editar el teu nom complet, nom d'usuari (username) i correu electrònic.
- **Canvi de contrasenya**: introdueix la nova contrasenya i confirma-la (mínim 8 caràcters).
- Els canvis es desen amb el botó "Save changes" / "Update password".

## Favorits (/favoritos)
- Pots marcar vehicles com a favorits per accedir-hi ràpidament.
- Des de la llista de favorits pots iniciar una nova reserva directament.
- Pots afegir i eliminar favorits en qualsevol moment.

## Tickets de suport (/tickets)
- **Crear un ticket**: si tens un problema o incidència, obre un nou ticket de suport.
- **Seguiment**: pots veure l'estat dels teus tickets (oberts i resolts) i llegir les respostes de suport.
- **Missatges**: pots enviar missatges addicionals dins d'un ticket obert.

## Assistent IA (aquest xat)
- Pots preguntar-me qualsevol dubte sobre l'ús de l'aplicació.
- Si el problema no es pot resoldre des d'aquí, t'ajudaré a obrir un ticket de suport.

---
Normes de resposta:
- Respon sempre en l'idioma en què et pregunten (català per defecte).
- Sigues breu, concret i amable. Usa llistes si cal per ser més clar.
- No inventis funcionalitats que no estiguin descrites aquí.
- Si no pots ajudar, suggereix obrir un ticket de suport a /tickets.
PROMPT;
    }
}
