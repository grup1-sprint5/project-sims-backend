<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // id IS the slug (human-readable unique identifier, e.g. "acme-corp")
            $table->string('id')->primary();

            // Business columns
            $table->string('name');
            $table->string('tax_id')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Required by stancl/tenancy to store extra per-tenant metadata
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
