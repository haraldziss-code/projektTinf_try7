<?php
/**
 * Sportgeräteverwaltung BKT - Login-Seite
 * Anmeldeseite für das Sportgeräteverwaltungssystem
 */

session_start();

define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Wenn bereits angemeldet, zum Dashboard weiterleiten
if (isLoggedIn() && isSessionValid()) {
    header('Location: index.php');
    exit;
}

// Formularverarbeitung
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Bitte geben Sie Benutzername und Passwort ein.';
    } else {
        $result = loginUser($username, $password);

        if ($result['success']) {
            // Zur ursprünglich angeforderten Seite oder zum Dashboard weiterleiten
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// URL-Parameter verarbeiten
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.';
}

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success = 'Sie wurden erfolgreich abgemeldet.';
}

if (isset($_GET['error']) && $_GET['error'] == 'no_permission') {
    $error = 'Sie haben keine Berechtigung für diese Seite.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Anmeldung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .login-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .login-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .footer-info {
            text-align: center;
            color: white;
            margin-top: 2rem;
            font-size: 0.9rem;
        }

        .version-info {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏃 Sportgeräteverwaltung</h1>
            <p>Berufskolleg für Technik</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>❌ Fehler:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <strong>✅</strong> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username"
                           placeholder="Benutzername" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label for="username">Benutzername</label>
                </div>

                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Passwort" required>
                    <label for="password">Passwort</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Angemeldet bleiben
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100">
                    Anmelden
                </button>
            </form>

            <hr class="my-4">

            <div class="text-center">
                <small class="text-muted">
                    <a href="#" onclick="showPasswordReset()" class="text-decoration-none">
                        Passwort vergessen?
                    </a>
                </small>
            </div>

            <div class="mt-3 text-center">
                <small class="text-muted">
                    Demo-Zugänge:<br>
                    <strong>admin</strong> / admin123 (Admin)<br>
                    <strong>lehrer1</strong> / admin123 (Lehrer)<br>
                    <strong>hausmeister</strong> / admin123 (Hausmeister)
                </small>
            </div>
        </div>
    </div>

    <div class="footer-info">
        <p>&copy; <?php echo date('Y'); ?> Berufskolleg für Technik - Sportgeräteverwaltung</p>
    </div>

    <div class="version-info">
        Version <?php echo APP_VERSION; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Formular-Autovervollständigung für Demo
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            // Demo-Zugänge als Vorschläge
            usernameInput.addEventListener('focus', function() {
                if (this.value === '') {
                    this.placeholder = 'z.B. admin, lehrer1, hausmeister';
                }
            });
        });

        function showPasswordReset() {
            alert('Passwort-Reset-Funktion\n\nBitte wenden Sie sich an den Systemadministrator,\num Ihr Passwort zurückzusetzen.\n\nE-Mail: admin@bkt-sport.de');
        }

        // Formular-Validierung
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (username.length < 2) {
                e.preventDefault();
                alert('Bitte geben Sie einen gültigen Benutzernamen ein.');
                return false;
            }

            if (password.length < 4) {
                e.preventDefault();
                alert('Bitte geben Sie ein gültiges Passwort ein.');
                return false;
            }
        });
    </script>
</body>
</html>