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
    Schema::create('vaccinations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('protocole_id')->nullable()->constrained('protocoles_vaccinaux')->nullOnDelete();
        $table->foreignId('produit_id')->nullable()->constrained('produits_veterinaires')->nullOnDelete();
        $table->string('nom_vaccin');
        $table->morphs('vaccinable');
        $table->date('date_vaccination');
        $table->decimal('quantite_utilisee', 10, 2)->default(0);
        $table->boolean('anterieure')->default(false);
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
