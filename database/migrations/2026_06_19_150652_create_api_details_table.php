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
        Schema::create('api_details',function(Blueprint $table){

            $table->id();
            
            
            $table->string('platform');
            
            
            $table->string('base_url');
            
            
            $table->string('version');
            
            
            $table->string('type');
            
            
            $table->string('status')->nullable();
            
            
            $table->timestamps();
            
            
            $table->index('base_url');
            
            $table->index('version');
            
            
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_details');
    }
};
