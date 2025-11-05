<?php
/**
 * Sportgeräteverwaltung BKT - Inspektions-Management
 * Seite zur Verwaltung von Geräte-Inspektionen
 */

define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Login und Berechtigungsprüfung
requireLogin(['Lehrer', 'Hausmeister', 'Admin']);

// Aktuellen Benutzer abrufen
$currentUser = getCurrentUser();

// Parameter abrufen
$action = sanitizeInput($_GET['action'] ?? 'list');
$inspectionId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$equipmentId = filter_var($_GET['equipment_id'] ?? 0, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$search = sanitizeInput($_GET['search'] ?? '');
$filterResult = sanitizeInput($_GET['filter_result'] ?? '');
$filterDateFrom = sanitizeInput($_GET['filter_date_from'] ?? '');
$filterDateTo = sanitizeInput($_GET['filter_date_to'] ?? '');

// Pagination
$itemsPerPage = 20;
$offset = ($page - 1) * $itemsPerPage;

// Formularverarbeitung
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token validieren
    validateCSRFToken($_POST['csrf_token'] ?? '');

    $postAction = sanitizeInput($_POST['action'] ?? '');

    if ($postAction === 'add' && hasPermission('add_inspection')) {
        $inspection = [
            'equipment_id' => filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT),
            'inspection_date' => sanitizeInput($_POST['inspection_date'] ?? ''),
            'inspector_name' => sanitizeInput($_POST['inspector_name'] ?? ''),
            'result' => sanitizeInput($_POST['result'] ?? ''),
            'notes' => sanitizeInput($_POST['notes'] ?? ''),
            'next_inspection' => sanitizeInput($_POST['next_inspection'] ?? '')
        ];

        // Validierung
        if (!$inspection['equipment_id']) {
            $errors[] = 'Gerät ist erforderlich.';
        }
        if (empty($inspection['inspection_date'])) {
            $errors[] = 'Inspektionsdatum ist erforderlich.';
        }
        if (!validateDate($inspection['inspection_date'])) {
            $errors[] = 'Ungültiges Inspektionsdatum.';
        }
        if (empty($inspection['inspector_name'])) {
            $errors[] = 'Prüfer ist erforderlich.';
        }
        if (empty($inspection['result'])) {
            $errors[] = 'Ergebnis ist erforderlich.';
        }
        if (empty($inspection['next_inspection'])) {
            $errors[] = 'Nächste Inspektion ist erforderlich.';
        }
        if (!validateDate($inspection['next_inspection'])) {
            $errors[] = 'Ungültiges Datum für nächste Inspektion.';
        }

        if (empty($errors)) {
            $sql = "INSERT INTO inspections
                    (equipment_id, inspection_date, inspector_name, result, notes, next_inspection, inspected_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $inspection['equipment_id'],
                $inspection['inspection_date'],
                $inspection['inspector_name'],
                $inspection['result'],
                $inspection['notes'],
                $inspection['next_inspection'],
                $currentUser['id']
            ];

            executeQuery($sql, $params);

            // Gerät-Daten aktualisieren (wird durch Trigger erledigt, aber zur Sicherheit hier)
            executeQuery("UPDATE large_equipment SET last_inspection = ?, next_inspection_due = ? WHERE id = ?",
                [$inspection['inspection_date'], $inspection['next_inspection'], $inspection['equipment_id']]);

            // Gerätename für Log holen
            $equipment = fetchOne("SELECT inventory_number, equipment_name FROM large_equipment WHERE id = ?",
                [$inspection['equipment_id']]);

            logActivity('add_inspection', "Inspektion durchgeführt: {$equipment['inventory_number']} - {$equipment['equipment_name']} (Ergebnis: {$inspection['result']})");

            header('Location: inspections.php?message=added');
            exit;
        }
    } elseif ($postAction === 'edit' && hasPermission('edit_inspection')) {
        $inspectionId = filter_var($_POST['inspection_id'], FILTER_VALIDATE_INT);
        if (!$inspectionId) {
            $errors[] = 'Ungültige Inspektions-ID.';
        } else {
            $inspection = [
                'equipment_id' => filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT),
                'inspection_date' => sanitizeInput($_POST['inspection_date'] ?? ''),
                'inspector_name' => sanitizeInput($_POST['inspector_name'] ?? ''),
                'result' => sanitizeInput($_POST['result'] ?? ''),
                'notes' => sanitizeInput($_POST['notes'] ?? ''),
                'next_inspection' => sanitizeInput($_POST['next_inspection'] ?? '')
            ];

            // Validierung
            if (!$inspection['equipment_id']) {
                $errors[] = 'Gerät ist erforderlich.';
            }
            if (empty($inspection['inspection_date'])) {
                $errors[] = 'Inspektionsdatum ist erforderlich.';
            }
            if (!validateDate($inspection['inspection_date'])) {
                $errors[] = 'Ungültiges Inspektionsdatum.';
            }
            if (empty($inspection['inspector_name'])) {
                $errors[] = 'Prüfer ist erforderlich.';
            }
            if (empty($inspection['result'])) {
                $errors[] = 'Ergebnis ist erforderlich.';
            }
            if (empty($inspection['next_inspection'])) {
                $errors[] = 'Nächste Inspektion ist erforderlich.';
            }
            if (!validateDate($inspection['next_inspection'])) {
                $errors[] = 'Ungültiges Datum für nächste Inspektion.';
            }

            if (empty($errors)) {
                $sql = "UPDATE inspections SET
                        equipment_id = ?, inspection_date = ?, inspector_name = ?,
                        result = ?, notes = ?, next_inspection = ?
                        WHERE id = ?";

                $params = [
                    $inspection['equipment_id'],
                    $inspection['inspection_date'],
                    $inspection['inspector_name'],
                    $inspection['result'],
                    $inspection['notes'],
                    $inspection['next_inspection'],
                    $inspectionId
                ];

                executeQuery($sql, $params);

                // Gerät-Daten aktualisieren (nur wenn dies die aktuellste Inspektion ist)
                $sql = "SELECT COUNT(*) as count FROM inspections
                        WHERE equipment_id = ? AND inspection_date > ?";
                $result = fetchOne($sql, [$inspection['equipment_id'], $inspection['inspection_date']]);

                if ($result['count'] == 0) {
                    executeQuery("UPDATE large_equipment SET last_inspection = ?, next_inspection_due = ? WHERE id = ?",
                        [$inspection['inspection_date'], $inspection['next_inspection'], $inspection['equipment_id']]);
                }

                $equipment = fetchOne("SELECT inventory_number, equipment_name FROM large_equipment WHERE id = ?",
                    [$inspection['equipment_id']]);

                logActivity('edit_inspection', "Inspektion bearbeitet: {$equipment['inventory_number']} - {$equipment['equipment_name']} (Ergebnis: {$inspection['result']})");

                header('Location: inspections.php?message=updated');
                exit;
            }
        }
    } elseif ($postAction === 'delete' && hasPermission('delete_inspection')) {
        $inspectionId = filter_var($_POST['inspection_id'], FILTER_VALIDATE_INT);
        if ($inspectionId) {
            // Inspektion abrufen für Log
            $sql = "SELECT i.*, le.inventory_number, le.equipment_name
                    FROM inspections i
                    JOIN large_equipment le ON i.equipment_id = le.id
                    WHERE i.id = ?";
            $inspection = fetchOne($sql, [$inspectionId]);

            if ($inspection) {
                executeQuery("DELETE FROM inspections WHERE id = ?", [$inspectionId]);

                // Gerät-Daten aktualisieren (nächste aktuellste Inspektion finden)
                $sql = "SELECT * FROM inspections WHERE equipment_id = ? ORDER BY inspection_date DESC LIMIT 1";
                $latestInspection = fetchOne($sql, [$inspection['equipment_id']]);

                if ($latestInspection) {
                    executeQuery("UPDATE large_equipment SET last_inspection = ?, next_inspection_due = ? WHERE id = ?",
                        [$latestInspection['inspection_date'], $latestInspection['next_inspection'], $inspection['equipment_id']]);
                } else {
                    executeQuery("UPDATE large_equipment SET last_inspection = NULL, next_inspection_due = NULL WHERE id = ?",
                        [$inspection['equipment_id']]);
                }

                logActivity('delete_inspection', "Inspektion gelöscht: {$inspection['inventory_number']} - {$inspection['equipment_name']} vom {$inspection['inspection_date']}");
                header('Location: inspections.php?message=deleted');
                exit;
            }
        }
    }
}

