<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sendgrid_segment_id')->nullable();
            $table->string('name');
            // SendGrid's segment query (their SGQL filter string) - stored
            // so the segment's definition is visible/editable locally
            // without a round-trip to SendGrid just to display it.
            $table->text('query_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_segments');
    }
};
