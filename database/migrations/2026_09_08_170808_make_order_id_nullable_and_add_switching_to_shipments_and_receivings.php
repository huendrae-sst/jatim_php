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
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('switching_stock_id')->nullable()->after('order_id')->constrained('switching_stocks')->nullOnDelete();
        });

        Schema::table('switching_stocks', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('destination_warehouse_id')->constrained('shipments')->nullOnDelete();
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('switching_stock_id')->nullable()->after('order_id')->constrained('switching_stocks')->nullOnDelete();
        });

        Schema::table('discrepancies', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->change();
            $table->foreignId('switching_stock_item_id')->nullable()->after('order_item_id')->constrained('switching_stock_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discrepancies', function (Blueprint $table) {
            $table->dropForeign(['switching_stock_item_id']);
            $table->dropColumn('switching_stock_item_id');
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->dropForeign(['switching_stock_id']);
            $table->dropColumn('switching_stock_id');
        });

        Schema::table('switching_stocks', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn('shipment_id');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['switching_stock_id']);
            $table->dropColumn('switching_stock_id');
        });
    }
};
