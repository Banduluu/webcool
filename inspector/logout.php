<?php
require_once __DIR__ . '/../includes/admin_functions.php';

$_SESSION['inspector_logged_in'] = false;
unset($_SESSION['inspector_logged_in'], $_SESSION['inspector_username']);
header('Location: /login.php');
exit;
