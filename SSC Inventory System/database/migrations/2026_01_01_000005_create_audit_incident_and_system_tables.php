<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_code', 40)->unique();
            $table->string('title', 180);
            $table->date('audit_date')->index();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('conducted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained('inventory_audits')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('system_quantity');
            $table->unsignedInteger('physical_quantity')->nullable();
            $table->integer('discrepancy')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->foreignId('observed_condition_id')->nullable()->constrained('inventory_conditions')->nullOnDelete();
            $table->boolean('adjustment_applied')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['inventory_audit_id', 'inventory_item_id'], 'audit_items_audit_item_unique');
        });

        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code', 40)->unique();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 30)->default('equipment')->index();
            $table->string('incident_type', 40)->index();
            $table->unsignedInteger('affected_quantity')->default(1);
            $table->text('description');
            $table->string('status', 40)->default('reported')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->string('title', 180);
            $table->text('message');
            $table->string('severity', 20)->default('info');
            $table->string('action_url')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 150);
            $table->string('action', 80)->index();
            $table->string('module', 60)->index();
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('record_label', 180)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('incident_reports');
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
    }
};
