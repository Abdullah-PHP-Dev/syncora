<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table) {

            $table->id();

            $table->string('platform');
            $table->string('name')->nullable();

            // $table->foreignId('company_id')
            //     ->constrained()
            //     ->cascadeOnDelete();

            $table->string('currency')->nullable();

            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('token_secret')->nullable();
            $table->string('status');

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();


            $table->index('client_id');

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ad_accounts');
    }
};