<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class TenantsList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:list {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenants with their status and metadata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $format = $this->option('format');
        $tenants = Tenant::query()
            ->select([
                'id',
                'name',
                'email',
                'active',
                'created_at',
            ])

            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        if ($format === 'json') {
            $this->printJson($tenants);
        } else {
            $this->printTable($tenants);
        }

        return self::SUCCESS;
    }

    /**
     * Print tenants as table.
     */
    private function printTable($tenants): void
    {
        $table = new Table($this->output);
        $table->setHeaders([
            'ID',
            'Name',
            'Email',
            'Status',

            'Created At',
        ]);

        foreach ($tenants as $tenant) {
            $table->addRow([
                $tenant->id,
                $tenant->name,
                $tenant->email,
                $tenant->active ? '✓ Active' : '✗ Inactive',

                $tenant->created_at->format('Y-m-d H:i'),
            ]);
        }

        $table->render();
    }

    /**
     * Print tenants as JSON.
     */
    private function printJson($tenants): void
    {
        $output = $tenants->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'active' => $tenant->active,

                'created_at' => $tenant->created_at->toIso8601String(),
            ];
        });

        $this->line(json_encode($output->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
