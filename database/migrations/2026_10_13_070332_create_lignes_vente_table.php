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
    Schema::create('lignes_vente', function (Blueprint $table) {
        $table->id();
        $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
        $table->nullableMorphs('vendable');          // lot ou reproducteur (nom court du morph map)
        $table->string('categorie');
        $table->string('description');               // copie lisible, conservée même si l'élément disparaît
        $table->unsignedSmallInteger('quantite');
        $table->decimal('poids_kg', 8, 2)->nullable();
        $table->string('mode_prix');
        $table->decimal('prix_applique', 12, 2);
        $table->decimal('montant', 14, 2);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_vente');
    }
};
