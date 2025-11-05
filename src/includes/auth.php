<?php
/**
 * Sportgeräteverwaltung BKT - Authentifizierung
 * Funktionen für Login, Logout und Berechtigungsprüfung
 */

define('SECURE_ACCESS', true);
require_once 'config.php';

/**
 * Benutzeranmeldung
 * @param string $username Benutzername
 * @param string $password Passwort
 * @return array Ergebnis mit success boolean und message
 */
function loginUser($username, $password) {
    $username = sanitizeInput($username);

    // Login-Versuche prüfen
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        if (isset($_SESSION['last_attempt']) && (time() - $_SESSION['last_attempt']) < LOGIN_TIMEOUT) {
            return [
                'success' => false,
                'message' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte warten Sie ' . ceil((LOGIN_TIMEOUT - (time() - $_SESSION['last_attempt'])) / 60) . ' Minuten.'
            ];
        } else {
            // Timeout abgelaufen, Versuche zurücksetzen
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);
        }
    }

    // Benutzer in Datenbank suchen
    $sql = "SELECT id, username, password_hash, full_name, email, role, is_active
            FROM users
            WHERE username = ? AND is_active = 1";

    $user = fetchOne($sql, [$username]);

    if (!$user) {
        // Fehlgeschlagener Versuch zählen
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt'] = time();

        return [
            'success' => false,
            'message' => 'Benutzername oder Passwort falsch.'
        ];
    }

    // Passwort überprüfen
    if (!password_verify($password, $user['password_hash'])) {
        // Fehlgeschlagener Versuch zählen
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt'] = time();

        return [
            'success' => false,
            'message' => 'Benutzername oder Passwort falsch.'
        ];
    }

    // Erfolgreiche Anmeldung
    unset($_SESSION['login_attempts']);
    unset($_SESSION['last_attempt']);

    // Session-Daten setzen
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Session-ID in Datenbank speichern
    $sessionId = session_id();
    $sql = "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL " . (SESSION_LIFETIME / 3600) . " HOUR))
            ON DUPLICATE KEY UPDATE
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            expires_at = VALUES(expires_at),
            last_activity = NOW()";

    executeQuery($sql, [
        $sessionId,
        $user['id'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    // Aktivität loggen
    logActivity('login', 'Benutzer angemeldet: ' . $username);

    return [
        'success' => true,
        'message' => 'Anmeldung erfolgreich.',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ];
}

/**
 * Benutzerabmeldung
 */
function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        // Aktivität loggen
        logActivity('logout', 'Benutzer abgemeldet: ' . $_SESSION['username']);

        // Session aus Datenbank entfernen
        $sessionId = session_id();
        $sql = "DELETE FROM user_sessions WHERE id = ?";
        executeQuery($sql, [$sessionId]);
    }

    // Session-Daten löschen
    $_SESSION = [];

    // Session-Cookie löschen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Session zerstören
    session_destroy();
}

/**
 * Prüfen, ob Benutzer angemeldet ist
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Aktuellen Benutzer abrufen
 * @return array|null Benutzerdaten oder null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}

/**
 * Prüfen, ob aktuelle Seite geschützt ist und ggf. zum Login weiterleiten
 * @param array $allowedRoles Erlaubte Rollen (leeres Array = alle angemeldeten Benutzer)
 */
function requireLogin($allowedRoles = []) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }

    // Rollenprüfung, falls erforderlich
    if (!empty($allowedRoles)) {
        $currentUser = getCurrentUser();
        if (!in_array($currentUser['role'], $allowedRoles)) {
            header('Location: index.php?error=no_permission');
            exit;
        }
    }

    // Session-Timeout prüfen
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        logoutUser();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Passwort-Hash erstellen
 * @param string $password Klartext-Passwort
 * @return string Passwort-Hash
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
}

/**
 * Passwort stark genug?
 * @param string $password Passwort
 * @return array Validierungsergebnis
 */
function validatePasswordStrength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Passwort muss mindestens einen Großbuchstaben enthalten.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Passwort muss mindestens einen Kleinbuchstaben enthalten.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Passwort muss mindestens eine Ziffer enthalten.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Berechtigungsprüfung für bestimmte Aktionen
 * @param string $action Aktion
 * @param int|null $equipmentId Geräte-ID (optional)
 * @return bool
 */
function hasPermission($action, $equipmentId = null) {
    if (!isLoggedIn()) {
        return false;
    }

    $currentUser = getCurrentUser();
    $userRole = $currentUser['role'];

    // Admin hat alle Berechtigungen
    if ($userRole === 'Admin') {
        return true;
    }

    switch ($action) {
        case 'view_dashboard':
            return true; // Alle angemeldeten Benutzer

        case 'view_equipment':
        case 'view_inspections':
            return true; // Alle angemeldeten Benutzer

        case 'edit_equipment':
        case 'add_equipment':
            return in_array($userRole, ['Lehrer', 'Hausmeister']);

        case 'delete_equipment':
            return in_array($userRole, ['Lehrer']); // Nur Lehrer können löschen

        case 'add_inspection':
        case 'edit_inspection':
            return in_array($userRole, ['Lehrer', 'Hausmeister']);

        case 'delete_inspection':
            return in_array($userRole, ['Lehrer']);

        case 'edit_small_equipment':
            return in_array($userRole, ['Lehrer', 'Hausmeister']);

        case 'view_reports':
            return true; // Alle angemeldeten Benutzer

        case 'manage_users':
            return $userRole === 'Admin';

        default:
            return false;
    }
}

/**
 * Session bereinigen (abgelaufene Sessions entfernen)
 */
function cleanupExpiredSessions() {
    $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
    executeQuery($sql);
}

/**
 * Prüfen, ob Session noch gültig ist
 * @return bool
 */
function isSessionValid() {
    if (!isLoggedIn()) {
        return false;
    }

    $sessionId = session_id();
    $sql = "SELECT COUNT(*) as count FROM user_sessions WHERE id = ? AND expires_at > NOW()";
    $result = fetchOne($sql, [$sessionId]);

    return $result['count'] > 0;
}

/**
 * Passwort zurücksetzen (Token-basiert)
 * @param string $email E-Mail-Adresse
 * @return array Ergebnis
 */
function requestPasswordReset($email) {
    $email = sanitizeInput($email);

    $sql = "SELECT id, username, full_name FROM users WHERE email = ? AND is_active = 1";
    $user = fetchOne($sql, [$email]);

    if (!$user) {
        // Sicherheit: Immer Erfolg melden, auch wenn E-Mail nicht existiert
        return [
            'success' => true,
            'message' => 'Wenn diese E-Mail-Adresse registriert ist, erhalten Sie eine Anleitung zum Zurücksetzen Ihres Passworts.'
        ];
    }

    // Token generieren
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 Stunde gültig

    // Token in Datenbank speichern (würde zusätzliche Tabelle benötigen)
    // Für diese Implementierung vereinfacht wir und loggen nur den Versuch
    logActivity('password_reset_request', 'Passwort-Reset angefordert für: ' . $email);

    return [
        'success' => true,
        'message' => 'Wenn diese E-Mail-Adresse registriert ist, erhalten Sie eine Anleitung zum Zurücksetzen Ihres Passworts.'
        // In einer echten Implementierung würde hier eine E-Mail gesendet
    ];
}

/**
 * Automatische Session-Bereinigung bei Seitenaufruf
 */
cleanupExpiredSessions();
?>