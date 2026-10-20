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
    Schema::create('distributions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ration_id')->nullable()->constrained('rations')->nullOnDelete();
        $table->foreignId('aliment_id')->constrained('aliments')->restrictOnDelete();
        $table->string('categorie');
        $table->date('date_distribution');
        $table->unsignedSmallInteger('nombre_animaux');
        $table->unsignedSmallInteger('grammes_par_animal');
        $table->decimal('quantite_theorique_kg', 10, 2);
        $table->decimal('quantite_servie_kg', 10, 2);
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();

        $table->unique(['categorie', 'aliment_id', 'date_distribution']);
        $table->index('date_distribution');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};
