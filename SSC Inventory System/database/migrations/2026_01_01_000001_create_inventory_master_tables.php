<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140)->unique();
            $table->string('building', 120)->nullable();
            $table->string('room_number', 50)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 60)->unique();
            $table->unsignedTinyInteger('severity_rank')->default(1);
            $table->string('badge_color', 30)->default('emerald');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('contact_person', 120)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('tin_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_conditions');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_categories');
    }
};
