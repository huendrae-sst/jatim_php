<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('emboss_file_id')->nullable()->after('budget_id')->constrained('emboss_files')->nullOnDelete();
            $table->string('delivery_method')->default('COURIER')->after('emboss_file_id'); // COURIER, PICKUP_KP
            $table->string('pickup_pic_nip')->nullable()->after('delivery_method'); // (POC-28)
            $table->string('pickup_pic_name')->nullable()->after('pickup_pic_nip');
            $table->string('pickup_pic_position')->nullable()->after('pickup_pic_name');
            $table->text('pickup_notes')->nullable()->after('pickup_pic_position');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('delivery_method')->default('COURIER')->after('service_type'); // COURIER, PICKUP_KP
            $table->string('pickup_pic_nip')->nullable()->after('delivery_method'); // (POC-28)
            $table->string('pickup_pic_name')->nullable()->after('pickup_pic_nip');
            $table->string('pickup_pic_position')->nullable()->after('pickup_pic_name');
            $table->text('pickup_notes')->nullable()->after('pickup_pic_position');
            $table->timestamp('pickup_handover_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method',
                'pickup_pic_nip',
                'pickup_pic_name',
                'pickup_pic_position',
                'pickup_notes',
                'pickup_handover_at',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('emboss_file_id');
            $table->dropColumn([
                'delivery_method',
                'pickup_pic_nip',
                'pickup_pic_name',
                'pickup_pic_position',
                'pickup_notes',
            ]);
        });
    }
};
