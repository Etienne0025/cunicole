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
    Schema::table('reproducteurs', function (Blueprint $table) {
        $table->timestamp('valide_le')->nullable()->after('statut');
        $table->foreignId('valide_par_id')->nullable()->after('valide_le')
            ->constrained('utilisateurs')->nullOnDelete();
        $table->foreignId('lot_origine_id')->nullable()->after('valide_par_id')
            ->constrained('lots_engraissement')->nullOnDelete();
    });

    // Les animaux déjà présents sont considérés comme validés
    DB::table('reproducteurs')->update(['valide_le' => now()]);
}

public function down(): void
{
    Schema::table('reproducteurs', function (Blueprint $table) {
        $table->dropConstrainedForeignId('lot_origine_id');
        $table->dropConstrainedForeignId('valide_par_id');
        $table->dropColumn('valide_le');
    });
}
};
