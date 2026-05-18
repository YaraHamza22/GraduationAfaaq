<?php

use Illuminate\Support\Facades\Route;
use Modules\ReportingModule\Http\Controllers\ActivityLogController;

/**
 * Activity log (سجل النشاط) — super-admin reporting routes.
 *
 * EN: Paginated activity log with filters (same underlying model as security sensitive logs).
 * AR: سجل النشاط العام مع التصفية (نفس جدول النشاط المستخدم في السجلات الحساسة).
 */
Route::middleware(['auth:api', 'role:super-admin,api'])
    ->prefix('super-admin/activity-log')
    ->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])
            ->name('reporting.activity-log.index');
        Route::get('/{activity_log}', [ActivityLogController::class, 'show'])
            ->whereNumber('activity_log')
            ->name('reporting.activity-log.show');
    });
