<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Jamia Zahidia Campus Management System'),

    /*
    |--------------------------------------------------------------------------
    | Application Short Name
    |--------------------------------------------------------------------------
    |
    | This is the short name/abbreviation of your application.
    |
    */

    'short_name' => env('APP_SHORT_NAME', 'JZCMS'),

    /*
    |--------------------------------------------------------------------------
    | Academic Year
    |--------------------------------------------------------------------------
    |
    | The current academic year for the system.
    |
    */

    'academic_year' => env('ACADEMIC_YEAR', date('Y')),

    /*
    |--------------------------------------------------------------------------
    | Default Pagination
    |--------------------------------------------------------------------------
    |
    | This value determines the default number of items per page.
    |
    */

    'pagination' => [
        'per_page' => env('PAGINATION_PER_PAGE', 15),
        'per_page_options' => [10, 15, 25, 50, 100],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Roles
    |--------------------------------------------------------------------------
    |
    | Define the user roles available in the system.
    |
    */

    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'teacher' => 'Teacher',
        'parent' => 'Parent',
        'student' => 'Student',
        'accountant' => 'Accountant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Format
    |--------------------------------------------------------------------------
    |
    | Default date format used throughout the application.
    |
    */

    'date_format' => env('DATE_FORMAT', 'd-m-Y'),
    'date_time_format' => env('DATE_TIME_FORMAT', 'd-m-Y H:i:s'),
    'time_format' => env('TIME_FORMAT', 'H:i:s'),

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    |
    | Default currency settings for fee management.
    |
    */

    'currency' => [
        'code' => env('CURRENCY_CODE', 'PKR'),
        'symbol' => env('CURRENCY_SYMBOL', 'Rs.'),
        'position' => env('CURRENCY_POSITION', 'before'), // before or after
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configure file upload limits and allowed types.
    |
    */

    'upload' => [
        'max_size' => env('UPLOAD_MAX_SIZE', 2048), // in KB
        'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'allowed_document_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Settings
    |--------------------------------------------------------------------------
    |
    | Configure SMS notification settings.
    |
    */

    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'provider' => env('SMS_PROVIDER', null), // e.g., 'twilio', 'nexmo'
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    |
    | Configure email notification settings.
    |
    */

    'email' => [
        'enabled' => env('EMAIL_ENABLED', true),
        'from_name' => env('MAIL_FROM_NAME', 'JZCMS'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@jzcms.edu.pk'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Settings
    |--------------------------------------------------------------------------
    |
    | Configure attendance tracking settings.
    |
    */

    'attendance' => [
        'statuses' => [
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'leave' => 'Leave',
            'half_day' => 'Half Day',
        ],
        'default_status' => 'present',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Settings
    |--------------------------------------------------------------------------
    |
    | Configure fee management settings.
    |
    */

    'fee' => [
        'late_fee_enabled' => env('LATE_FEE_ENABLED', true),
        'late_fee_days' => env('LATE_FEE_DAYS', 10),
        'late_fee_amount' => env('LATE_FEE_AMOUNT', 100),
        'payment_methods' => [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'online' => 'Online Payment',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Student ID Format
    |--------------------------------------------------------------------------
    |
    | Configure student ID generation format.
    |
    */

    'student_id' => [
        'prefix' => env('STUDENT_ID_PREFIX', 'STD'),
        'length' => env('STUDENT_ID_LENGTH', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grade Settings
    |--------------------------------------------------------------------------
    |
    | Configure grading system.
    |
    */

    'grades' => [
        'A+' => ['min' => 90, 'max' => 100, 'points' => 4.0],
        'A' => ['min' => 80, 'max' => 89, 'points' => 3.7],
        'B+' => ['min' => 70, 'max' => 79, 'points' => 3.3],
        'B' => ['min' => 60, 'max' => 69, 'points' => 3.0],
        'C+' => ['min' => 50, 'max' => 59, 'points' => 2.3],
        'C' => ['min' => 40, 'max' => 49, 'points' => 2.0],
        'F' => ['min' => 0, 'max' => 39, 'points' => 0.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    |
    | Configure academic session settings.
    |
    */

    'session' => [
        'start_month' => env('SESSION_START_MONTH', 4), // April
        'end_month' => env('SESSION_END_MONTH', 3), // March
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure automated backup settings.
    |
    */

    'backup' => [
        'enabled' => env('BACKUP_ENABLED', false),
        'frequency' => env('BACKUP_FREQUENCY', 'daily'), // daily, weekly, monthly
        'keep_backups' => env('BACKUP_KEEP_COUNT', 7),
    ],

];
