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
    Schema::create('mortalites', function (Blueprint $table) {
        $table->id();
        $table->string('cible');
        $table->foreignId('reproducteur_id')->nullable()->constrained('reproducteurs')->nullOnDelete();
        $table->foreignId('lot_id')->nullable()->constrained('lots_engraissement')->nullOnDelete();
        $table->foreignId('portee_id')->nullable()->constrained('portees')->nullOnDelete();
        $table->string('code_cible');             // copie du matricule ou du code, conservée si l'élément est supprimé
        $table->unsignedSmallInteger('nombre')->default(1);
        $table->date('date_deces');
        $table->string('cause');
        $table->string('precisions')->nullable();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();

        $table->index('date_deces');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortalites');
    }
};
