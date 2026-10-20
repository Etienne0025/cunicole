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
    Schema::create('traitements', function (Blueprint $table) {
        $table->id();
        $table->morphs('traitable');
        $table->foreignId('produit_id')->nullable()->constrained('produits_veterinaires')->nullOnDelete();
        $table->string('nom_traitement');
        $table->text('symptomes');
        $table->date('date_debut');
        $table->unsignedSmallInteger('duree_jours')->default(1);
        $table->unsignedSmallInteger('delai_attente_jours')->default(0);
        $table->date('date_fin_traitement');
        $table->date('date_fin_attente');
        $table->decimal('quantite_utilisee', 10, 2)->nullable();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();

        $table->index('date_fin_attente');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traitements');
    }
};
