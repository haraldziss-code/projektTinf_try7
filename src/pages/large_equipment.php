<?php
/**
 * Sportgeräteverwaltung BKT - Großgeräte-Verwaltung
 * Seite zur Verwaltung von Großgeräten mit CRUD-Operationen
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
$equipmentId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$search = sanitizeInput($_GET['search'] ?? '');
$filterHall = sanitizeInput($_GET['filter_hall'] ?? '');
$filterStatus = sanitizeInput($_GET['filter_status'] ?? '');

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

    if ($postAction === 'add' && hasPermission('add_equipment')) {
        $equipment = [
            'inventory_number' => sanitizeInput($_POST['inventory_number'] ?? ''),
            'equipment_name' => sanitizeInput($_POST['equipment_name'] ?? ''),
            'equipment_type' => sanitizeInput($_POST['equipment_type'] ?? ''),
            'hall_garage' => sanitizeInput($_POST['hall_garage'] ?? ''),
            'location_description' => sanitizeInput($_POST['location_description'] ?? ''),
            'last_inspection' => sanitizeInput($_POST['last_inspection'] ?? ''),
            'next_inspection_due' => sanitizeInput($_POST['next_inspection_due'] ?? ''),
            'status' => sanitizeInput($_POST['status'] ?? 'aktiv'),
            'notes' => sanitizeInput($_POST['notes'] ?? '')
        ];

        // Validierung
        if (empty($equipment['inventory_number'])) {
            $errors[] = 'Inventarnummer ist erforderlich.';
        }
        if (empty($equipment['equipment_name'])) {
            $errors[] = 'Gerätename ist erforderlich.';
        }
        if (empty($equipment['equipment_type'])) {
            $errors[] = 'Gerätetyp ist erforderlich.';
        }
        if (empty($equipment['hall_garage'])) {
            $errors[] = 'Halle/Garage ist erforderlich.';
        }

        // Prüfen, ob Inventarnummer bereits existiert
        if (!empty($equipment['inventory_number'])) {
            $sql = "SELECT COUNT(*) as count FROM large_equipment WHERE inventory_number = ?";
            $result = fetchOne($sql, [$equipment['inventory_number']]);
            if ($result['count'] > 0) {
                $errors[] = 'Diese Inventarnummer existiert bereits.';
            }
        }

        // Datum validieren
        if (!empty($equipment['last_inspection']) && !validateDate($equipment['last_inspection'])) {
            $errors[] = 'Ungültiges Datum für letzte Inspektion.';
        }
        if (!empty($equipment['next_inspection_due']) && !validateDate($equipment['next_inspection_due'])) {
            $errors[] = 'Ungültiges Datum für nächste Inspektion.';
        }

        if (empty($errors)) {
            $sql = "INSERT INTO large_equipment
                    (inventory_number, equipment_name, equipment_type, hall_garage,
                     location_description, last_inspection, next_inspection_due, status, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $equipment['inventory_number'],
                $equipment['equipment_name'],
                $equipment['equipment_type'],
                $equipment['hall_garage'],
                $equipment['location_description'],
                $equipment['last_inspection'] ?: null,
                $equipment['next_inspection_due'] ?: null,
                $equipment['status'],
                $equipment['notes'],
                $currentUser['id']
            ];

            executeQuery($sql, $params);
            logActivity('add_large_equipment', "Großgerät hinzugefügt: {$equipment['inventory_number']} - {$equipment['equipment_name']}");
            header('Location: large_equipment.php?message=added');
            exit;
        }
    } elseif ($postAction === 'edit' && hasPermission('edit_equipment')) {
        $equipmentId = filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT);
        if (!$equipmentId) {
            $errors[] = 'Ungültige Geräte-ID.';
        } else {
            $equipment = [
                'inventory_number' => sanitizeInput($_POST['inventory_number'] ?? ''),
                'equipment_name' => sanitizeInput($_POST['equipment_name'] ?? ''),
                'equipment_type' => sanitizeInput($_POST['equipment_type'] ?? ''),
                'hall_garage' => sanitizeInput($_POST['hall_garage'] ?? ''),
                'location_description' => sanitizeInput($_POST['location_description'] ?? ''),
                'last_inspection' => sanitizeInput($_POST['last_inspection'] ?? ''),
                'next_inspection_due' => sanitizeInput($_POST['next_inspection_due'] ?? ''),
                'status' => sanitizeInput($_POST['status'] ?? 'aktiv'),
                'notes' => sanitizeInput($_POST['notes'] ?? '')
            ];

            // Validierung
            if (empty($equipment['inventory_number'])) {
                $errors[] = 'Inventarnummer ist erforderlich.';
            }
            if (empty($equipment['equipment_name'])) {
                $errors[] = 'Gerätename ist erforderlich.';
            }
            if (empty($equipment['equipment_type'])) {
                $errors[] = 'Gerätetyp ist erforderlich.';
            }
            if (empty($equipment['hall_garage'])) {
                $errors[] = 'Halle/Garage ist erforderlich.';
            }

            // Prüfen, ob Inventarnummer bereits existiert (außer bei diesem Gerät)
            if (!empty($equipment['inventory_number'])) {
                $sql = "SELECT COUNT(*) as count FROM large_equipment
                        WHERE inventory_number = ? AND id != ?";
                $result = fetchOne($sql, [$equipment['inventory_number'], $equipmentId]);
                if ($result['count'] > 0) {
                    $errors[] = 'Diese Inventarnummer existiert bereits.';
                }
            }

            if (empty($errors)) {
                $sql = "UPDATE large_equipment SET
                        inventory_number = ?, equipment_name = ?, equipment_type = ?,
                        hall_garage = ?, location_description = ?, last_inspection = ?,
                        next_inspection_due = ?, status = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?";

                $params = [
                    $equipment['inventory_number'],
                    $equipment['equipment_name'],
                    $equipment['equipment_type'],
                    $equipment['hall_garage'],
                    $equipment['location_description'],
                    $equipment['last_inspection'] ?: null,
                    $equipment['next_inspection_due'] ?: null,
                    $equipment['status'],
                    $equipment['notes'],
                    $equipmentId
                ];

                executeQuery($sql, $params);
                logActivity('edit_large_equipment', "Großgerät bearbeitet: {$equipment['inventory_number']} - {$equipment['equipment_name']}");
                header('Location: large_equipment.php?message=updated');
                exit;
            }
        }
    } elseif ($postAction === 'delete' && hasPermission('delete_equipment')) {
        $equipmentId = filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT);
        if ($equipmentId) {
            // Gerät abrufen für Log
            $sql = "SELECT inventory_number, equipment_name FROM large_equipment WHERE id = ?";
            $equipment = fetchOne($sql, [$equipmentId]);

            if ($equipment) {
                executeQuery("DELETE FROM large_equipment WHERE id = ?", [$equipmentId]);
                logActivity('delete_large_equipment', "Großgerät gelöscht: {$equipment['inventory_number']} - {$equipment['equipment_name']}");
                header('Location: large_equipment.php?message=deleted');
                exit;
            }
        }
    }
}

// Daten abrufen
$equipment = null;
$equipmentList = [];
$totalItems = 0;

if ($action === 'add' || ($action === 'edit' && $equipmentId)) {
    if ($action === 'edit' && $equipmentId) {
        $sql = "SELECT * FROM large_equipment WHERE id = ?";
        $equipment = fetchOne($sql, [$equipmentId]);
        if (!$equipment) {
            header('Location: large_equipment.php?error=not_found');
            exit;
        }
    }
} else {
    // Liste abrufen
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $whereConditions[] = "(inventory_number LIKE ? OR equipment_name LIKE ? OR equipment_type LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($filterHall)) {
        $whereConditions[] = "hall_garage = ?";
        $params[] = $filterHall;
    }

    if (!empty($filterStatus)) {
        $whereConditions[] = "status = ?";
        $params[] = $filterStatus;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Gesamtanzahl für Pagination
    $countSql = "SELECT COUNT(*) as count FROM large_equipment $whereClause";
    $result = fetchOne($countSql, $params);
    $totalItems = $result['count'];

    // Daten abrufen
    $sql = "SELECT * FROM large_equipment $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";

    $listParams = array_merge($params, [$itemsPerPage, $offset]);
    $equipmentList = fetchAll($sql, $listParams);
}

// Pagination-Daten
$pagination = getPaginationData($totalItems, $itemsPerPage, $page);

// URL-Parameter verarbeiten
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $success = 'Großgerät erfolgreich hinzugefügt.';
            break;
        case 'updated':
            $success = 'Großgerät erfolgreich aktualisiert.';
            break;
        case 'deleted':
            $success = 'Großgerät erfolgreich gelöscht.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_found':
            $errors[] = 'Gerät nicht gefunden.';
            break;
        case 'permission':
            $errors[] = 'Keine Berechtigung für diese Aktion.';
            break;
    }
}

// Gerätetypen für Dropdown
$equipmentTypes = [
    'Turnkasten', 'Turnbank', 'mehrteiliger Kasten', 'Barren', 'Tischtennistisch',
    'Sprungbrett', 'Kletterwand', 'Reck', 'Ringe', 'Pauschen', 'Bock',
    'Schwebebalken', 'Minitrampolin', 'Matten', 'Sprossenwand'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Großgeräte-Verwaltung | <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                        <a class="nav-link active" href="large_equipment.php">
                            <i class="bi bi-box me-1"></i> Großgeräte
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="small_equipment.php">
                            <i class="bi bi-basket me-1"></i> Kleingeräte
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="inspections.php">
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
                        <i class="bi bi-plus-circle me-2"></i>Neues Großgerät
                    <?php elseif ($action === 'edit'): ?>
                        <i class="bi bi-pencil me-2"></i>Großgerät bearbeiten
                    <?php else: ?>
                        <i class="bi bi-box me-2"></i>Großgeräte-Verwaltung
                    <?php endif; ?>
                </h2>
            </div>
            <div class="col-md-6 text-md-end">
                <?php if ($action === 'list'): ?>
                    <?php if (hasPermission('add_equipment')): ?>
                        <a href="large_equipment.php?action=add" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Neues Gerät
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="large_equipment.php" class="btn btn-outline-secondary">
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
                            <h5 class="mb-0">Gerätedaten</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="<?php echo $action === 'add' ? 'add' : 'edit'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="equipment_id" value="<?php echo $equipment['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="inventory_number" class="form-label">Inventarnummer *</label>
                                            <input type="text" class="form-control" id="inventory_number" name="inventory_number"
                                                   value="<?php echo htmlspecialchars($equipment['inventory_number'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="equipment_name" class="form-label">Gerätename *</label>
                                            <input type="text" class="form-control" id="equipment_name" name="equipment_name"
                                                   value="<?php echo htmlspecialchars($equipment['equipment_name'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="equipment_type" class="form-label">Gerätetyp *</label>
                                            <select class="form-select" id="equipment_type" name="equipment_type" required>
                                                <option value="">Bitte wählen...</option>
                                                <?php foreach ($equipmentTypes as $type): ?>
                                                    <option value="<?php echo $type; ?>" <?php echo (isset($equipment['equipment_type']) && $equipment['equipment_type'] === $type) ? 'selected' : ''; ?>>
                                                        <?php echo $type; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="hall_garage" class="form-label">Halle/Garage *</label>
                                            <select class="form-select" id="hall_garage" name="hall_garage" required>
                                                <option value="">Bitte wählen...</option>
                                                <option value="Halle 1" <?php echo (isset($equipment['hall_garage']) && $equipment['hall_garage'] === 'Halle 1') ? 'selected' : ''; ?>>Halle 1</option>
                                                <option value="Halle 2" <?php echo (isset($equipment['hall_garage']) && $equipment['hall_garage'] === 'Halle 2') ? 'selected' : ''; ?>>Halle 2</option>
                                                <option value="Halle 3" <?php echo (isset($equipment['hall_garage']) && $equipment['hall_garage'] === 'Halle 3') ? 'selected' : ''; ?>>Halle 3 (GBB)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="location_description" class="form-label">Standortbeschreibung</label>
                                    <textarea class="form-control" id="location_description" name="location_description" rows="2"><?php echo htmlspecialchars($equipment['location_description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_inspection" class="form-label">Letzte Inspektion</label>
                                            <input type="date" class="form-control" id="last_inspection" name="last_inspection"
                                                   value="<?php echo htmlspecialchars($equipment['last_inspection'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="next_inspection_due" class="form-label">Nächste Inspektion fällig</label>
                                            <input type="date" class="form-control" id="next_inspection_due" name="next_inspection_due"
                                                   value="<?php echo htmlspecialchars($equipment['next_inspection_due'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="aktiv" <?php echo (isset($equipment['status']) && $equipment['status'] === 'aktiv') ? 'selected' : ''; ?>>Aktiv</option>
                                        <option value="in Reparatur" <?php echo (isset($equipment['status']) && $equipment['status'] === 'in Reparatur') ? 'selected' : ''; ?>>In Reparatur</option>
                                        <option value="ausgemustert" <?php echo (isset($equipment['status']) && $equipment['status'] === 'ausgemustert') ? 'selected' : ''; ?>>Ausgemustert</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notizen</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($equipment['notes'] ?? ''); ?></textarea>
                                </div>

                                <div class="text-end">
                                    <a href="large_equipment.php" class="btn btn-outline-secondary me-2">Abbrechen</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i>
                                        <?php echo $action === 'add' ? 'Hinzufügen' : 'Speichern'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($action === 'edit' && $equipment): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Inspektionshistorie</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = "SELECT * FROM inspections WHERE equipment_id = ? ORDER BY inspection_date DESC LIMIT 10";
                                $inspections = fetchAll($sql, [$equipment['id']]);
                                ?>

                                <?php if (empty($inspections)): ?>
                                    <p class="text-muted">Keine Inspektionen gefunden.</p>
                                <?php else: ?>
                                    <?php foreach ($inspections as $inspection): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <strong><?php echo formatDate($inspection['inspection_date']); ?></strong><br>
                                            <?php echo getStatusBadge($inspection['result']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($inspection['inspector_name']); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (hasPermission('add_inspection')): ?>
                                    <a href="inspections.php?action=add&equipment_id=<?php echo $equipment['id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="bi bi-plus-circle me-1"></i>Neue Inspektion
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Liste und Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-4">
                            <div class="search-input">
                                <input type="text" class="form-control" name="search" placeholder="Suchen (Inventarnr., Name, Typ)..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="filter_hall">
                                <option value="">Alle Hallen</option>
                                <option value="Halle 1" <?php echo $filterHall === 'Halle 1' ? 'selected' : ''; ?>>Halle 1</option>
                                <option value="Halle 2" <?php echo $filterHall === 'Halle 2' ? 'selected' : ''; ?>>Halle 2</option>
                                <option value="Halle 3" <?php echo $filterHall === 'Halle 3' ? 'selected' : ''; ?>>Halle 3 (GBB)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="filter_status">
                                <option value="">Alle Status</option>
                                <option value="aktiv" <?php echo $filterStatus === 'aktiv' ? 'selected' : ''; ?>>Aktiv</option>
                                <option value="in Reparatur" <?php echo $filterStatus === 'in Reparatur' ? 'selected' : ''; ?>>In Reparatur</option>
                                <option value="ausgemustert" <?php echo $filterStatus === 'ausgemustert' ? 'selected' : ''; ?>>Ausgemustert</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Suchen
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="large_equipment.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise me-1"></i>Zurücksetzen
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabelle -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($equipmentList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-box display-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">Keine Großgeräte gefunden</h5>
                            <p class="text-muted">Passen Sie Ihre Filter an oder fügen Sie neue Geräte hinzu.</p>
                            <?php if (hasPermission('add_equipment')): ?>
                                <a href="large_equipment.php?action=add" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Erstes Gerät hinzufügen
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Inventarnr.</th>
                                        <th>Gerätename</th>
                                        <th>Typ</th>
                                        <th>Halle</th>
                                        <th>Letzte Inspektion</th>
                                        <th>Nächste Inspektion</th>
                                        <th>Status</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipmentList as $item): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['inventory_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['equipment_type']); ?></td>
                                            <td><?php echo htmlspecialchars($item['hall_garage']); ?></td>
                                            <td><?php echo formatDate($item['last_inspection']); ?></td>
                                            <td>
                                                <?php
                                                $daysUntil = '';
                                                if ($item['next_inspection_due']) {
                                                    $nextDate = new DateTime($item['next_inspection_due']);
                                                    $today = new DateTime();
                                                    $interval = $today->diff($nextDate);
                                                    $daysUntil = $interval->days;

                                                    if ($nextDate < $today) {
                                                        echo '<span class="badge bg-danger">Überfällig</span>';
                                                    } elseif ($daysUntil <= 7) {
                                                        echo '<span class="badge bg-warning">' . $daysUntil . ' Tage</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success">' . formatDate($item['next_inspection_due']) . '</span>';
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo getStatusBadge($item['status']); ?></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="large_equipment.php?action=edit&id=<?php echo $item['id']; ?>"
                                                       class="btn btn-outline-primary" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="inspections.php?equipment_id=<?php echo $item['id']; ?>"
                                                       class="btn btn-outline-info" title="Inspektionen">
                                                        <i class="bi bi-clipboard-check"></i>
                                                    </a>
                                                    <?php if (hasPermission('delete_equipment')): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                                onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['inventory_number']); ?>')"
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
                                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&filter_hall=<?php echo urlencode($filterHall); ?>&filter_status=<?php echo urlencode($filterStatus); ?>">
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_hall=<?php echo urlencode($filterHall); ?>&filter_status=<?php echo urlencode($filterStatus); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($pagination['has_next']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&filter_hall=<?php echo urlencode($filterHall); ?>&filter_status=<?php echo urlencode($filterStatus); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="mt-3 text-muted">
                            <small>
                                Zeige <?php echo count($equipmentList); ?> von <?php echo $pagination['total_items']; ?> Geräten
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
                    <h5 class="modal-title">Gerät löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Möchten Sie dieses Großgerät wirklich löschen?</p>
                    <p><strong>Inventarnummer:</strong> <span id="deleteInventoryNumber"></span></p>
                    <p class="text-warning">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="equipment_id" id="deleteEquipmentId">
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
        function confirmDelete(equipmentId, inventoryNumber) {
            document.getElementById('deleteEquipmentId').value = equipmentId;
            document.getElementById('deleteInventoryNumber').textContent = inventoryNumber;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Autovervollständigung für Gerätetypen
        document.getElementById('equipment_type')?.addEventListener('focus', function() {
            if (this.value === '') {
                this.classList.add('is-valid');
            }
        });
    </script>
</body>
</html>