<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G-02: CRM alerts for the navbar bell (lead assigned, new unassigned lead,
 * deal won/lost, task reminders). One row per recipient person; the
 * workspace column keeps them inside the tenant they came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_notifications')) {
            return;
        }
        Schema::create('crm_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id'); // the person who receives it
            $table->string('event', 40);
            $table->string('title', 200);
            $table->string('body', 300)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'workspace_id', 'read_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notifications');
    }
};
