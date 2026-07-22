<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizational_unit_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('document', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('organizational_unit_id', 'people_unit_fk')->references('id')->on('organizational_units')->restrictOnDelete();
            $table->foreign('user_id', 'people_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique('user_id', 'people_user_unique');
            $table->index('organizational_unit_id', 'people_unit_index');
            $table->index('email', 'people_email_index');
            $table->index('document', 'people_document_index');
            $table->index(['status', 'archived_at'], 'people_state_index');
            $table->index(['organizational_unit_id', 'name'], 'people_unit_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
