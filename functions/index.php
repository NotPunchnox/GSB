<?php
// Auth
include(__DIR__ . "/auth/check-login.php");
include(__DIR__ . "/auth/login.php");

// Cookies
include(__DIR__ . "/cookies/create.php");
include(__DIR__ . "/cookies/remove.php");

// JS
include(__DIR__ . "/js/alert.php");
include(__DIR__ . "/js/logs.php");
include(__DIR__ . "/js/reload.php");

// SQL
include(__DIR__ . "/SQL/sql-request-insert.php");
include(__DIR__ . "/SQL/sql-request.php");
?>