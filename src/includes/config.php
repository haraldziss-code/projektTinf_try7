<?php
/**
 * Sportgeräteverwaltung BKT - Datenbank-Konfiguration
 * Konfigurationsdatei für die Datenbankverbindung und Systemeinstellungen
 */

// Verhindern des direkten Zugriffs
if (!defined('SECURE_ACCESS')) {
    die('Direkter Zugriff ist nicht erlaubt');
}

// Datenbank-Konfiguration
define('DB_HOST', 'database');
define('DB_NAME', 'sportgeraete');
define('DB_USER', 'sportgeraete_user');
define('DB_PASSWORD', 'sportgeraete_password_456');
define('DB_CHARSET', 'utf8mb4');

// Session-Konfiguration
define('SESSION_LIFETIME', 7200); // 2 Stunden in Sekunden
define('SESSION_NAME', 'sportgeraete_session');

// System-Konfiguration
define('APP_NAME', 'Sportgeräteverwaltung BKT');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Europe/Berlin');

// Sicherheitseinstellungen
define('HASH_COST', 12); // bcrypt cost factor
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 Minuten

// Fehleranzeige (nur in Entwicklung)
define('DEBUG_MODE', true);
define('ERROR_REPORTING', E_ALL);

// Zeitzone setzen
date_default_timezone_set(TIMEZONE);

// Fehlerberichteinstellungen
if (DEBUG_MODE) {
    error_reporting(ERROR_REPORTING);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Datenbankverbindung herstellen
function getDatabaseConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
            } else {
                die("System derzeit nicht verfügbar. Bitte versuchen Sie es später erneut.");
            }
        }
    }

    return $pdo;
}

// Hilfsfunktionen für SQL
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Query fehlgeschlagen: " . $e->getMessage() . "<br>SQL: " . $sql);
        } else {
            error_log("Database error: " . $e->getMessage());
            die("Datenbankfehler aufgetreten.");
        }
    }
}

function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

function insertAndGetId($sql, $params = []) {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

// Session-Management
function secureSessionStart() {
    // Sichere Session-Konfiguration
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Auf 1 setzen, wenn HTTPS verwendet wird
    ini_set('session.cookie_samesite', 'Strict');

    // Session-Name setzen
    session_name(SESSION_NAME);

    // Session starten
    session_start();

    // Session-Regeneration gegen Session-Fixing
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    // Session-Timeout prüfen
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

// CSRF-Schutz
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || hash_equals($_SESSION['csrf_token'], $token) === false) {
        die('CSRF-Token ungültig. Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
    }
    return true;
}

// Eingabe-Validierung und -Säuberung
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePositiveInteger($value) {
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

// Logging-Funktion
function logActivity($action, $details = '') {
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)";

    try {
        executeQuery($sql, [$userId, $action, $details, $ip, $userAgent]);
    } catch (PDOException $e) {
        error_log("Logging failed: " . $e->getMessage());
    }
}

// Formatierungsfunktionen
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

function formatDateTime($dateTime, $format = 'd.m.Y H:i') {
    if (empty($dateTime) || $dateTime === '0000-00-00 00:00:00') {
        return '-';
    }
    $dateObj = new DateTime($dateTime);
    return $dateObj->format($format);
}

function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

// Status-Farben und Icons
function getStatusBadge($status) {
    $badges = [
        'aktiv' => '<span class="badge badge-success">✓ Aktiv</span>',
        'in Reparatur' => '<span class="badge badge-warning">⚠ In Reparatur</span>',
        'ausgemustert' => '<span class="badge badge-danger">✗ Ausgemustert</span>',
        'bestanden' => '<span class="badge badge-success">✓ Bestanden</span>',
        'nicht bestanden' => '<span class="badge badge-danger">✗ Nicht bestanden</span>',
        'mit Auflagen' => '<span class="badge badge-warning">⚠ Mit Auflagen</span>'
    ];

    return $badges[$status] ?? '<span class="badge badge-secondary">?</span>';
}

// Pagination-Hilfsfunktionen
function getPaginationData($totalItems, $itemsPerPage = 20, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;

    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// Error-Handling
function handleError($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $error_type = $error_types[$errno] ?? 'Unknown Error';

    if (DEBUG_MODE) {
        echo "<div class='alert alert-danger'>
                <strong>$error_type:</strong> $errstr<br>
                <small>Datei: $errfile, Zeile: $errline</small>
              </div>";
    } else {
        error_log("PHP Error [$error_type]: $errstr in $errfile on line $errline");
        echo "<div class='alert alert-danger'>
                Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.
              </div>";
    }

    return true;
}

// Error-Handler setzen
set_error_handler('handleError');
?>