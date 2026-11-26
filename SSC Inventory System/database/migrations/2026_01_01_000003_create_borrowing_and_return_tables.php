<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 40)->unique();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->string('borrower_name', 150)->index();
            $table->string('school_id', 60)->index();
            $table->string('department_organization', 150);
            $table->text('purpose');
            $table->date('request_date');
            $table->date('date_borrowed');
            $table->date('expected_return_date')->index();
            $table->date('actual_return_date')->nullable();
            $table->timestamp('return_requested_at')->nullable();
            $table->text('return_request_notes')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('upcoming_notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('borrowing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_request_id')->constrained('borrowing_requests')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->unsignedInteger('quantity_damaged_on_return')->default(0);
            $table->unsignedInteger('quantity_missing_on_return')->default(0);
            $table->foreignId('condition_on_borrow_id')->nullable()->constrained('inventory_conditions')->nullOnDelete();
            $table->string('status', 40)->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('return_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('return_code', 40)->unique();
            $table->foreignId('borrowing_request_id')->constrained('borrowing_requests')->cascadeOnDelete();
            $table->foreignId('borrowing_item_id')->constrained('borrowing_items')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('quantity_returned');
            $table->date('actual_return_date');
            $table->foreignId('condition_on_return_id')->constrained('inventory_conditions')->restrictOnDelete();
            $table->boolean('is_damaged')->default(false);
            $table->boolean('is_missing')->default(false);
            $table->boolean('flagged_for_inspection')->default(false)->index();
            $table->string('inspection_status', 40)->nullable()->index();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->text('inspection_remarks')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_transactions');
        Schema::dropIfExists('borrowing_items');
        Schema::dropIfExists('borrowing_requests');
    }
};
