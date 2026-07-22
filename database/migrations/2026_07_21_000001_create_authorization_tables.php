<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('hierarchy_level');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'roles_code_unique');
            $table->index(['is_active', 'hierarchy_level'], 'roles_active_level_index');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 150);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique('code', 'permissions_code_unique');
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id', 'permission_role_permission_fk')
                ->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id', 'permission_role_role_fk')
                ->references('id')->on('roles')->cascadeOnDelete();
            $table->unique(['permission_id', 'role_id'], 'permission_role_pair_unique');
            $table->index(['role_id', 'permission_id'], 'permission_role_role_index');
        });

        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('organizational_unit_id');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('granted_by_user_id')->nullable();
            $table->unsignedBigInteger('delegated_from_grant_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'access_grants_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('role_id', 'access_grants_role_fk')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('organizational_unit_id', 'access_grants_unit_fk')->references('id')->on('organizational_units')->restrictOnDelete();
            $table->foreign('granted_by_user_id', 'access_grants_grantor_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('delegated_from_grant_id', 'access_grants_delegated_from_fk')->references('id')->on('access_grants')->restrictOnDelete();
            $table->foreign('revoked_by_user_id', 'access_grants_revoker_fk')->references('id')->on('users')->nullOnDelete();
            $table->index('user_id', 'access_grants_user_index');
            $table->index('role_id', 'access_grants_role_index');
            $table->index('organizational_unit_id', 'access_grants_unit_index');
            $table->index('granted_by_user_id', 'access_grants_grantor_index');
            $table->index('delegated_from_grant_id', 'access_grants_delegated_from_index');
            $table->index('revoked_by_user_id', 'access_grants_revoker_index');
            $table->index(['user_id', 'revoked_at', 'starts_at', 'ends_at'], 'access_grants_active_window_index');
            $table->index(['organizational_unit_id', 'role_id'], 'access_grants_scope_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
