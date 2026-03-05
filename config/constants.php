<?php

date_default_timezone_set('Africa/Nairobi');
define('APP_NAME', 'KSG Weekly Status Report');
define('APP_VERSION', '1.0.0');

define('CAMPUSES', [
    'nairobi'  => 'Nairobi (Main Campus)',
    'mombasa'  => 'Mombasa Campus',
    'matuga'   => 'Matuga Campus',
    'embu'     => 'Embu Campus',
    'baringo'  => 'Baringo Campus',
]);

define('DEPARTMENTS', [
    'ict'                    => 'ICT',
    'hospitality'            => 'Hospitality',
    'training'               => 'Training',
    'administration_planning' => 'Administration & Planning',
    'finance_accounting'     => 'Finance & Accounting',
    'research_consultancy'   => 'Research & Consultancy',
    'procurement'            => 'Procurement',
    'human_resource'         => 'Human Resource',
]);

define('SECTIONS', [
    'ict'                    => [],
    'hospitality'            => [
        'catering'           => 'Catering Section',
        'hospitality'        => 'Hospitality Section',
    ],
    'training'               => [
        'library'            => 'Library Section',
        'examinations'       => 'Examinations Section',
        'business_dev'       => 'Business Development Section',
        'admissions'         => 'Admissions Section',
    ],
    'administration_planning' => [
        'security'           => 'Security Section',
        'maintenance'        => 'Maintenance Section',
    ],
    'finance_accounting'     => [
        'accounting'         => 'Accounting Section',
        'finance'            => 'Finance Section',
    ],
    'research_consultancy'   => [],
    'procurement'            => [],
    'human_resource'         => [
        'registry'           => 'Registry Section',
        'communication'      => 'Communication Section',
        'telephone_exchange' => 'Telephone Exchange Section',
    ],
]);

define('ROLES', [
    'staff'           => 'Staff',
    'hod'             => 'HoD / HoS / Team Leader',
    'deputy_director' => 'Deputy Director',
    'director'        => 'Director',
    'admin'           => 'Administrator',
]);

define('STATUSES', [
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
    'delayed'     => 'Delayed',
    'on_track'    => 'On Track',
    'pending'     => 'Pending',
    'scheduled'   => 'Scheduled',
    'cancelled'   => 'Cancelled',
]);

define('TASK_STATUSES', [
    'pending'     => 'Pending',
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
    'overdue'     => 'Overdue',
]);

define('MAX_ACTIVITIES', 10);
define('DB_DATE_FORMAT', 'Y-m-d');
define('DISPLAY_DATE_FORMAT', 'd M Y');