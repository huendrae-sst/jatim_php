<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Chart of Accounts (CoA / Rekening Buku Besar)
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique()->index();
            $table->string('account_name');
            $table->enum('account_type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'])->default('ASSET');
            $table->string('classification')->nullable(); // e.g. Aset Lancar, Beban Operasional, dll.
            $table->enum('normal_balance', ['DEBIT', 'CREDIT'])->default('DEBIT');
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Master Cost Centers (Pusat Biaya Unit Kerja)
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index();
            $table->string('name');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('department')->nullable();
            $table->string('pic_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('chart_of_accounts');
    }
};
