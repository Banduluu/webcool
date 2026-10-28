<?php
require_once __DIR__ . '/../includes/admin_functions.php';

if (!empty($_SESSION['inspector_logged_in'])) {
    record_activity_log('Signed out');
}
$_SESSION['inspector_logged_in'] = false;
unset($_SESSION['inspector_logged_in'], $_SESSION['inspector_username']);
header('Location: /login.php');
exit;
