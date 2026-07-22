<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable();
            $table->text('disabled_reason')->nullable();

            $table->index('disabled_at', 'users_disabled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_disabled_at_index');
            $table->dropColumn(['disabled_at', 'disabled_reason']);
        });
    }
};
