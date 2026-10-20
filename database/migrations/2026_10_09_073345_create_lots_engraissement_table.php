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
    Schema::create('lots_engraissement', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->foreignId('portee_id')->nullable()->constrained('portees')->nullOnDelete();
        $table->foreignId('cage_id')->nullable()->constrained('cages')->restrictOnDelete();
        $table->unsignedSmallInteger('effectif_initial');
        $table->unsignedSmallInteger('effectif_actuel');
        $table->date('date_debut');
        $table->string('statut')->default('en_cours');
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots_engraissement');
    }
};
