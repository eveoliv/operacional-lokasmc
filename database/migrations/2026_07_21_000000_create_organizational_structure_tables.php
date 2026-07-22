<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedInteger('hierarchy_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'organizational_unit_types_code_unique');
            $table->unique('hierarchy_order', 'organizational_unit_types_order_unique');
            $table->index('is_active', 'organizational_unit_types_active_index');
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizational_unit_type_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 100);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('organizational_unit_type_id', 'organizational_units_type_fk')
                ->references('id')->on('organizational_unit_types')->restrictOnDelete();
            $table->foreign('parent_id', 'organizational_units_parent_fk')
                ->references('id')->on('organizational_units')->restrictOnDelete();
            $table->unique('code', 'organizational_units_code_unique');
            $table->index('organizational_unit_type_id', 'organizational_units_type_index');
            $table->index('parent_id', 'organizational_units_parent_index');
            $table->index(['is_active', 'archived_at'], 'organizational_units_state_index');
        });

        Schema::create('organizational_unit_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedInteger('depth');

            $table->foreign('ancestor_id', 'organizational_unit_closure_ancestor_fk')
                ->references('id')->on('organizational_units')->restrictOnDelete();
            $table->foreign('descendant_id', 'organizational_unit_closure_descendant_fk')
                ->references('id')->on('organizational_units')->restrictOnDelete();
            $table->unique(['ancestor_id', 'descendant_id'], 'organizational_unit_closure_pair_unique');
            $table->index(['descendant_id', 'ancestor_id'], 'organizational_unit_closure_descendant_index');
            $table->index(['ancestor_id', 'depth'], 'organizational_unit_closure_depth_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_unit_closure');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('organizational_unit_types');
    }
};
