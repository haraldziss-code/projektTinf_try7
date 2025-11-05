<?php
/**
 * Sportgeräteverwaltung BKT - Logout
 * Abmeldeseite mit Session-Bereinigung
 */

session_start();

define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Benutzer abmelden
logoutUser();

// Zur Login-Seite mit Erfolgsmeldung weiterleiten
header('Location: login.php?logout=1');
exit;
?>