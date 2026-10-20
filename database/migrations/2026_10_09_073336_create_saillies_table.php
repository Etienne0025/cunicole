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
    Schema::create('saillies', function (Blueprint $table) {
        $table->id();
        $table->foreignId('femelle_id')->constrained('reproducteurs')->restrictOnDelete();
        $table->foreignId('male_id')->constrained('reproducteurs')->restrictOnDelete();
        $table->date('date_saillie');
        $table->string('statut')->default('en_attente');
        $table->date('date_palpation')->nullable();
        $table->date('boite_nids_le')->nullable();
        $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index(['femelle_id', 'statut']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saillies');
    }
};
