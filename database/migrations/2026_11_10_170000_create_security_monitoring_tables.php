<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60)->index();
            $table->string('severity', 20)->index();
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('action', 20)->default('log');
            $table->string('ip', 45)->index();
            $table->string('method', 10)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('categories')->nullable();
            $table->json('signatures')->nullable();
            $table->json('context')->nullable();
            $table->integer('status_code')->nullable();
            $table->string('fingerprint', 64)->index();
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('security_ip_rules', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('type', 20)->default('block'); // block | allow
            $table->string('source', 20)->default('manual'); // manual | auto
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('incident_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['ip', 'type']);
        });

        Schema::create('security_reset_grants', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index(); // password_reset | signup
            $table->string('identifier_hash', 64)->index();
            $table->string('client_hash', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_reset_grants');
        Schema::dropIfExists('security_ip_rules');
        Schema::dropIfExists('security_events');
    }
};
