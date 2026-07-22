<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 150);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->unsignedBigInteger('organizational_unit_id')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('actor_user_id', 'audit_logs_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('organizational_unit_id', 'audit_logs_unit_fk')->references('id')->on('organizational_units')->restrictOnDelete();
            $table->index('actor_user_id', 'audit_logs_actor_index');
            $table->index('organizational_unit_id', 'audit_logs_unit_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
            $table->index('correlation_id', 'audit_logs_correlation_index');
            $table->index('created_at', 'audit_logs_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
