<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('production_number')->unique(); // e.g. PROD/202609/0001
            $table->foreignId('emboss_file_id')->nullable()->constrained('emboss_files')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->enum('status', ['PLANNED', 'ISSUED', 'IN_PRODUCTION', 'COMPLETED', 'CANCELLED'])->default('PLANNED');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_planned')->default(1);
            $table->integer('qty_issued')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
    }
};
