<?php

use App\Http\Controllers\AccessGrantController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\AttendanceSessionLockController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('organization', [OrganizationController::class, 'store'])->name('organization.store');
    Route::put('organization/{organizational_unit}', [OrganizationController::class, 'update'])->name('organization.update');
    Route::patch('organization/{organizational_unit}/move', [OrganizationController::class, 'move'])->name('organization.move');
    Route::patch('organization/{organizational_unit}/archive', [OrganizationController::class, 'archive'])->name('organization.archive');

    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

    Route::get('people', [PersonController::class, 'index'])->name('people.index');
    Route::post('people', [PersonController::class, 'store'])->name('people.store');
    Route::put('people/{person}', [PersonController::class, 'update'])->name('people.update');
    Route::patch('people/{person}/archive', [PersonController::class, 'archive'])->name('people.archive');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::get('access-grants', [AccessGrantController::class, 'index'])->name('access-grants.index');
    Route::post('access-grants', [AccessGrantController::class, 'store'])->name('access-grants.store');
    Route::patch('access-grants/{access_grant}/revoke', [AccessGrantController::class, 'revoke'])->name('access-grants.revoke');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('events/{event}/status', [EventController::class, 'transition'])->name('events.transition');

    Route::get('registrations', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::post('events/{event}/registrations', [RegistrationController::class, 'store'])->name('events.registrations.store');
    Route::patch('events/{event}/registrations/{registration}/cancel', [RegistrationController::class, 'cancel'])->name('events.registrations.cancel');

    Route::get('attendance-sessions', [AttendanceSessionController::class, 'index'])->name('attendance-sessions.index');
    Route::post('events/{event}/attendance-sessions', [AttendanceSessionController::class, 'store'])->name('events.attendance-sessions.store');
    Route::put('attendance-sessions/{attendance_session}', [AttendanceSessionController::class, 'update'])->name('attendance-sessions.update');
    Route::put('attendance-sessions/{attendance_session}/records', [AttendanceRecordController::class, 'batch'])->name('attendance-sessions.records.store');
    Route::patch('attendance-sessions/{attendance_session}/lock', [AttendanceSessionLockController::class, 'lock'])->name('attendance-sessions.lock');
    Route::patch('attendance-sessions/{attendance_session}/unlock', [AttendanceSessionLockController::class, 'unlock'])->name('attendance-sessions.unlock');
});

require __DIR__.'/settings.php';
