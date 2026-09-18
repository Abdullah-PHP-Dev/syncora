<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('html_content');
            $table->string('editor_type')->default('code');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['email_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_template_versions');
    }
};
