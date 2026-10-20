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
    Schema::create('blocages_consanguinite', function (Blueprint $table) {
        $table->id();
        $table->foreignId('femelle_id')->constrained('reproducteurs')->cascadeOnDelete();
        $table->foreignId('male_id')->constrained('reproducteurs')->cascadeOnDelete();
        $table->string('lien');
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocages_consanguinite');
    }
};
