<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizational_unit_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('organizational_unit_id', 'events_unit_fk')->references('id')->on('organizational_units')->restrictOnDelete();
            $table->index('organizational_unit_id', 'events_unit_index');
            $table->index(['status', 'starts_at'], 'events_status_start_index');
            $table->index('archived_at', 'events_archived_index');
        });

        Schema::create('event_audiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizational_unit_id');
            $table->boolean('include_descendants')->default(false);
            $table->timestamps();

            $table->foreign('event_id', 'event_audiences_event_fk')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('organizational_unit_id', 'event_audiences_unit_fk')->references('id')->on('organizational_units')->restrictOnDelete();
            $table->unique(['event_id', 'organizational_unit_id'], 'event_audiences_event_unit_unique');
            $table->index('organizational_unit_id', 'event_audiences_unit_index');
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('person_id');
            $table->string('status', 50)->default('active');
            $table->string('source', 50);
            $table->unsignedBigInteger('operated_by_user_id')->nullable();
            $table->unsignedBigInteger('eligible_event_audience_id')->nullable();
            $table->unsignedBigInteger('eligible_organizational_unit_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->foreign('event_id', 'registrations_event_fk')->references('id')->on('events')->restrictOnDelete();
            $table->foreign('person_id', 'registrations_person_fk')->references('id')->on('people')->restrictOnDelete();
            $table->foreign('operated_by_user_id', 'registrations_operator_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('eligible_event_audience_id', 'registrations_audience_fk')->references('id')->on('event_audiences')->restrictOnDelete();
            $table->foreign('eligible_organizational_unit_id', 'registrations_eligible_unit_fk')->references('id')->on('organizational_units')->restrictOnDelete();
            $table->foreign('cancelled_by_user_id', 'registrations_canceller_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['event_id', 'person_id'], 'registrations_event_person_unique');
            $table->index('person_id', 'registrations_person_index');
            $table->index('operated_by_user_id', 'registrations_operator_index');
            $table->index('eligible_event_audience_id', 'registrations_audience_index');
            $table->index('eligible_organizational_unit_id', 'registrations_eligible_unit_index');
            $table->index('cancelled_by_user_id', 'registrations_canceller_index');
            $table->index(['event_id', 'status'], 'registrations_event_status_index');
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id', 'attendance_sessions_event_fk')->references('id')->on('events')->restrictOnDelete();
            $table->foreign('locked_by_user_id', 'attendance_sessions_locker_fk')->references('id')->on('users')->nullOnDelete();
            $table->index('event_id', 'attendance_sessions_event_index');
            $table->index('locked_by_user_id', 'attendance_sessions_locker_index');
            $table->index(['event_id', 'starts_at'], 'attendance_sessions_event_start_index');
            $table->index('archived_at', 'attendance_sessions_archived_index');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_session_id');
            $table->unsignedBigInteger('registration_id');
            $table->string('status', 50);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('operated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('attendance_session_id', 'attendance_records_session_fk')->references('id')->on('attendance_sessions')->restrictOnDelete();
            $table->foreign('registration_id', 'attendance_records_registration_fk')->references('id')->on('registrations')->restrictOnDelete();
            $table->foreign('operated_by_user_id', 'attendance_records_operator_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['attendance_session_id', 'registration_id'], 'attendance_records_session_registration_unique');
            $table->index('registration_id', 'attendance_records_registration_index');
            $table->index('operated_by_user_id', 'attendance_records_operator_index');
            $table->index(['attendance_session_id', 'status'], 'attendance_records_session_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('event_audiences');
        Schema::dropIfExists('events');
    }
};
