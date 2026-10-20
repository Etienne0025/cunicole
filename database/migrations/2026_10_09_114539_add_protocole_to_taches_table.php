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
    Schema::table('taches', function (Blueprint $table) {
        $table->foreignId('protocole_id')->nullable()->after('source_id')
            ->constrained('protocoles_vaccinaux')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('taches', function (Blueprint $table) {
        $table->dropConstrainedForeignId('protocole_id');
    });
}
};
