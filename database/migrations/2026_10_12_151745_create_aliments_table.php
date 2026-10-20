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
    Schema::create('aliments', function (Blueprint $table) {
        $table->id();
        $table->string('nom')->unique();
        $table->decimal('poids_sac_kg', 7, 2)->nullable();
        $table->decimal('stock_kg', 10, 2)->default(0);
        $table->boolean('actif')->default(true);
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aliments');
    }
};