// Daten abrufen
$inspection = null;
$inspectionList = [];
$totalItems = 0;
$equipmentList = [];

if ($action === 'add' || ($action === 'edit' && $inspectionId)) {
    // Geräte für Dropdown abrufen
    $equipmentList = fetchAll("SELECT id, inventory_number, equipment_name, hall_garage
                              FROM large_equipment
                              WHERE status = 'aktiv'
                              ORDER BY hall_garage, equipment_name");

    if ($action === 'edit' && $inspectionId) {
        $sql = "SELECT i.*, le.inventory_number, le.equipment_name, le.hall_garage
                FROM inspections i
                JOIN large_equipment le ON i.equipment_id = le.id
                WHERE i.id = ?";
        $inspection = fetchOne($sql, [$inspectionId]);
        if (!$inspection) {
            header('Location: inspections.php?error=not_found');
            exit;
        }
    } elseif ($action === 'add' && $equipmentId) {
        // Gerät vorauswählen, falls ID übergeben wurde
        $equipment = fetchOne("SELECT id, inventory_number, equipment_name, hall_garage
                              FROM large_equipment
                              WHERE id = ? AND status = 'aktiv'", [$equipmentId]);
        if ($equipment) {
            $inspection = [
                'equipment_id' => $equipment['id'],
                'equipment_name' => $equipment['equipment_name'],
                'inventory_number' => $equipment['inventory_number'],
                'hall_garage' => $equipment['hall_garage'],
                'inspection_date' => date('Y-m-d'),
                'inspector_name' => $currentUser['full_name'],
                'result' => 'bestanden'
            ];
        }
    }
} else {
    // Liste abrufen
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $whereConditions[] = "(le.inventory_number LIKE ? OR le.equipment_name LIKE ? OR i.inspector_name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($filterResult)) {
        $whereConditions[] = "i.result = ?";
        $params[] = $filterResult;
    }

    if (!empty($filterDateFrom)) {
        $whereConditions[] = "i.inspection_date >= ?";
        $params[] = $filterDateFrom;
    }

    if (!empty($filterDateTo)) {
        $whereConditions[] = "i.inspection_date <= ?";
        $params[] = $filterDateTo;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Gesamtanzahl für Pagination
    $countSql = "SELECT COUNT(*) as count FROM inspections i
                 JOIN large_equipment le ON i.equipment_id = le.id
                 $whereClause";
    $result = fetchOne($countSql, $params);
    $totalItems = $result['count'];

    // Daten abrufen
    $sql = "SELECT i.*, le.inventory_number, le.equipment_name, le.hall_garage,
                   u.full_name as inspected_by_user
            FROM inspections i
            JOIN large_equipment le ON i.equipment_id = le.id
            LEFT JOIN users u ON i.inspected_by = u.id
            $whereClause
            ORDER BY i.inspection_date DESC, i.created_at DESC
            LIMIT ? OFFSET ?";

    $listParams = array_merge($params, [$itemsPerPage, $offset]);
    $inspectionList = fetchAll($sql, $listParams);
}

// Pagination-Daten
$pagination = getPaginationData($totalItems, $itemsPerPage, $page);

// URL-Parameter verarbeiten
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $success = 'Inspektion erfolgreich hinzugefügt.';
            break;
        case 'updated':
            $success = 'Inspektion erfolgreich aktualisiert.';
            break;
        case 'deleted':
            $success = 'Inspektion erfolgreich gelöscht.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_found':
            $errors[] = 'Inspektion nicht gefunden.';
            break;
        case 'permission':
            $errors[] = 'Keine Berechtigung für diese Aktion.';
            break;
    }
}

