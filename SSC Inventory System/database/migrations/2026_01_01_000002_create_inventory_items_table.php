<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('property_code', 60)->unique();
            $table->string('name', 180)->index();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('brand_model', 150)->nullable();
            $table->text('serial_number')->nullable();
            $table->text('sensitive_notes')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('minimum_stock_level')->default(2);
            $table->string('unit_of_measurement', 50)->default('piece');
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 150)->nullable();
            $table->string('status', 40)->default('available')->index();
            $table->foreignId('condition_id')->constrained('inventory_conditions')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('image_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
            $table->index(['available_quantity', 'minimum_stock_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
