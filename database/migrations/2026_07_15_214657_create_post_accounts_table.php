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
        Schema::create('post_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->nullable();
            $table->string('name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('account_id')->nullable();
            $table->string('parent_account_id')->nullable();
            $table->string('username')->nullable();
            $table->string('account_url')->nullable();
            $table->text('image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->nullable();
            $table->boolean('is_active')->nullable();
            $table->boolean('webhook_subscriptions')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('token_secret')->nullable();
            $table->string('status')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->datetime('expires_in')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_accounts');
    }
};
