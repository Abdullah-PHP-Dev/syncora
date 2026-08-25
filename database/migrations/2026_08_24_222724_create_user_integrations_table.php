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
        Schema::create('user_integrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->cascadeOnDelete();

            // Encrypted (Model-level 'encrypted:array' cast) - stored as a
            // long opaque ciphertext string, so this needs to be `text`, not
            // `json` (a real json column would reject/mangle the encrypted
            // payload since it isn't valid JSON on its own).
            $table->text('credentials')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'integration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_integrations');
    }
};
