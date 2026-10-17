<?php

use App\Models\Bundle;
use App\Services\PackageAccessService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['owner_id', 'email']);
            $table->unique(['owner_id', 'user_id']);
        });
        Bundle::orderBy('id')->chunkById(100, function ($bundles) {
            foreach ($bundles as $bundle) {
                $settings = $bundle->access_settings ?? [];
                $settings['team_management'] ??= PackageAccessService::initialSettings($bundle)['team_management'];
                DB::table('bundles')->where('id', $bundle->id)->update(['access_settings' => json_encode($settings)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_team_members');
        Bundle::orderBy('id')->chunkById(100, function ($bundles) {
            foreach ($bundles as $bundle) {
                $settings = $bundle->access_settings ?? [];
                unset($settings['team_management']);
                DB::table('bundles')->where('id', $bundle->id)->update(['access_settings' => json_encode($settings)]);
            }
        });
    }
};
