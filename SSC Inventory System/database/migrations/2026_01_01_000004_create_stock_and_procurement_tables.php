<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_reference', 50)->unique();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('movement_type', 40)->index();
            $table->integer('quantity');
            $table->unsignedInteger('previous_quantity');
            $table->unsignedInteger('new_quantity');
            $table->unsignedInteger('previous_available_quantity');
            $table->unsignedInteger('new_available_quantity');
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('transaction_date')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('acquisitions', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_reference', 60)->unique();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('item_name', 180)->index();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->unsignedInteger('quantity_acquired');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 14, 2);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 150)->nullable();
            $table->date('purchase_date')->index();
            $table->string('funding_source', 150);
            $table->string('receipt_path')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('synced_to_inventory')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisitions');
        Schema::dropIfExists('stock_movements');
    }
};
