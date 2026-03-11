<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use KSG\Auth;

Auth::startSession();
Auth::logout();

header('Location: /login.php');
exit;
