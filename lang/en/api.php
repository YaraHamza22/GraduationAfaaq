<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API response messages (English)
    |--------------------------------------------------------------------------
    */

    'instructor' => [
        'admin_list_success' => 'Instructors were retrieved successfully.',
        'admin_created_success' => 'The instructor account was created successfully.',
        'admin_updated_success' => 'The instructor account was updated successfully.',
        'admin_deleted_success' => 'The instructor account was deleted successfully.',
        'student_directory_list_success' => 'Instructors were retrieved successfully for the learner directory.',
        'student_directory_one_success' => 'Instructor public profile was retrieved successfully.',
        'student_directory_not_found' => 'No published instructor profile was found for this identifier.',
    ],

    'security' => [
        'audit_logs_list_success' => 'Sensitive security audit records were retrieved successfully.',
        'audit_log_one_success' => 'The sensitive security audit record was retrieved successfully.',
    ],

    'activity_log' => [
        'list_success' => 'Activity records were retrieved successfully.',
        'one_success' => 'The activity record was retrieved successfully.',
    ],
];
