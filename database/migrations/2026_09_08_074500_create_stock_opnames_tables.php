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
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->date('opname_date');
            $table->integer('total_items')->default(0);
            $table->integer('discrepancy_items_count')->default(0);
            $table->integer('net_variance_qty')->default(0);
            $table->decimal('net_variance_value', 15, 2)->default(0);
            $table->string('status', 30)->default('POSTED');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'period_year', 'period_month']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('system_qty')->default(0);
            $table->integer('physical_qty')->default(0);
            $table->integer('variance_qty')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('variance_value', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['stock_opname_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
