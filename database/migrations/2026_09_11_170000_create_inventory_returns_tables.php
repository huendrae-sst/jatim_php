<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique(); // e.g. RET/202609/0001
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('origin_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('status', ['REQUESTED', 'APPROVED', 'REJECTED', 'SHIPPED', 'RECEIVED', 'CANCELLED'])->default('REQUESTED');
            $table->string('reason'); // DAMAGED_ON_ARRIVAL, DEFECTIVE, WRONG_SPECIFICATION, EXCESS_STOCK, OTHER
            $table->text('reason_details')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_return_id')->constrained('inventory_returns')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_returned')->default(1);
            $table->integer('qty_received_good')->default(0);
            $table->integer('qty_received_damaged')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('condition')->default('DAMAGED'); // DAMAGED, DEFECTIVE, GOOD
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_return_items');
        Schema::dropIfExists('inventory_returns');
    }
};
