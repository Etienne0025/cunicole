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
    Schema::create('objectifs_naissances', function (Blueprint $table) {
        $table->id();
        $table->unsignedSmallInteger('annee');
        $table->unsignedTinyInteger('mois');
        $table->unsignedSmallInteger('objectif');
        $table->timestamps();

        $table->unique(['annee', 'mois']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objectifs_naissances');
    }
};
