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
    Schema::create('reproducteurs', function (Blueprint $table) {
        $table->id();
        $table->string('matricule')->unique();   // code de tatouage officiel
        $table->string('nom')->nullable();
        $table->string('sexe');
        $table->string('race')->nullable();
        $table->date('date_naissance');
        $table->string('origine')->default('ferme');
        $table->foreignId('mere_id')->nullable()->constrained('reproducteurs')->nullOnDelete();
        $table->foreignId('pere_id')->nullable()->constrained('reproducteurs')->nullOnDelete();
        $table->foreignId('cage_id')->nullable()->constrained('cages')->restrictOnDelete();
        $table->string('statut')->default('actif');
        $table->date('date_entree');
        $table->date('date_sortie')->nullable();
        $table->string('motif_sortie')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reproducteurs');
    }
};
