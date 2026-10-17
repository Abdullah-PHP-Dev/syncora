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
        Schema::table('bundles', fn (Blueprint $table) => $table->json('access_settings')->nullable());
        Schema::create('package_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->date('period_start');
            $table->string('event_key', 150);
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->unique(['user_id', 'feature_key', 'event_key'], 'package_usage_event_unique');
            $table->index(['user_id', 'feature_key', 'period_start']);
        });
        Bundle::orderBy('id')->chunkById(100, function ($bundles) {
            foreach ($bundles as $bundle) {
                DB::table('bundles')->where('id', $bundle->id)->update(['access_settings' => json_encode(PackageAccessService::initialSettings($bundle))]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_usage');
        Schema::table('bundles', fn (Blueprint $table) => $table->dropColumn('access_settings'));
    }
};
