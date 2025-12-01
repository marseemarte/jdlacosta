<?php
require_once __DIR__ . '/config.php';
init_session();
$_SESSION = [];
session_destroy();
header('Location: ../login.php');
exit;
?>