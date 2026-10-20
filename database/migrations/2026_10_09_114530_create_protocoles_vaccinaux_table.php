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
    Schema::create('protocoles_vaccinaux', function (Blueprint $table) {
        $table->id();
        $table->string('nom');
        $table->foreignId('produit_id')->constrained('produits_veterinaires')->restrictOnDelete();
        $table->string('cible');
        $table->unsignedSmallInteger('age_premiere_dose_jours')->default(0);
        $table->unsignedSmallInteger('intervalle_rappel_jours')->nullable();
        $table->decimal('doses_par_animal', 6, 2)->default(1);
        $table->boolean('actif')->default(true);
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('protocoles_vaccinaux');
    }
};
