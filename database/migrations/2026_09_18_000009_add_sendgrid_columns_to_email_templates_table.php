<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('sendgrid_design_id')->nullable()->after('body');
            $table->unsignedInteger('current_version')->default(1)->after('sendgrid_design_id');
            $table->string('thumbnail_url')->nullable()->after('current_version');
            $table->string('category')->nullable()->after('thumbnail_url');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn(['sendgrid_design_id', 'current_version', 'thumbnail_url', 'category', 'status']);
        });
    }
};
