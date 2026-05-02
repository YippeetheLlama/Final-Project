<?php
require_once __DIR__ . "/functions.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

require_valid_csrf("index.php");

session_unset();
session_destroy();

header("Location: login.php");
exit;
?>
