<?php
require_once __DIR__ . '/../includes/admin_functions.php';

if (!empty($_SESSION['admin_logged_in'])) {
    record_activity_log('Signed out');
}
session_unset();
session_destroy();
header('Location: /login.php');
exit;
