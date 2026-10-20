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
    Schema::create('mouvements_aliments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('aliment_id')->constrained('aliments')->restrictOnDelete();
        $table->string('type');
        $table->decimal('quantite_kg', 10, 2);   // signée : positive à l'entrée, négative à la sortie
        $table->unsignedSmallInteger('nombre_sacs')->nullable();
        $table->decimal('poids_sac_kg', 7, 2)->nullable();
        $table->date('date_mouvement');
        $table->string('fournisseur')->nullable();
        $table->decimal('cout_total', 12, 2)->nullable();   // servira au module Finances
        $table->string('motif')->nullable();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();

        $table->index(['aliment_id', 'date_mouvement']);
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvements_aliments');
    }
};
