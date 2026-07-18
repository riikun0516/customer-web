<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    header('Location: cases.php');
} else {
    header('Location: login.php');
}
exit;
