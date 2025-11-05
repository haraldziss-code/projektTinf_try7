<?php
/**
 * Sportgeräteverwaltung BKT - Berichte und Abfragen
 * Seite für Berichte, Export-Funktionen und Datenabfragen
 */

define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Login und Berechtigungsprüfung
requireLogin(['Lehrer', 'Hausmeister', 'Admin']);

// Aktuellen Benutzer abrufen
$currentUser = getCurrentUser();

// Parameter abrufen
$reportType = sanitizeInput($_GET['report'] ?? '');
$exportFormat = sanitizeInput($_GET['export'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Formularverarbeitung
$errors = [];
$success = '';

// Daten für verschiedene Berichte abrufen
$reports = [];

// Bericht 1: Geräteübersicht
$reports['equipment_overview'] = [
    'total_large' => fetchOne("SELECT COUNT(*) as count FROM large_equipment WHERE status = 'aktiv'")['count'],
    'total_small_items' => fetchOne("SELECT SUM(quantity) as total FROM small_equipment")['total'] ?? 0,
    'total_small_types' => fetchOne("SELECT COUNT(*) as count FROM small_equipment")['count'],
    'equipment_in_repair' => fetchOne("SELECT COUNT(*) as count FROM large_equipment WHERE status = 'in Reparatur'")['count'],
    'equipment_decommissioned' => fetchOne("SELECT COUNT(*) as count FROM large_equipment WHERE status = 'ausgemustert'")['count']
];

// Bericht 2: Fällige Inspektionen
$reports['overdue_inspections'] = fetchAll("
    SELECT le.id, le.inventory_number, le.equipment_name, le.hall_garage,
           le.last_inspection, le.next_inspection_due,
           DATEDIFF(CURDATE(), le.next_inspection_due) as days_overdue
    FROM large_equipment le
    WHERE le.status = 'aktiv' AND le.next_inspection_due < CURDATE()
    ORDER BY le.next_inspection_due ASC
");

$reports['upcoming_inspections'] = fetchAll("
    SELECT le.id, le.inventory_number, le.equipment_name, le.hall_garage,
           le.next_inspection_due,
           DATEDIFF(le.next_inspection_due, CURDATE()) as days_until
    FROM large_equipment le
    WHERE le.status = 'aktiv'
      AND le.next_inspection_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY le.next_inspection_due ASC
");

// Bericht 3: Niedrige Bestände
$reports['low_stock'] = fetchAll("
    SELECT se.id, se.equipment_type, se.quantity, se.min_quantity,
           sec.category_name, sl.hall, sl.spindle_room,
           (se.min_quantity - se.quantity) as shortage
    FROM small_equipment se
    JOIN small_equipment_categories sec ON se.category_id = sec.id
    JOIN storage_locations sl ON se.storage_location_id = sl.id
    WHERE se.quantity <= se.min_quantity
    ORDER BY shortage DESC, se.equipment_type ASC
");

// Bericht 4: Geräte nach Halle
$reports['equipment_by_hall'] = fetchAll("
    SELECT
        hall_garage,
        COUNT(*) as total_equipment,
        SUM(CASE WHEN status = 'aktiv' THEN 1 ELSE 0 END) as active_equipment,
        SUM(CASE WHEN status = 'in Reparatur' THEN 1 ELSE 0 END) as repair_equipment,
        SUM(CASE WHEN status = 'ausgemustert' THEN 1 ELSE 0 END) as decommissioned_equipment
    FROM large_equipment
    GROUP BY hall_garage
    ORDER BY hall_garage
");

// Bericht 5: Kleingeräte nach Kategorie
$reports['small_equipment_by_category'] = fetchAll("
    SELECT
        sec.category_name,
        sec.category_type,
        COUNT(*) as types_count,
        SUM(se.quantity) as total_quantity,
        SUM(se.min_quantity) as total_min_quantity,
        SUM(CASE WHEN se.quantity <= se.min_quantity THEN 1 ELSE 0 END) as low_stock_types
    FROM small_equipment se
    JOIN small_equipment_categories sec ON se.category_id = sec.id
    GROUP BY sec.id, sec.category_name, sec.category_type
    ORDER BY sec.category_type, sec.category_name
");

// Bericht 6: Inspektionsstatistiken
$reports['inspection_stats'] = [
    'total_inspections' => fetchOne("SELECT COUNT(*) as count FROM inspections")['count'],
    'this_year' => fetchOne("SELECT COUNT(*) as count FROM inspections WHERE YEAR(inspection_date) = YEAR(CURDATE())")['count'],
    'last_year' => fetchOne("SELECT COUNT(*) as count FROM inspections WHERE YEAR(inspection_date) = YEAR(CURDATE()) - 1")['count'],
    'passed' => fetchOne("SELECT COUNT(*) as count FROM inspections WHERE result = 'bestanden'")['count'],
    'failed' => fetchOne("SELECT COUNT(*) as count FROM inspections WHERE result = 'nicht bestanden'")['count'],
    'conditional' => fetchOne("SELECT COUNT(*) as count FROM inspections WHERE result = 'mit Auflagen'")['count']
];

$reports['recent_inspections'] = fetchAll("
    SELECT i.inspection_date, i.result, i.inspector_name,
           le.inventory_number, le.equipment_name, le.hall_garage
    FROM inspections i
    JOIN large_equipment le ON i.equipment_id = le.id
    ORDER BY i.inspection_date DESC
    LIMIT 10
");

// Bericht 7: Lagerplatz-Auslastung
$reports['storage_usage'] = fetchAll("
    SELECT
        sl.id,
        sl.hall,
        sl.spindle_room,
        sl.capacity,
        COUNT(se.id) as equipment_types,
        SUM(se.quantity) as total_items,
        ROUND((SUM(se.quantity) * 100.0 / sl.capacity), 2) as usage_percentage
    FROM storage_locations sl
    LEFT JOIN small_equipment se ON sl.id = se.storage_location_id
    GROUP BY sl.id, sl.hall, sl.spindle_room, sl.capacity
    ORDER BY usage_percentage DESC
");

// Spezielle Abfragen
$customQueries = [
    'how_many_badminton_rackets' => "
        SELECT se.equipment_type, se.quantity, sl.hall, sl.spindle_room
        FROM small_equipment se
        JOIN small_equipment_categories sec ON se.category_id = sec.id
        JOIN storage_locations sl ON se.storage_location_id = sl.id
        WHERE se.equipment_type LIKE '%Badminton%'
    ",
    'when_last_inspection' => "
        SELECT le.inventory_number, le.equipment_name, le.last_inspection,
               i.inspector_name, i.result, i.notes
        FROM large_equipment le
        LEFT JOIN inspections i ON le.id = i.equipment_id
        WHERE le.inventory_number = ?
        ORDER BY i.inspection_date DESC
        LIMIT 1
    ",
    'equipment_location' => "
        SELECT le.inventory_number, le.equipment_name, le.hall_garage,
               le.location_description, le.status
        FROM large_equipment le
        WHERE le.hall_garage = ?
        ORDER BY le.equipment_name
    "
];

// Custom Query Ausführung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_query'])) {
    $queryType = sanitizeInput($_POST['query_type']);
    $queryParam = sanitizeInput($_POST['query_param'] ?? '');

    if ($queryType === 'when_last_inspection' && !empty($queryParam)) {
        $result = fetchOne($customQueries[$queryType], [$queryParam]);
        $customQueryResult = $result ? [$result] : [];
    } elseif ($queryType === 'equipment_location' && !empty($queryParam)) {
        $customQueryResult = fetchAll($customQueries[$queryType], [$queryParam]);
    } elseif ($queryType === 'how_many_badminton_rackets') {
        $customQueryResult = fetchAll($customQueries[$queryType]);
    } else {
        $customQueryResult = [];
    }
}

// Export-Funktionen
if ($exportFormat && $reportType) {
    switch ($exportFormat) {
        case 'csv':
            exportToCSV($reportType);
            break;
        case 'pdf':
            exportToPDF($reportType);
            break;
        case 'excel':
            exportToExcel($reportType);
            break;
    }
}

/**
 * CSV Export
 */
function exportToCSV($reportType) {
    global $reports;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $reportType . '_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM für Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    switch ($reportType) {
        case 'equipment_overview':
            fputcsv($output, ['Gerätetyp', 'Anzahl']);
            fputcsv($output, ['Großgeräte (aktiv)', $reports['equipment_overview']['total_large']]);
            fputcsv($output, ['Kleingeräte (Typen)', $reports['equipment_overview']['total_small_types']]);
            fputcsv($output, ['Kleingeräte (Gesamtmenge)', $reports['equipment_overview']['total_small_items']]);
            fputcsv($output, ['In Reparatur', $reports['equipment_overview']['equipment_in_repair']]);
            fputcsv($output, ['Ausgemustert', $reports['equipment_overview']['equipment_decommissioned']]);
            break;

        case 'overdue_inspections':
            fputcsv($output, ['Inventarnr.', 'Gerätename', 'Halle', 'Letzte Inspektion', 'Fällig am', 'Tage überfällig']);
            foreach ($reports['overdue_inspections'] as $item) {
                fputcsv($output, [
                    $item['inventory_number'],
                    $item['equipment_name'],
                    $item['hall_garage'],
                    formatDate($item['last_inspection']),
                    formatDate($item['next_inspection_due']),
                    $item['days_overdue']
                ]);
            }
            break;

        case 'low_stock':
            fputcsv($output, ['Gerätetyp', 'Kategorie', 'Aktueller Bestand', 'Mindestbestand', 'Fehlende Menge', 'Lagerplatz']);
            foreach ($reports['low_stock'] as $item) {
                fputcsv($output, [
                    $item['equipment_type'],
                    $item['category_name'],
                    $item['quantity'],
                    $item['min_quantity'],
                    $item['shortage'],
                    $item['hall'] . ' - ' . $item['spindle_room']
                ]);
            }
            break;

        case 'equipment_by_hall':
            fputcsv($output, ['Halle', 'Gesamt', 'Aktiv', 'In Reparatur', 'Ausgemustert']);
            foreach ($reports['equipment_by_hall'] as $item) {
                fputcsv($output, [
                    $item['hall_garage'],
                    $item['total_equipment'],
                    $item['active_equipment'],
                    $item['repair_equipment'],
                    $item['decommissioned_equipment']
                ]);
            }
            break;

        case 'small_equipment_by_category':
            fputcsv($output, ['Kategorie', 'Typ', 'Anzahl Typen', 'Gesamtmenge', 'Mindestmenge', 'Niedrige Bestände']);
            foreach ($reports['small_equipment_by_category'] as $item) {
                fputcsv($output, [
                    $item['category_name'],
                    $item['category_type'],
                    $item['types_count'],
                    $item['total_quantity'],
                    $item['total_min_quantity'],
                    $item['low_stock_types']
                ]);
            }
            break;

        default:
            echo 'Bericht nicht gefunden';
            exit;
    }

    fclose($output);
    exit;
}

/**
 * PDF Export (vereinfacht)
 */
function exportToPDF($reportType) {
    // Für eine echte Implementierung würde man eine Bibliothek wie TCPDF oder FPDF verwenden
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=' . $reportType . '_' . date('Y-m-d') . '.pdf');

    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<h1>Bericht: ' . htmlspecialchars($reportType) . '</h1>';
    echo '<p>Erstellt am: ' . date('d.m.Y H:i') . '</p>';
    echo '<p>Dies ist eine vereinfachte PDF-Ausgabe. Für die vollständige Implementierung wird eine PDF-Bibliothek benötigt.</p>';
    echo '</body></html>';
    exit;
}

/**
 * Excel Export (vereinfacht als CSV)
 */
function exportToExcel($reportType) {
    // Für eine echte Implementierung würde man eine Bibliothek wie PHPExcel verwenden
    exportToCSV($reportType);
}

// URL-Parameter verarbeiten
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'exported':
            $success = 'Bericht erfolgreich exportiert.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berichte & Abfragen | <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-left: 4px solid #007bff;
        }

        .report-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .report-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }

        .query-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .query-result {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .quick-query {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .quick-query h5 {
            margin-bottom: 1rem;
        }

        .quick-query .btn {
            background: white;
            color: #667eea;
            border: none;
            font-weight: 600;
        }

        .quick-query .btn:hover {
            background: #f8f9fa;
            color: #764ba2;
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
                        <a class="nav-link" href="inspections.php">
                            <i class="bi bi-clipboard-check me-1"></i> Inspektionen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">
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
                    <i class="bi bi-file-earmark-bar-graph me-2"></i>Berichte & Abfragen
                </h2>
            </div>
            <div class="col-md-6 text-md-end">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Drucken
                </button>
            </div>
        </div>

        <!-- Meldungen -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Erfolg:</strong> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Schnellabfragen -->
        <div class="quick-query">
            <h5><i class="bi bi-lightning me-2"></i>Häufige Abfragen</h5>
            <p class="mb-3">Beantwortung typischer Fragen zum Gerätebestand</p>
            <div class="row">
                <div class="col-md-4">
                    <button type="button" class="btn w-100 mb-2" onclick="quickQuery('how_many_badminton_rackets')">
                        <i class="bi bi-question-circle me-2"></i>Wie viele Badmintonschläger verfügbar?
                    </button>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn w-100 mb-2" onclick="showInspectionQuery()">
                        <i class="bi bi-question-circle me-2"></i>Wann letzte technische Prüfung?
                    </button>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn w-100 mb-2" onclick="showLocationQuery()">
                        <i class="bi bi-question-circle me-2"></i>Geräte nach Halle/Lagerplatz?
                    </button>
                </div>
            </div>
        </div>

        <!-- Custom Query Form -->
        <div class="query-form" id="customQueryForm" style="display: none;">
            <h5><i class="bi bi-search me-2"></i>Benutzerdefinierte Abfrage</h5>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="query_type" class="form-label">Abfragetyp</label>
                        <select class="form-select" id="query_type" name="query_type" onchange="updateQueryParamField()">
                            <option value="">Bitte wählen...</option>
                            <option value="how_many_badminton_rackets">Badmintonschläger Bestand</option>
                            <option value="when_last_inspection">Letzte Inspektion (Inventarnr.)</option>
                            <option value="equipment_location">Geräte nach Halle</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="paramField" style="display: none;">
                        <label for="query_param" class="form-label">Parameter</label>
                        <input type="text" class="form-control" id="query_param" name="query_param" placeholder="">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="custom_query" value="1" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Abfrage ausführen
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Custom Query Result -->
        <?php if (isset($customQueryResult)): ?>
            <div class="query-result">
                <h6><i class="bi bi-check-circle me-2"></i>Abfrageergebnis</h6>
                <?php if (empty($customQueryResult)): ?>
                    <p class="text-muted">Keine Ergebnisse gefunden.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($customQueryResult[0]) as $column): ?>
                                        <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $column))); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customQueryResult as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo htmlspecialchars($value ?? '-'); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Bericht 1: Geräteübersicht -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="bi bi-pie-chart me-2"></i>Geräteübersicht
                </h5>
                <div class="export-buttons">
                    <a href="?report=equipment_overview&export=csv" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="?report=equipment_overview&export=pdf" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                    <a href="?report=equipment_overview&export=excel" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark-excel me-1"></i>Excel
                    </a>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $reports['equipment_overview']['total_large']; ?></div>
                    <div class="text-muted">Großgeräte (aktiv)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $reports['equipment_overview']['total_small_types']; ?></div>
                    <div class="text-muted">Kleingeräte-Typen</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($reports['equipment_overview']['total_small_items']); ?></div>
                    <div class="text-muted">Kleingeräte (gesamt)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-warning"><?php echo $reports['equipment_overview']['equipment_in_repair']; ?></div>
                    <div class="text-muted">In Reparatur</div>
                </div>
            </div>
        </div>

        <!-- Bericht 2: Inspektionsstatus -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="bi bi-clipboard-check me-2"></i>Inspektionsstatus
                </h5>
                <div class="export-buttons">
                    <a href="?report=overdue_inspections&export=csv" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="?report=overdue_inspections&export=pdf" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Überfällige Inspektionen (<?php echo count($reports['overdue_inspections']); ?>)
                    </h6>
                    <?php if (empty($reports['overdue_inspections'])): ?>
                        <p class="text-muted">Keine überfälligen Inspektionen.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Inventarnr.</th>
                                        <th>Gerät</th>
                                        <th>Halle</th>
                                        <th>Tage überfällig</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports['overdue_inspections'] as $item): ?>
                                        <tr class="table-danger">
                                            <td><?php echo htmlspecialchars($item['inventory_number']); ?></td>
                                            <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['hall_garage']); ?></td>
                                            <td><strong><?php echo $item['days_overdue']; ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <h6 class="text-warning">
                        <i class="bi bi-clock me-2"></i>
                        Fällige Inspektionen (30 Tage) (<?php echo count($reports['upcoming_inspections']); ?>)
                    </h6>
                    <?php if (empty($reports['upcoming_inspections'])): ?>
                        <p class="text-muted">Keine fälligen Inspektionen in den nächsten 30 Tagen.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Inventarnr.</th>
                                        <th>Gerät</th>
                                        <th>Halle</th>
                                        <th>Tage bis fällig</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports['upcoming_inspections'] as $item): ?>
                                        <tr class="table-warning">
                                            <td><?php echo htmlspecialchars($item['inventory_number']); ?></td>
                                            <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['hall_garage']); ?></td>
                                            <td><strong><?php echo $item['days_until']; ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bericht 3: Niedrige Bestände -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="bi bi-exclamation-circle me-2"></i>Niedrige Bestände
                </h5>
                <div class="export-buttons">
                    <a href="?report=low_stock&export=csv" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="?report=low_stock&export=pdf" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>

            <?php if (empty($reports['low_stock'])): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    Alle Bestände sind ausreichend. Keine Nachbestellung erforderlich.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Gerätetyp</th>
                                <th>Kategorie</th>
                                <th>Aktuell</th>
                                <th>Mindest</th>
                                <th>Fehlend</th>
                                <th>Lagerplatz</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports['low_stock'] as $item): ?>
                                <tr class="table-warning">
                                    <td><strong><?php echo htmlspecialchars($item['equipment_type']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><span class="badge bg-danger"><?php echo $item['quantity']; ?></span></td>
                                    <td><?php echo $item['min_quantity']; ?></td>
                                    <td><strong><?php echo $item['shortage']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['hall']); ?> - <?php echo htmlspecialchars($item['spindle_room']); ?></td>
                                    <td>
                                        <a href="small_equipment.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Bestand anpassen
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bericht 4: Geräte nach Halle -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="bi bi-building me-2"></i>Geräte nach Halle
                </h5>
                <div class="export-buttons">
                    <a href="?report=equipment_by_hall&export=csv" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="?report=equipment_by_hall&export=pdf" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Halle/Garage</th>
                            <th>Gesamt</th>
                            <th>Aktiv</th>
                            <th>In Reparatur</th>
                            <th>Ausgemustert</th>
                            <th>Verfügbarkeit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports['equipment_by_hall'] as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['hall_garage']); ?></strong></td>
                                <td><?php echo $item['total_equipment']; ?></td>
                                <td><span class="badge bg-success"><?php echo $item['active_equipment']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $item['repair_equipment']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $item['decommissioned_equipment']; ?></span></td>
                                <td>
                                    <?php
                                    $availability = round(($item['active_equipment'] / $item['total_equipment']) * 100, 1);
                                    $color = $availability >= 90 ? 'success' : ($availability >= 70 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $availability; ?>%">
                                            <?php echo $availability; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bericht 5: Inspektionsstatistiken -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="bi bi-graph-up me-2"></i>Inspektionsstatistiken
                </h5>
                <div class="export-buttons">
                    <a href="?report=inspection_stats&export=csv" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-file-earmark-csv me-1"></i>CSV
                    </a>
                    <a href="?report=inspection_stats&export=pdf" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $reports['inspection_stats']['total_inspections']; ?></div>
                    <div class="text-muted">Gesamt-Inspektionen</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $reports['inspection_stats']['this_year']; ?></div>
                    <div class="text-muted">Dieses Jahr</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-success"><?php echo $reports['inspection_stats']['passed']; ?></div>
                    <div class="text-muted">Bestanden</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-warning"><?php echo $reports['inspection_stats']['conditional']; ?></div>
                    <div class="text-muted">Mit Auflagen</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-danger"><?php echo $reports['inspection_stats']['failed']; ?></div>
                    <div class="text-muted">Nicht bestanden</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo round(($reports['inspection_stats']['passed'] / $reports['inspection_stats']['total_inspections']) * 100, 1); ?>%</div>
                    <div class="text-muted">Erfolgsquote</div>
                </div>
            </div>

            <h6 class="mt-4">Letzte Inspektionen</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Gerät</th>
                            <th>Inventarnr.</th>
                            <th>Prüfer</th>
                            <th>Ergebnis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports['recent_inspections'] as $item): ?>
                            <tr>
                                <td><?php echo formatDate($item['inspection_date']); ?></td>
                                <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['inventory_number']); ?></td>
                                <td><?php echo htmlspecialchars($item['inspector_name']); ?></td>
                                <td><?php echo getStatusBadge($item['result']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export-Info -->
        <div class="alert alert-info">
            <h6><i class="bi bi-info-circle me-2"></i>Export-Informationen</h6>
            <ul class="mb-0">
                <li><strong>CSV:</strong> Kompatibel mit Excel und anderen Tabellenkalkulationen</li>
                <li><strong>PDF:</strong> Druckbare Berichte für Dokumentation</li>
                <li><strong>Excel:</strong> Format für erweiterte Analyse</li>
                <li>Alle Exporte enthalten den aktuellen Datenstand zum Zeitpunkt des Downloads</li>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function quickQuery(type) {
            document.getElementById('customQueryForm').style.display = 'block';
            document.getElementById('query_type').value = type;
            updateQueryParamField();

            // Scroll zum Formular
            document.getElementById('customQueryForm').scrollIntoView({ behavior: 'smooth' });
        }

        function showInspectionQuery() {
            quickQuery('when_last_inspection');
            document.getElementById('query_param').placeholder = 'Inventarnummer eingeben (z.B. MK-2023-001)';
        }

        function showLocationQuery() {
            quickQuery('equipment_location');
            document.getElementById('query_param').placeholder = 'Halle eingeben (z.B. Halle 1, Halle 2, Halle 3)';
        }

        function updateQueryParamField() {
            const queryType = document.getElementById('query_type').value;
            const paramField = document.getElementById('paramField');
            const paramInput = document.getElementById('query_param');

            if (queryType === 'how_many_badminton_rackets') {
                paramField.style.display = 'none';
            } else {
                paramField.style.display = 'block';
                if (queryType === 'when_last_inspection') {
                    paramInput.placeholder = 'Inventarnummer eingeben (z.B. MK-2023-001)';
                } else if (queryType === 'equipment_location') {
                    paramInput.placeholder = 'Halle eingeben (z.B. Halle 1, Halle 2, Halle 3)';
                }
            }
        }

        // Auto-Refresh für überfällige Inspektionen (alle 5 Minuten)
        setInterval(() => {
            if (document.querySelector('.table-danger')) {
                location.reload();
            }
        }, 300000);

        // Print-Styles optimieren
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('.export-buttons, .btn').forEach(el => {
                el.style.display = 'none';
            });
        });

        window.addEventListener('afterprint', function() {
            location.reload();
        });
    </script>
</body>
</html>