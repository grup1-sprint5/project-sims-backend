<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the unused domains table (domain-based tenant identification was removed).
     */
    public function up(): void
    {
        Schema::dropIfExists('domains');
    }

    /**
     * Recreate the domains table for rollback only.
     */
    public function down(): void
    {
        Schema::create('domains', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            $table->string('tenant_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');
        });
    }
};
