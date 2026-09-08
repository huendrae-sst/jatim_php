<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number')->index();
            $table->date('transaction_date')->index();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('cost_center_code')->nullable()->index();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('account_code')->index();
            $table->string('account_name');
            $table->string('reference_type')->nullable()->index(); // e.g. SETTLEMENT, GOODS_RECEIPT, STOCK_OPNAME, INITIAL_BALANCE, MANUAL
            $table->string('reference_number')->nullable()->index();
            $table->text('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'transaction_date']);
            $table->index(['chart_of_account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledger_entries');
    }
};
