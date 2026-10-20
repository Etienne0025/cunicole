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
    Schema::table('utilisateurs', function (Blueprint $table) {
        $table->boolean('doit_changer_mot_de_passe')->default(false)->after('actif');
        $table->timestamp('derniere_connexion_le')->nullable()->after('doit_changer_mot_de_passe');
    });
}

public function down(): void
{
    Schema::table('utilisateurs', function (Blueprint $table) {
        $table->dropColumn(['doit_changer_mot_de_passe', 'derniere_connexion_le']);
    });
}
};
