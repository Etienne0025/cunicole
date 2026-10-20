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
    Schema::create('mouvements_cages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reproducteur_id')->constrained('reproducteurs')->cascadeOnDelete();
        $table->foreignId('cage_origine_id')->nullable()->constrained('cages')->nullOnDelete();
        $table->foreignId('cage_destination_id')->nullable()->constrained('cages')->nullOnDelete();
        $table->string('code_origine')->nullable();       // copie du code, si la cage est supprimée plus tard
        $table->string('code_destination')->nullable();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->string('motif')->nullable();
        $table->timestamp('effectue_le');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('mouvements_cages');
}
};
