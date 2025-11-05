<?php
/**
 * Sportgeräteverwaltung BKT - Kleingeräte-Verwaltung
 * Seite zur Verwaltung von Kleingeräten mit Mengenverwaltung
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
$filterLocation = sanitizeInput($_GET['filter_location'] ?? '');
$filterCategory = sanitizeInput($_GET['filter_category'] ?? '');

// Pagination
$itemsPerPage = 25;
$offset = ($page - 1) * $itemsPerPage;

// Formularverarbeitung
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token validieren
    validateCSRFToken($_POST['csrf_token'] ?? '');

    $postAction = sanitizeInput($_POST['action'] ?? '');

    if ($postAction === 'add' && hasPermission('edit_small_equipment')) {
        $equipment = [
            'equipment_type' => sanitizeInput($_POST['equipment_type'] ?? ''),
            'category_id' => filter_var($_POST['category_id'], FILTER_VALIDATE_INT),
            'quantity' => filter_var($_POST['quantity'], FILTER_VALIDATE_INT),
            'storage_location_id' => filter_var($_POST['storage_location_id'], FILTER_VALIDATE_INT),
            'characteristics' => sanitizeInput($_POST['characteristics'] ?? ''),
            'min_quantity' => filter_var($_POST['min_quantity'], FILTER_VALIDATE_INT),
            'notes' => sanitizeInput($_POST['notes'] ?? '')
        ];

        // Validierung
        if (empty($equipment['equipment_type'])) {
            $errors[] = 'Gerätetyp ist erforderlich.';
        }
        if (!$equipment['category_id']) {
            $errors[] = 'Kategorie ist erforderlich.';
        }
        if (!$equipment['quantity'] || $equipment['quantity'] < 0) {
            $errors[] = 'Menge muss eine positive Zahl sein.';
        }
        if (!$equipment['storage_location_id']) {
            $errors[] = 'Lagerplatz ist erforderlich.';
        }
        if (!$equipment['min_quantity'] || $equipment['min_quantity'] < 0) {
            $errors[] = 'Mindestbestand muss eine positive Zahl sein.';
        }

        if (empty($errors)) {
            $sql = "INSERT INTO small_equipment
                    (equipment_type, category_id, quantity, storage_location_id,
                     characteristics, min_quantity, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $equipment['equipment_type'],
                $equipment['category_id'],
                $equipment['quantity'],
                $equipment['storage_location_id'],
                $equipment['characteristics'],
                $equipment['min_quantity'],
                $equipment['notes'],
                $currentUser['id']
            ];

            $newId = insertAndGetId($sql, $params);

            // Log für Mengenänderung
            $sql = "INSERT INTO small_equipment_log
                    (equipment_id, quantity_change, old_quantity, new_quantity, change_reason, changed_by)
                    VALUES (?, ?, 0, ?, 'Initialbestand', ?)";

            executeQuery($sql, [$newId, $equipment['quantity'], $currentUser['id']]);

            logActivity('add_small_equipment', "Kleingerät hinzugefügt: {$equipment['equipment_type']} (Menge: {$equipment['quantity']})");
            header('Location: small_equipment.php?message=added');
            exit;
        }
    } elseif ($postAction === 'edit' && hasPermission('edit_small_equipment')) {
        $equipmentId = filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT);
        if (!$equipmentId) {
            $errors[] = 'Ungültige Geräte-ID.';
        } else {
            $equipment = [
                'equipment_type' => sanitizeInput($_POST['equipment_type'] ?? ''),
                'category_id' => filter_var($_POST['category_id'], FILTER_VALIDATE_INT),
                'quantity' => filter_var($_POST['quantity'], FILTER_VALIDATE_INT),
                'storage_location_id' => filter_var($_POST['storage_location_id'], FILTER_VALIDATE_INT),
                'characteristics' => sanitizeInput($_POST['characteristics'] ?? ''),
                'min_quantity' => filter_var($_POST['min_quantity'], FILTER_VALIDATE_INT),
                'notes' => sanitizeInput($_POST['notes'] ?? '')
            ];

            // Alte Menge für Log holen
            $oldEquipment = fetchOne("SELECT quantity FROM small_equipment WHERE id = ?", [$equipmentId]);

            // Validierung
            if (empty($equipment['equipment_type'])) {
                $errors[] = 'Gerätetyp ist erforderlich.';
            }
            if (!$equipment['category_id']) {
                $errors[] = 'Kategorie ist erforderlich.';
            }
            if (!$equipment['quantity'] || $equipment['quantity'] < 0) {
                $errors[] = 'Menge muss eine positive Zahl sein.';
            }
            if (!$equipment['storage_location_id']) {
                $errors[] = 'Lagerplatz ist erforderlich.';
            }
            if (!$equipment['min_quantity'] || $equipment['min_quantity'] < 0) {
                $errors[] = 'Mindestbestand muss eine positive Zahl sein.';
            }

            if (empty($errors)) {
                $sql = "UPDATE small_equipment SET
                        equipment_type = ?, category_id = ?, quantity = ?,
                        storage_location_id = ?, characteristics = ?, min_quantity = ?,
                        notes = ?, updated_at = NOW(), created_by = ?
                        WHERE id = ?";

                $params = [
                    $equipment['equipment_type'],
                    $equipment['category_id'],
                    $equipment['quantity'],
                    $equipment['storage_location_id'],
                    $equipment['characteristics'],
                    $equipment['min_quantity'],
                    $equipment['notes'],
                    $currentUser['id'],
                    $equipmentId
                ];

                executeQuery($sql, $params);

                // Log für Mengenänderung, falls sich Menge geändert hat
                if ($oldEquipment && $oldEquipment['quantity'] != $equipment['quantity']) {
                    $changeReason = sanitizeInput($_POST['change_reason'] ?? 'Mengenaktualisierung');

                    $sql = "INSERT INTO small_equipment_log
                            (equipment_id, quantity_change, old_quantity, new_quantity, change_reason, changed_by)
                            VALUES (?, ?, ?, ?, ?, ?)";

                    executeQuery($sql, [
                        $equipmentId,
                        $equipment['quantity'] - $oldEquipment['quantity'],
                        $oldEquipment['quantity'],
                        $equipment['quantity'],
                        $changeReason,
                        $currentUser['id']
                    ]);
                }

                logActivity('edit_small_equipment', "Kleingerät bearbeitet: {$equipment['equipment_type']} (Menge: {$equipment['quantity']})");
                header('Location: small_equipment.php?message=updated');
                exit;
            }
        }
    } elseif ($postAction === 'quick_update' && hasPermission('edit_small_equipment')) {
        $equipmentId = filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT);
        $change = filter_var($_POST['change'], FILTER_VALIDATE_INT);
        $reason = sanitizeInput($_POST['reason'] ?? 'Schnellaktualisierung');

        if ($equipmentId && ($change === 1 || $change === -1)) {
            // Alte Menge holen
            $oldEquipment = fetchOne("SELECT quantity, equipment_type FROM small_equipment WHERE id = ?", [$equipmentId]);

            if ($oldEquipment) {
                $newQuantity = $oldEquipment['quantity'] + $change;

                if ($newQuantity >= 0) {
                    executeQuery("UPDATE small_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?",
                        [$newQuantity, $equipmentId]);

                    // Log-Eintrag
                    $sql = "INSERT INTO small_equipment_log
                            (equipment_id, quantity_change, old_quantity, new_quantity, change_reason, changed_by)
                            VALUES (?, ?, ?, ?, ?, ?)";

                    executeQuery($sql, [
                        $equipmentId,
                        $change,
                        $oldEquipment['quantity'],
                        $newQuantity,
                        $reason,
                        $currentUser['id']
                    ]);

                    logActivity('quick_update_small_equipment', "Menge angepasst: {$oldEquipment['equipment_type']} ({$change > 0 ? '+' : ''}{$change})");
                    header('Location: small_equipment.php?message=quick_updated');
                    exit;
                }
            }
        }
    } elseif ($postAction === 'delete' && hasPermission('delete_equipment')) {
        $equipmentId = filter_var($_POST['equipment_id'], FILTER_VALIDATE_INT);
        if ($equipmentId) {
            // Gerät abrufen für Log
            $sql = "SELECT equipment_type FROM small_equipment WHERE id = ?";
            $equipment = fetchOne($sql, [$equipmentId]);

            if ($equipment) {
                executeQuery("DELETE FROM small_equipment WHERE id = ?", [$equipmentId]);
                logActivity('delete_small_equipment', "Kleingerät gelöscht: {$equipment['equipment_type']}");
                header('Location: small_equipment.php?message=deleted');
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
        $sql = "SELECT se.*, sec.category_name, sl.hall, sl.spindle_room
                FROM small_equipment se
                JOIN small_equipment_categories sec ON se.category_id = sec.id
                JOIN storage_locations sl ON se.storage_location_id = sl.id
                WHERE se.id = ?";
        $equipment = fetchOne($sql, [$equipmentId]);
        if (!$equipment) {
            header('Location: small_equipment.php?error=not_found');
            exit;
        }
    }
} else {
    // Liste abrufen
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $whereConditions[] = "(se.equipment_type LIKE ? OR sec.category_name LIKE ? OR se.characteristics LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($filterLocation)) {
        $whereConditions[] = "se.storage_location_id = ?";
        $params[] = $filterLocation;
    }

    if (!empty($filterCategory)) {
        $whereConditions[] = "se.category_id = ?";
        $params[] = $filterCategory;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Gesamtanzahl für Pagination
    $countSql = "SELECT COUNT(*) as count FROM small_equipment se
                 JOIN small_equipment_categories sec ON se.category_id = sec.id
                 JOIN storage_locations sl ON se.storage_location_id = sl.id
                 $whereClause";
    $result = fetchOne($countSql, $params);
    $totalItems = $result['count'];

    // Daten abrufen
    $sql = "SELECT se.*, sec.category_name, sec.category_type,
                   sl.hall, sl.spindle_room,
                   CASE WHEN se.quantity <= se.min_quantity THEN 1 ELSE 0 END as low_stock
            FROM small_equipment se
            JOIN small_equipment_categories sec ON se.category_id = sec.id
            JOIN storage_locations sl ON se.storage_location_id = sl.id
            $whereClause
            ORDER BY sec.category_type, sec.category_name, se.equipment_type
            LIMIT ? OFFSET ?";

    $listParams = array_merge($params, [$itemsPerPage, $offset]);
    $equipmentList = fetchAll($sql, $listParams);
}

// Pagination-Daten
$pagination = getPaginationData($totalItems, $itemsPerPage, $page);

// Zusätzliche Daten für Dropdowns
$categories = fetchAll("SELECT * FROM small_equipment_categories ORDER BY category_type, category_name");
$locations = fetchAll("SELECT * FROM storage_locations ORDER BY hall, spindle_room");

// URL-Parameter verarbeiten
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $success = 'Kleingerät erfolgreich hinzugefügt.';
            break;
        case 'updated':
            $success = 'Kleingerät erfolgreich aktualisiert.';
            break;
        case 'quick_updated':
            $success = 'Menge erfolgreich aktualisiert.';
            break;
        case 'deleted':
            $success = 'Kleingerät erfolgreich gelöscht.';
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kleingeräte-Verwaltung | <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .quantity-display {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .low-stock {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .out-of-stock {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .storage-visualization {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .storage-box {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s ease;
        }

        .storage-box:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }

        .storage-box.filled {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-color: #28a745;
        }

        .storage-box.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            border-color: #ffc107;
        }

        .storage-box.empty {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-color: #dc3545;
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
                        <a class="nav-link active" href="small_equipment.php">
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
                        <i class="bi bi-plus-circle me-2"></i>Neues Kleingerät
                    <?php elseif ($action === 'edit'): ?>
                        <i class="bi bi-pencil me-2"></i>Kleingerät bearbeiten
                    <?php else: ?>
                        <i class="bi bi-basket me-2"></i>Kleingeräte-Verwaltung
                    <?php endif; ?>
                </h2>
            </div>
            <div class="col-md-6 text-md-end">
                <?php if ($action === 'list'): ?>
                    <?php if (hasPermission('edit_small_equipment')): ?>
                        <a href="small_equipment.php?action=add" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Neues Gerät
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="small_equipment.php" class="btn btn-outline-secondary">
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
                                            <label for="equipment_type" class="form-label">Gerätetyp *</label>
                                            <input type="text" class="form-control" id="equipment_type" name="equipment_type"
                                                   value="<?php echo htmlspecialchars($equipment['equipment_type'] ?? ''); ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category_id" class="form-label">Kategorie *</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Bitte wählen...</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>"
                                                            <?php echo (isset($equipment['category_id']) && $equipment['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['category_name']); ?> (<?php echo $category['category_type']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="quantity" class="form-label">Menge *</label>
                                            <input type="number" class="form-control" id="quantity" name="quantity"
                                                   value="<?php echo htmlspecialchars($equipment['quantity'] ?? '0'); ?>"
                                                   min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="min_quantity" class="form-label">Mindestbestand *</label>
                                            <input type="number" class="form-control" id="min_quantity" name="min_quantity"
                                                   value="<?php echo htmlspecialchars($equipment['min_quantity'] ?? '5'); ?>"
                                                   min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="storage_location_id" class="form-label">Lagerplatz *</label>
                                            <select class="form-select" id="storage_location_id" name="storage_location_id" required>
                                                <option value="">Bitte wählen...</option>
                                                <?php foreach ($locations as $location): ?>
                                                    <option value="<?php echo $location['id']; ?>"
                                                            <?php echo (isset($equipment['storage_location_id']) && $equipment['storage_location_id'] == $location['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($location['hall']); ?> - <?php echo htmlspecialchars($location['spindle_room']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="characteristics" class="form-label">Merkmale (Farbe, Material, Gewicht etc.)</label>
                                    <input type="text" class="form-control" id="characteristics" name="characteristics"
                                           value="<?php echo htmlspecialchars($equipment['characteristics'] ?? ''); ?>"
                                           placeholder="z.B. Orange, Leder, Größe 7">
                                </div>

                                <?php if ($action === 'edit'): ?>
                                    <div class="mb-3">
                                        <label for="change_reason" class="form-label">Grund der Mengenänderung</label>
                                        <input type="text" class="form-control" id="change_reason" name="change_reason"
                                               placeholder="z.B. Neue Lieferung, Defekte Geräte, Inventur">
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notizen</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($equipment['notes'] ?? ''); ?></textarea>
                                </div>

                                <div class="text-end">
                                    <a href="small_equipment.php" class="btn btn-outline-secondary me-2">Abbrechen</a>
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
                                <h5 class="mb-0">Mengenhistorie</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = "SELECT sel.*, u.full_name
                                        FROM small_equipment_log sel
                                        LEFT JOIN users u ON sel.changed_by = u.id
                                        WHERE sel.equipment_id = ?
                                        ORDER BY sel.changed_at DESC
                                        LIMIT 10";
                                $history = fetchAll($sql, [$equipment['id']]);
                                ?>

                                <?php if (empty($history)): ?>
                                    <p class="text-muted">Keine Änderungen protokolliert.</p>
                                <?php else: ?>
                                    <?php foreach ($history as $entry): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo $entry['quantity_change'] > 0 ? '+' : ''; ?><?php echo $entry['quantity_change']; ?></strong>
                                                <small class="text-muted"><?php echo formatDateTime($entry['changed_at'], 'd.m. H:i'); ?></small>
                                            </div>
                                            <small><?php echo htmlspecialchars($entry['change_reason']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($entry['full_name'] ?? 'System'); ?></small>
                                        </div>
                                    <?php endforeach; ?>
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
                                <input type="text" class="form-control" name="search" placeholder="Suchen (Typ, Kategorie, Merkmale)..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="filter_location">
                                <option value="">Alle Lagerorte</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php echo $filterLocation == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['hall']); ?> - <?php echo htmlspecialchars($location['spindle_room']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="filter_category">
                                <option value="">Alle Kategorien</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $filterCategory == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Suchen
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="small_equipment.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise me-1"></i>Zurücksetzen
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lagerplatz-Übersicht -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-grid-3x3-gap me-2"></i>Lagerplatz-Übersicht
                    </h5>
                </div>
                <div class="card-body">
                    <div class="storage-visualization">
                        <?php
                        $locationStats = [];
                        foreach ($locations as $location) {
                            $sql = "SELECT COUNT(*) as count, SUM(quantity) as total_quantity
                                    FROM small_equipment
                                    WHERE storage_location_id = ?";
                            $stats = fetchOne($sql, [$location['id']]);
                            $locationStats[] = [
                                'location' => $location,
                                'count' => $stats['count'],
                                'total_quantity' => $stats['total_quantity'] ?? 0
                            ];
                        }

                        foreach ($locationStats as $stat): ?>
                            <div class="storage-box <?php echo $stat['total_quantity'] == 0 ? 'empty' : ($stat['total_quantity'] < 20 ? 'warning' : 'filled'); ?>">
                                <h6><?php echo htmlspecialchars($stat['location']['hall']); ?></h6>
                                <p class="mb-1"><?php echo htmlspecialchars($stat['location']['spindle_room']); ?></p>
                                <div class="quantity-display"><?php echo $stat['total_quantity']; ?></div>
                                <small class="text-muted"><?php echo $stat['count']; ?> Artikel</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tabelle -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($equipmentList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-basket display-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">Keine Kleingeräte gefunden</h5>
                            <p class="text-muted">Passen Sie Ihre Filter an oder fügen Sie neue Geräte hinzu.</p>
                            <?php if (hasPermission('edit_small_equipment')): ?>
                                <a href="small_equipment.php?action=add" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Erstes Gerät hinzufügen
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Gerätetyp</th>
                                        <th>Kategorie</th>
                                        <th>Menge</th>
                                        <th>Mindestbestand</th>
                                        <th>Lagerplatz</th>
                                        <th>Merkmale</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipmentList as $item): ?>
                                        <tr class="<?php echo $item['quantity'] == 0 ? 'out-of-stock' : ($item['low_stock'] ? 'low-stock' : ''); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['equipment_type']); ?></strong>
                                                <?php if ($item['quantity'] == 0): ?>
                                                    <span class="badge bg-danger ms-2">Ausverkauft</span>
                                                <?php elseif ($item['low_stock']): ?>
                                                    <span class="badge bg-warning ms-2">Niedrig</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($item['category_name']); ?></span><br>
                                                <small class="text-muted"><?php echo $item['category_type']; ?></small>
                                            </td>
                                            <td>
                                                <div class="quantity-controls">
                                                    <?php if (hasPermission('edit_small_equipment')): ?>
                                                        <form method="post" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="quick_update">
                                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                                            <input type="hidden" name="change" value="-1">
                                                            <input type="hidden" name="reason" value="Manuelle Entnahme">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    <?php echo $item['quantity'] == 0 ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-dash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <span class="quantity-display"><?php echo $item['quantity']; ?></span>
                                                    <?php if (hasPermission('edit_small_equipment')): ?>
                                                        <form method="post" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="quick_update">
                                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                                            <input type="hidden" name="change" value="1">
                                                            <input type="hidden" name="reason" value="Manuelle Zugabe">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                                <i class="bi bi-plus"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $item['quantity'] <= $item['min_quantity'] ? 'bg-warning' : 'bg-secondary'; ?>">
                                                    <?php echo $item['min_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($item['hall']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['spindle_room']); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($item['characteristics'] ?? '-'); ?></small>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="small_equipment.php?action=edit&id=<?php echo $item['id']; ?>"
                                                       class="btn btn-outline-primary" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-info"
                                                            onclick="showHistory(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['equipment_type']); ?>')"
                                                            title="Historie">
                                                        <i class="bi bi-clock-history"></i>
                                                    </button>
                                                    <?php if (hasPermission('delete_equipment')): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                                onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['equipment_type']); ?>')"
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
                                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&filter_location=<?php echo urlencode($filterLocation); ?>&filter_category=<?php echo urlencode($filterCategory); ?>">
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_location=<?php echo urlencode($filterLocation); ?>&filter_category=<?php echo urlencode($filterCategory); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($pagination['has_next']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&filter_location=<?php echo urlencode($filterLocation); ?>&filter_category=<?php echo urlencode($filterCategory); ?>">
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
                    <p>Möchten Sie dieses Kleingerät wirklich löschen?</p>
                    <p><strong>Gerätetyp:</strong> <span id="deleteEquipmentType"></span></p>
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

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mengenhistorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 id="historyEquipmentType"></h6>
                    <div id="historyContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Laden...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function confirmDelete(equipmentId, equipmentType) {
            document.getElementById('deleteEquipmentId').value = equipmentId;
            document.getElementById('deleteEquipmentType').textContent = equipmentType;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function showHistory(equipmentId, equipmentType) {
            document.getElementById('historyEquipmentType').textContent = equipmentType;
            document.getElementById('historyContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                </div>
            `;

            // AJAX-Request für Historie
            fetch(`../api/equipment_history.php?type=small&id=${equipmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.history) {
                        let html = '';
                        if (data.history.length === 0) {
                            html = '<p class="text-muted">Keine Änderungen protokolliert.</p>';
                        } else {
                            html = '<div class="timeline">';
                            data.history.forEach(entry => {
                                const changeClass = entry.quantity_change > 0 ? 'success' : 'danger';
                                const changeIcon = entry.quantity_change > 0 ? 'bi-plus-circle' : 'bi-dash-circle';
                                html += `
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="me-3">
                                            <i class="bi ${changeIcon} text-${changeClass}"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong>${entry.quantity_change > 0 ? '+' : ''}${entry.quantity_change}</strong>
                                                <small class="text-muted">${new Date(entry.changed_at).toLocaleString('de-DE')}</small>
                                            </div>
                                            <small>${entry.change_reason}</small><br>
                                            <small class="text-muted">${entry.full_name || 'System'}</small>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }
                        document.getElementById('historyContent').innerHTML = html;
                    } else {
                        document.getElementById('historyContent').innerHTML = '<p class="text-danger">Fehler beim Laden der Historie.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading history:', error);
                    document.getElementById('historyContent').innerHTML = '<p class="text-danger">Verbindungsfehler.</p>';
                });

            new bootstrap.Modal(document.getElementById('historyModal')).show();
        }

        // Schnellaktualisierung mit Bestätigung
        document.querySelectorAll('form[action=""][method="post"]').forEach(form => {
            if (form.querySelector('input[name="action"][value="quick_update"]')) {
                form.addEventListener('submit', function(e) {
                    const change = this.querySelector('input[name="change"]').value;
                    const action = change === '1' ? 'hinzufügen' : 'entnehmen';
                    if (!confirm(`Möchten Sie wirklich 1 Stück ${action}?`)) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>