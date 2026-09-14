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
        Schema::table('discrepancies', function (Blueprint $table) {
            $table->string('berita_acara_path')->nullable()->after('resolution_notes');
            $table->string('berita_acara_filename')->nullable()->after('berita_acara_path');
            $table->text('checker_notes')->nullable()->after('berita_acara_filename');
            $table->foreignId('checker_user_id')->nullable()->after('checker_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('checker_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discrepancies', function (Blueprint $table) {
            $table->dropForeign(['checker_user_id']);
            $table->dropColumn([
                'berita_acara_path',
                'berita_acara_filename',
                'checker_notes',
                'checker_user_id',
                'verified_at',
            ]);
        });
    }
};
