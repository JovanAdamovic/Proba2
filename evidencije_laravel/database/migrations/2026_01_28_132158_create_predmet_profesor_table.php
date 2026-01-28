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
        Schema::create('predmet_profesor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predmet_id') 
                ->constrained('predmeti')
                ->cascadeOnDelete(); 
            $table->foreignId('profesor_id') 
                ->constrained('users')
                ->cascadeOnDelete(); 
            $table->unique(['predmet_id', 'profesor_id']); 
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void  
    {
        Schema::dropIfExists('predmet_profesor'); 
    }
};