// Statistik für Dashboard
$stats = [];
$stats['total_inspections'] = fetchOne("SELECT COUNT(*) as count FROM inspections")['count'];
$stats['this_year'] = fetchOne("SELECT COUNT(*) as count FROM inspections WHERE YEAR(inspection_date) = YEAR(CURDATE())")['count'];
$stats['this_month'] = fetchOne("SELECT COUNT(*) as count FROM inspections WHERE MONTH(inspection_date) = MONTH(CURDATE()) AND YEAR(inspection_date) = YEAR(CURDATE())")['count'];
$stats['passed'] = fetchOne("SELECT COUNT(*) as count FROM inspections WHERE result = 'bestanden'")['count'];
$stats['failed'] = fetchOne("SELECT COUNT(*) as count FROM inspections WHERE result = 'nicht bestanden'")['count'];
$stats['conditional'] = fetchOne("SELECT COUNT(*) as count FROM inspections WHERE result = 'mit Auflagen'")['count'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspektions-Management | <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .inspection-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }

        .stat-card.total { border-left-color: #007bff; }
        .stat-card.passed { border-left-color: #28a745; }
        .stat-card.failed { border-left-color: #dc3545; }
        .stat-card.conditional { border-left-color: #ffc107; }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .inspection-card {
            border-left: 4px solid;
            transition: all 0.2s ease;
        }

        .inspection-card.passed { border-left-color: #28a745; }
        .inspection-card.failed { border-left-color: #dc3545; }
        .inspection-card.conditional { border-left-color: #ffc107; }

        .overdue-inspection {
            background-color: #f8d7da;
            border-left-color: #dc3545;
        }

        .upcoming-inspection {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }

        .calendar-view {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-box me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="large_equipment.php">
                            <i class="bi bi-box me-1"></i> Großgeräte
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="small_equipment.php">
                            <i class="bi bi-basket me-1"></i> Kleingeräte
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="inspections.php">
                            <i class="bi bi-clipboard-check me-1"></i> Inspektionen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-file-earmark-bar-graph me-1"></i> Berichte
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($currentUser['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h2>
                    <?php if ($action === 'add'): ?>
                        <i class="bi bi-clipboard-plus me-2"></i>Neue Inspektion
                    <?php elseif ($action === 'edit'): ?>
                        <i class="bi bi-clipboard-pencil me-2"></i>Inspektion bearbeiten
                    <?php else: ?>
                        <i class="bi bi-clipboard-check me-2"></i>Inspektions-Management
                    <?php endif; ?>
                </h2>
            </div>
            <div class="col-md-6 text-md-end">
                <?php if ($action === 'list'): ?>
                    <?php if (hasPermission('add_inspection')): ?>
                        <a href="inspections.php?action=add" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Neue Inspektion
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="inspections.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Zurück zur Liste
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Meldungen -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Fehler:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Erfolg:</strong> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'add' || $action === 'edit'): ?>
            <!-- Formular -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Inspektionsdaten</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="<?php echo $action === 'add' ? 'add' : 'edit'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="inspection_id" value="<?php echo $inspection['id']; ?>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="equipment_id" class="form-label">Gerät *</label>
                                            <select class="form-select" id="equipment_id" name="equipment_id" required onchange="updateEquipmentInfo()">
                                                <option value="">Bitte wählen...</option>
                                                <?php foreach ($equipmentList as $equipment): ?>
                                                    <option value="<?php echo $equipment['id']; ?>"
                                                            <?php echo (isset($inspection['equipment_id']) && $inspection['equipment_id'] == $equipment['id']) ? 'selected' : ''; ?>
                                                            data-inventory="<?php echo htmlspecialchars($equipment['inventory_number']); ?>"
                                                            data-name="<?php echo htmlspecialchars($equipment['equipment_name']); ?>"
                                                            data-hall="<?php echo htmlspecialchars($equipment['hall_garage']); ?>">
                                                        <?php echo htmlspecialchars($equipment['inventory_number']); ?> - <?php echo htmlspecialchars($equipment['equipment_name']); ?>
                                                        (<?php echo htmlspecialchars($equipment['hall_garage']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="inspection_date" class="form-label">Inspektionsdatum *</label>
                                            <input type="date" class="form-control" id="inspection_date" name="inspection_date"
                                                   value="<?php echo htmlspecialchars($inspection['inspection_date'] ?? date('Y-m-d')); ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="inspector_name" class="form-label">Prüfer *</label>
                                            <input type="text" class="form-control" id="inspector_name" name="inspector_name"
                                                   value="<?php echo htmlspecialchars($inspection['inspector_name'] ?? $currentUser['full_name']); ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="result" class="form-label">Ergebnis *</label>
                                            <select class="form-select" id="result" name="result" required onchange="updateResultFields()">
                                                <option value="">Bitte wählen...</option>
                                                <option value="bestanden" <?php echo (isset($inspection['result']) && $inspection['result'] === 'bestanden') ? 'selected' : ''; ?>>Bestanden</option>
                                                <option value="nicht bestanden" <?php echo (isset($inspection['result']) && $inspection['result'] === 'nicht bestanden') ? 'selected' : ''; ?>>Nicht bestanden</option>
                                                <option value="mit Auflagen" <?php echo (isset($inspection['result']) && $inspection['result'] === 'mit Auflagen') ? 'selected' : ''; ?>>Mit Auflagen</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="next_inspection" class="form-label">Nächste Inspektion *</label>
                                    <input type="date" class="form-control" id="next_inspection" name="next_inspection"
                                           value="<?php echo htmlspecialchars($inspection['next_inspection'] ?? ''); ?>"
                                           required>
                                    <small class="text-muted">Standardmäßig 1 Jahr ab Inspektionsdatum</small>
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notizen</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4"
                                              placeholder="Beschreiben Sie den Zustand des Geräts, festgestellte Mängel, durchgeführte Reparaturen etc."><?php echo htmlspecialchars($inspection['notes'] ?? ''); ?></textarea>
                                </div>

                                <!-- Geräte-Info -->
                                <div id="equipmentInfo" class="alert alert-info" style="display: none;">
                                    <h6><i class="bi bi-info-circle me-2"></i>Geräteinformationen</h6>
                                    <div id="equipmentDetails"></div>
                                </div>

                                <div class="text-end">
                                    <a href="inspections.php" class="btn btn-outline-secondary me-2">Abbrechen</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>
                                        <?php echo $action === 'add' ? 'Hinzufügen' : 'Speichern'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Prüf-Checkliste</h5>
                        </div>
                        <div class="card-body">
                            <h6>Allgemeine Prüfpunkte:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle text-success me-2"></i>Overallheit und Stabilität</li>
                                <li><i class="bi bi-check-circle text-success me-2"></i>Oberflächenbeschaffenheit</li>
                                <li><i class="bi bi-check-circle text-success me-2"></i>Befestigungen und Verbindungen</li>
                                <li><i class="bi bi-check-circle text-success me-2"></i>Sicherheitsvorkehrungen</li>
                                <li><i class="bi bi-check-circle text-success me-2"></i>Funktionstüchtigkeit</li>
                            </ul>

                            <h6 class="mt-3">Bewertungskriterien:</h6>
                            <div class="mb-2">
                                <span class="badge bg-success">Bestanden</span>
                                <small class="text-muted d-block">Keine Mängel, sofort einsatzbereit</small>
                            </div>
                            <div class="mb-2">
                                <span class="badge bg-warning">Mit Auflagen</span>
                                <small class="text-muted d-block">Kleine Mängel, die behoben werden sollten</small>
                            </div>
                            <div class="mb-2">
                                <span class="badge bg-danger">Nicht bestanden</span>
                                <small class="text-muted d-block">Erhebliche Mängel, Reparatur erforderlich</small>
                            </div>

                            <div class="mt-3">
                                <small class="text-muted">
                                    <strong>Wichtig:</strong> Dokumentieren Sie alle festgestellten Mängel genau in den Notizen.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Statistik-Karten -->
            <div class="inspection-stats mb-4">
                <div class="stat-card total">
                    <div class="stat-number text-primary"><?php echo $stats['total_inspections']; ?></div>
                    <div class="stat-label">Gesamt-Inspektionen</div>
                </div>
                <div class="stat-card passed">
                    <div class="stat-number text-success"><?php echo $stats['passed']; ?></div>
                    <div class="stat-label">Bestanden</div>
                </div>
                <div class="stat-card conditional">
                    <div class="stat-number text-warning"><?php echo $stats['conditional']; ?></div>
                    <div class="stat-label">Mit Auflagen</div>
                </div>
                <div class="stat-card failed">
                    <div class="stat-number text-danger"><?php echo $stats['failed']; ?></div>
                    <div class="stat-label">Nicht bestanden</div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-3">
                            <div class="search-input">
                                <input type="text" class="form-control" name="search" placeholder="Suchen..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="filter_result">
                                <option value="">Alle Ergebnisse</option>
                                <option value="bestanden" <?php echo $filterResult === 'bestanden' ? 'selected' : ''; ?>>Bestanden</option>
                                <option value="nicht bestanden" <?php echo $filterResult === 'nicht bestanden' ? 'selected' : ''; ?>>Nicht bestanden</option>
                                <option value="mit Auflagen" <?php echo $filterResult === 'mit Auflagen' ? 'selected' : ''; ?>>Mit Auflagen</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="filter_date_from"
                                   placeholder="Von" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="filter_date_to"
                                   placeholder="Bis" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Filtern
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="inspections.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabelle -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($inspectionList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-check display-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">Keine Inspektionen gefunden</h5>
                            <p class="text-muted">Passen Sie Ihre Filter an oder führen Sie neue Inspektionen durch.</p>
                            <?php if (hasPermission('add_inspection')): ?>
                                <a href="inspections.php?action=add" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Erste Inspektion durchführen
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Gerät</th>
                                        <th>Inventarnr.</th>
                                        <th>Prüfer</th>
                                        <th>Ergebnis</th>
                                        <th>Nächste Prüfung</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inspectionList as $item): ?>
                                        <tr class="inspection-card <?php echo $item['result']; ?>">
                                            <td>
                                                <strong><?php echo formatDate($item['inspection_date']); ?></strong><br>
                                                <small class="text-muted"><?php echo formatDateTime($item['created_at'], 'H:i'); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['equipment_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['hall_garage']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['inventory_number']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($item['inspector_name']); ?>
                                                <?php if ($item['inspected_by_user'] && $item['inspected_by_user'] !== $item['inspector_name']): ?>
                                                    <br><small class="text-muted">(<?php echo htmlspecialchars($item['inspected_by_user']); ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo getStatusBadge($item['result']); ?></td>
                                            <td>
                                                <?php
                                                if ($item['next_inspection']) {
                                                    $nextDate = new DateTime($item['next_inspection']);
                                                    $today = new DateTime();
                                                    $interval = $today->diff($nextDate);
                                                    $daysUntil = $interval->days;

                                                    if ($nextDate < $today) {
                                                        echo '<span class="badge bg-danger">Überfällig</span><br>';
                                                    } elseif ($daysUntil <= 30) {
                                                        echo '<span class="badge bg-warning">' . $daysUntil . ' Tage</span><br>';
                                                    }
                                                    echo formatDate($item['next_inspection']);
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="inspections.php?action=edit&id=<?php echo $item['id']; ?>"
                                                       class="btn btn-outline-primary" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="large_equipment.php?id=<?php echo $item['equipment_id']; ?>"
                                                       class="btn btn-outline-info" title="Gerät anzeigen">
                                                        <i class="bi bi-box"></i>
                                                    </a>
                                                    <?php if (hasPermission('delete_inspection')): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                                onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['inventory_number']); ?>', '<?php echo formatDate($item['inspection_date']); ?>')"
                                                                title="Löschen">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <nav aria-label="Seitennavigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagination['has_previous']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&filter_result=<?php echo urlencode($filterResult); ?>&filter_date_from=<?php echo urlencode($filterDateFrom); ?>&filter_date_to=<?php echo urlencode($filterDateTo); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $startPage = max(1, $pagination['current_page'] - 2);
                                    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);

                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_result=<?php echo urlencode($filterResult); ?>&filter_date_from=<?php echo urlencode($filterDateFrom); ?>&filter_date_to=<?php echo urlencode($filterDateTo); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($pagination['has_next']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&filter_result=<?php echo urlencode($filterResult); ?>&filter_date_from=<?php echo urlencode($filterDateFrom); ?>&filter_date_to=<?php echo urlencode($filterDateTo); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="mt-3 text-muted">
                            <small>
                                Zeige <?php echo count($inspectionList); ?> von <?php echo $pagination['total_items']; ?> Inspektionen
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inspektion löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Möchten Sie diese Inspektion wirklich löschen?</p>
                    <p><strong>Gerät:</strong> <span id="deleteEquipmentInfo"></span></p>
                    <p><strong>Datum:</strong> <span id="deleteInspectionDate"></span></p>
                    <p class="text-warning">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="inspection_id" id="deleteInspectionId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function confirmDelete(inspectionId, equipmentInfo, inspectionDate) {
            document.getElementById('deleteInspectionId').value = inspectionId;
            document.getElementById('deleteEquipmentInfo').textContent = equipmentInfo;
            document.getElementById('deleteInspectionDate').textContent = inspectionDate;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function updateEquipmentInfo() {
            const select = document.getElementById('equipment_id');
            const selectedOption = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('equipmentInfo');
            const detailsDiv = document.getElementById('equipmentDetails');

            if (select.value) {
                const inventory = selectedOption.getAttribute('data-inventory');
                const name = selectedOption.getAttribute('data-name');
                const hall = selectedOption.getAttribute('data-hall');

                detailsDiv.innerHTML = `
                    <p><strong>Inventarnummer:</strong> ${inventory}</p>
                    <p><strong>Gerätename:</strong> ${name}</p>
                    <p><strong>Standort:</strong> ${hall}</p>
                `;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }

        function updateResultFields() {
            const result = document.getElementById('result').value;
            const notesField = document.getElementById('notes');

            if (result === 'mit Auflagen' || result === 'nicht bestanden') {
                notesField.placeholder = 'Bitte beschreiben Sie die festgestellten Mängel und erforderlichen Maßnahmen genau...';
                notesField.classList.add('is-invalid');
            } else {
                notesField.placeholder = 'Beschreiben Sie den Zustand des Geräts...';
                notesField.classList.remove('is-invalid');
            }
        }

        // Automatisch nächste Inspektion berechnen
        document.getElementById('inspection_date')?.addEventListener('change', function() {
            const inspectionDate = new Date(this.value);
            const nextDate = new Date(inspectionDate);
            nextDate.setFullYear(nextDate.getFullYear() + 1);

            const nextField = document.getElementById('next_inspection');
            if (!nextField.value) {
                nextField.value = nextDate.toISOString().split('T')[0];
            }
        });

        // Geräte-Info beim Laden anzeigen
        if (document.getElementById('equipment_id')) {
            updateEquipmentInfo();
        }

        // Ergebnis-Felder aktualisieren
        if (document.getElementById('result')) {
            updateResultFields();
        }

        // Formular-Validierung
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const result = document.getElementById('result').value;
            const notes = document.getElementById('notes').value;

            if ((result === 'mit Auflagen' || result === 'nicht bestanden') && notes.trim().length < 10) {
                e.preventDefault();
                alert('Bei negativen Ergebnissen müssen Sie die Mängel in den Notizen genau beschreiben (mindestens 10 Zeichen).');
                document.getElementById('notes').focus();
            }
        });
    </script>
</body>
</html>