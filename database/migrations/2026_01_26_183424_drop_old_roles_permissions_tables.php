<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        // Drop role_permissions table first (has foreign keys)
        Schema::dropIfExists('role_permissions');
        
        // Drop permissions table
        Schema::dropIfExists('permissions');
        
        // Drop roles table
        Schema::dropIfExists('roles');
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate roles table
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->timestamps();
            });
        }

        // Recreate permissions table
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // Recreate role_permissions table
        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
                $table->timestamps();
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->unique(['role_id','permission_id']);
            });
        }
    }
};
