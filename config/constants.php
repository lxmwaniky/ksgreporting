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
    'administration_planning'  => 'Administration & Planning',
    'ict'                      => 'ICT',
    'finance'                  => 'Finance',
    'human_resource'           => 'Human Resource',
    'academic'                 => 'Academic Affairs',
    'procurement'              => 'Procurement',
    'research'                 => 'Research & Consultancy',
    'library'                  => 'Library Services',
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

define('MAX_ACTIVITIES', 10);
define('DB_DATE_FORMAT', 'Y-m-d');
define('DISPLAY_DATE_FORMAT', 'd M Y');

