<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_destructions', function (Blueprint $table) {
            $table->id();
            $table->string('destruction_number')->unique(); // e.g. DST/202609/0001
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('status', ['REQUESTED', 'APPROVED', 'REJECTED', 'EXECUTED', 'CANCELLED'])->default('REQUESTED');
            $table->string('reason'); // EXPIRED_CHIP, DAMAGED_UNUSABLE, DISCONTINUED_DESIGN, FAILED_EMBOSS, OTHER
            $table->text('reason_details')->nullable();
            $table->string('berita_acara_number')->unique(); // e.g. BA-DST/202609/0001
            $table->string('witness_name_1');
            $table->string('witness_title_1');
            $table->string('witness_name_2');
            $table->string('witness_title_2');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('execution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_destruction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_destruction_id')->constrained('stock_destructions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_loss_value', 15, 2)->default(0);
            $table->string('batch_or_serial_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_destruction_items');
        Schema::dropIfExists('stock_destructions');
    }
};
