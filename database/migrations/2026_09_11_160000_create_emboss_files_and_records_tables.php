<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emboss_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_id')->unique(); // e.g. EMB-202609-0001
            $table->string('file_name');
            $table->string('file_hash')->index(); // SHA-256 for duplicate file detection (POC-23)
            $table->string('source')->default('CARD_CORE_SYSTEM'); // CARD_CORE_SYSTEM, ATM_CENTER, BRANCH_PORTAL, MANUAL_UPLOAD
            $table->integer('total_records')->default(0);
            $table->integer('success_records')->default(0);
            $table->integer('reject_records')->default(0);
            $table->integer('duplicate_records')->default(0);
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED, PARTIAL_SUCCESS
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('emboss_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emboss_file_id')->constrained('emboss_files')->cascadeOnDelete();
            $table->string('external_reference_id')->index(); // For record-level duplicate detection (POC-23)
            $table->string('branch_code')->index();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('product_code')->index(); // SKU
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('card_type')->default('INSTANT'); // INSTANT, NAMED, TOKEN, KUE
            $table->string('cardholder_name')->nullable();
            $table->string('card_number')->nullable(); // Stored securely, masked on display (POC-54)
            $table->string('cif_number')->nullable();
            $table->string('account_number')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0); // Price enrichment (POC-27)
            $table->decimal('total_price', 15, 2)->default(0);
            $table->string('status')->default('VALID'); // VALID, INVALID, DUPLICATE, PROCESSED_TO_ORDER
            $table->text('error_message')->nullable(); // Specific error for reject queue (POC-22)
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('procurement_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emboss_records');
        Schema::dropIfExists('emboss_files');
    }
};
