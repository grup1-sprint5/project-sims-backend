<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use Carbon\Carbon;

class CancelExpiredReservations extends Command
{
    protected $signature = 'reservations:cancel-expired';
    protected $description = 'Cancel·la automàticament les reserves pendents que han superat el deadline d\'activació';

    public function handle()
    {
        $now = Carbon::now();
        $expiredReservations = Reservation::where('status', 'pending')
            ->where('activation_deadline', '<', $now)
            ->get();

        if ($expiredReservations->isEmpty()) {
            $this->info('No hi ha reserves expirades.');
            return Command::SUCCESS;
        }

        foreach ($expiredReservations as $reservation) {
            $reservation->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
            ]);
            $this->info("Reserva #{$reservation->id} cancel·lada");
        }

        $this->info("Total cancel·lades: {$expiredReservations->count()}");
        return Command::SUCCESS;
    }
}
