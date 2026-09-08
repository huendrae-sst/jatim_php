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
        Schema::table('switching_stocks', function (Blueprint $table) {
            $table->foreignId('transferred_by_user_id')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable()->after('transferred_by_user_id');
            $table->text('transfer_notes')->nullable()->after('transferred_at');
            $table->string('tracking_number')->nullable()->after('transfer_notes');
            $table->foreignId('received_by_user_id')->nullable()->after('tracking_number')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by_user_id');
            $table->text('receipt_notes')->nullable()->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('switching_stocks', function (Blueprint $table) {
            $table->dropForeign(['transferred_by_user_id']);
            $table->dropForeign(['received_by_user_id']);
            $table->dropColumn([
                'transferred_by_user_id',
                'transferred_at',
                'transfer_notes',
                'tracking_number',
                'received_by_user_id',
                'received_at',
                'receipt_notes',
            ]);
        });
    }
};
