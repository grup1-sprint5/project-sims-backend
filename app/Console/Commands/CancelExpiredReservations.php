<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\Carbon;
use Stancl\Tenancy\Facades\Tenancy;

class CancelExpiredReservations extends Command
{
    protected $signature = 'reservations:cancel-expired';
    protected $description = 'Cancel·la reserves pendents que han superat el deadline';

    public function handle()
    {
        $totalExpired = 0;
        $totalActiveHealed = 0;

        $tenants = Tenant::query()
            ->where('active', true)
            ->get(['id']);

        if ($tenants->isEmpty()) {
            $this->info('No hi ha tenants actius.');
            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            Tenancy::initialize($tenant);

            try {
                $now = Carbon::now();
                $expiredReservations = Reservation::pendingAndOverdue($now)
                    ->get();

                $invalidActiveReservations = Reservation::invalidActiveWithoutTrip($now)
                    ->get();

                foreach ($expiredReservations as $reservation) {
                    $reservation->markAsExpired($now);
                    $this->info("[{$tenant->id}] Reserva #{$reservation->id} expirada");
                    $totalExpired++;
                }

                foreach ($invalidActiveReservations as $reservation) {
                    $reservation->markAsExpired($now);
                    $this->info("[{$tenant->id}] Reserva #{$reservation->id} activa inconsistent corregida a expirada");
                    $totalActiveHealed++;
                }
            } finally {
                Tenancy::end();
            }
        }

        if ($totalExpired === 0 && $totalActiveHealed === 0) {
            $this->info('No hi ha reserves expirades ni inconsistents.');
            return Command::SUCCESS;
        }

        $this->info("Total cancel·lades: {$totalExpired}");
        $this->info("Total actives inconsistents corregides: {$totalActiveHealed}");
        return Command::SUCCESS;
    }
}
