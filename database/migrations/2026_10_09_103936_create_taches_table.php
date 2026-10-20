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
    Schema::create('taches', function (Blueprint $table) {
        $table->id();
        $table->string('cle_unique')->unique();   // type + élément + jour : évite les doublons
        $table->string('type');
        $table->date('date_prevue');
        $table->string('titre');
        $table->string('description')->nullable();
        $table->nullableMorphs('source');         // source_type + source_id (saillie, cage, lot)
        $table->string('statut')->default('a_faire');
        $table->timestamp('faite_le')->nullable();
        $table->foreignId('faite_par_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        $table->boolean('automatique')->default(false);
        $table->text('justification')->nullable();
        $table->timestamps();

        $table->index(['date_prevue', 'statut']);
    });
}

public function down(): void
{
    Schema::dropIfExists('taches');
}
};
