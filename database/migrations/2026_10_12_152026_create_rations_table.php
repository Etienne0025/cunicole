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
    Schema::create('rations', function (Blueprint $table) {
        $table->id();
        $table->string('categorie');
        $table->foreignId('aliment_id')->constrained('aliments')->restrictOnDelete();
        $table->unsignedSmallInteger('grammes_par_jour');   // par animal
        $table->boolean('actif')->default(true);
        $table->string('notes')->nullable();
        $table->timestamps();

        $table->unique(['categorie', 'aliment_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rations');
    }
};
