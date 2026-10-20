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
    Schema::create('pesees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reproducteur_id')->constrained('reproducteurs')->cascadeOnDelete();
        $table->date('date_pesee');
        $table->decimal('poids_kg', 5, 2);
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesees');
    }
};
