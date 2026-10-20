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
    Schema::create('criteres_selection', function (Blueprint $table) {
        $table->id();
        $table->decimal('poids_min_kg', 4, 2)->default(2.5);
        $table->unsignedSmallInteger('age_min_jours')->default(90);
        $table->decimal('prolificite_mere_min', 3, 1)->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('criteres_selection');
}
};
