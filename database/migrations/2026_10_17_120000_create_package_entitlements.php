<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('bundles', fn (Blueprint $table) => $table->json('entitlement_settings')->nullable());
        Schema::create('package_changes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        $defaults = [];
        foreach (config('package_features') as $key => $feature) {
            $defaults[$key] = $feature['default'];
            DB::table('package_features')->insert([
                'key' => $key, 'name_en' => $feature['name_en'], 'name_ar' => $feature['name_ar'],
                'description' => $feature['description'], 'type' => $feature['type'],
                'sort_order' => count($defaults), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Store explicit starting settings without changing seller permissions.
        DB::table('bundles')->update(['entitlement_settings' => json_encode($defaults)]);
    }

    public function down(): void
    {
        Schema::dropIfExists('package_changes');
        Schema::table('bundles', fn (Blueprint $table) => $table->dropColumn('entitlement_settings'));
        Schema::dropIfExists('package_features');
    }
};
