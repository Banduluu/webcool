<?php
require_once __DIR__ . '/../includes/admin_functions.php';

session_unset();
session_destroy();
header('Location: /login.php');
exit;
