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
        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->nullable();
            $table->string('comment_id')->nullable();
            $table->string('parent_comment_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('user_name')->nullable();
            $table->text('content')->nullable();
            $table->string('user_avatar_url')->nullable();
            $table->integer('likes')->nullable()->default(0);
            $table->boolean('is_flagged')->nullable()->default(0);
            $table->string('status')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_comments');
    }
};
