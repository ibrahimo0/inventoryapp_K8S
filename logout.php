<?php
/*
 * Logout script for the Mini Inventory System.
 * Clears the session and redirects to the login page.
 */

session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit();