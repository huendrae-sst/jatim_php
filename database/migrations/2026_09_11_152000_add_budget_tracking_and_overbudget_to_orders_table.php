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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('requesting_organization_id')->constrained('budgets')->nullOnDelete();
            $table->boolean('is_overbudget')->default(false)->after('total_estimated_value');
            $table->decimal('projected_utilization', 5, 2)->default(0)->after('is_overbudget');
            $table->text('overbudget_approval_reason')->nullable()->after('projected_utilization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['budget_id']);
            $table->dropColumn([
                'budget_id',
                'is_overbudget',
                'projected_utilization',
                'overbudget_approval_reason',
            ]);
        });
    }
};
