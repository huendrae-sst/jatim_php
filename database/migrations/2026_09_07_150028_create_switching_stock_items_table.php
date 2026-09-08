<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('switching_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('switching_stock_id')->constrained('switching_stocks')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_requested')->default(1);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('switching_stocks', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->change();
            $table->integer('qty_requested')->nullable()->change();
        });

        $existing = DB::table('switching_stocks')->whereNotNull('item_id')->get();
        foreach ($existing as $row) {
            DB::table('switching_stock_items')->insert([
                'switching_stock_id' => $row->id,
                'item_id' => $row->item_id,
                'qty_requested' => $row->qty_requested ?? 1,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switching_stock_items');

        Schema::table('switching_stocks', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable(false)->change();
            $table->integer('qty_requested')->nullable(false)->change();
        });
    }
};
