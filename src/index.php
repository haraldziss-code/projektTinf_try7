<?php
/**
 * Sportgeräteverwaltung BKT - Haupt-Dashboard
 * Übersichtsseite mit den wichtigsten Informationen und Schnellzugriffen
 */

define('SECURE_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Login erforderlich
requireLogin();

// Aktuellen Benutzer abrufen
$currentUser = getCurrentUser();

// Dashboard-Daten abrufen
$dashboardData = getDashboardData();

// URL-Parameter verarbeiten
$message = '';
$error = '';

if (isset($_GET['message'])) {
    $message = sanitizeInput($_GET['message']);
}

if (isset($_GET['error'])) {
    $error = sanitizeInput($_GET['error']);
}

/**
 * Dashboard-Daten aus der Datenbank abrufen
 */
function getDashboardData() {
    $data = [];

    // Fällige Inspektionen (nächste 30 Tage)
    $sql = "SELECT le.id, le.inventory_number, le.equipment_name, le.hall_garage,
                   le.next_inspection_due, DATEDIFF(le.next_inspection_due, CURDATE()) as days_until
            FROM large_equipment le
            WHERE le.status = 'aktiv'
              AND le.next_inspection_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY le.next_inspection_due ASC
            LIMIT 10";

    $data['upcoming_inspections'] = fetchAll($sql);

    // Geräte in Reparatur
    $sql = "SELECT COUNT(*) as count FROM large_equipment WHERE status = 'in Reparatur'";
    $result = fetchOne($sql);
    $data['equipment_in_repair'] = $result['count'];

    // Gesamtanzahl Großgeräte
    $sql = "SELECT COUNT(*) as count FROM large_equipment WHERE status = 'aktiv'";
    $result = fetchOne($sql);
    $data['total_large_equipment'] = $result['count'];

    // Gesamtanzahl Kleingeräte
    $sql = "SELECT SUM(quantity) as total FROM small_equipment";
    $result = fetchOne($sql);
    $data['total_small_equipment'] = $result['total'] ?? 0;

    // Letzte Inspektionen
    $sql = "SELECT i.inspection_date, i.result, le.equipment_name, le.inventory_number,
                   u.full_name as inspector
            FROM inspections i
            JOIN large_equipment le ON i.equipment_id = le.id
            LEFT JOIN users u ON i.inspected_by = u.id
            ORDER BY i.inspection_date DESC
            LIMIT 5";

    $data['recent_inspections'] = fetchAll($sql);

    // Geräte nach Halle
    $sql = "SELECT hall_garage, COUNT(*) as count
            FROM large_equipment
            WHERE status = 'aktiv'
            GROUP BY hall_garage
            ORDER BY hall_garage";

    $data['equipment_by_hall'] = fetchAll($sql);

    // Niedrige Bestände Kleingeräte
    $sql = "SELECT se.equipment_type, sec.category_name, se.quantity, se.min_quantity,
                   sl.hall, sl.spindle_room
            FROM small_equipment se
            JOIN small_equipment_categories sec ON se.category_id = sec.id
            JOIN storage_locations sl ON se.storage_location_id = sl.id
            WHERE se.quantity <= se.min_quantity
            ORDER BY se.quantity ASC
            LIMIT 10";

    $data['low_stock_items'] = fetchAll($sql);

    // Letzte Aktivitäten
    $sql = "SELECT action, details, created_at
            FROM activity_logs
            ORDER BY created_at DESC
            LIMIT 5";

    $data['recent_activities'] = fetchAll($sql);

    return $data;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-left: 4px solid;
            margin-bottom: 1.5rem;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info { border-left-color: #17a2b8; }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .quick-action {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            margin-bottom: 1rem;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .alert-item {
            background: white;
            border-left: 4px solid #dc3545;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .alert-item.warning {
            border-left-color: #ffc107;
        }

        .user-info {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem;
            backdrop-filter: blur(10px);
        }

        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-2">🏃 Sportgeräteverwaltung</h1>
                    <p class="mb-0 opacity-75">Berufskolleg für Technik - Dashboard</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="user-info d-inline-block">
                        <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($currentUser['role']); ?></small>
                    </div>
                    <div class="dropdown d-inline-block ms-3">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Einstellungen</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Meldungen -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Fehler:</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Erfolg:</strong> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistik-Karten -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="stat-number text-primary"><?php echo $dashboardData['total_large_equipment']; ?></div>
                    <div class="stat-label">Großgeräte aktiv</div>
                    <small class="text-muted">Gesamtbestand</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-number text-success"><?php echo number_format($dashboardData['total_small_equipment']); ?></div>
                    <div class="stat-label">Kleingeräte</div>
                    <small class="text-muted">Gesamtmenge</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-number text-warning"><?php echo $dashboardData['equipment_in_repair']; ?></div>
                    <div class="stat-label">In Reparatur</div>
                    <small class="text-muted">Großgeräte</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card danger">
                    <div class="stat-number text-danger"><?php echo count($dashboardData['upcoming_inspections']); ?></div>
                    <div class="stat-label">Fällige Inspektionen</div>
                    <small class="text-muted">Nächste 30 Tage</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Linke Spalte -->
            <div class="col-md-8">

                <!-- Fällige Inspektionen -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                        Fällige Inspektionen
                    </h5>
                    <?php if (empty($dashboardData['upcoming_inspections'])): ?>
                        <p class="text-muted">Keine Inspektionen in den nächsten 30 Tagen fällig.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Inventarnr.</th>
                                        <th>Gerät</th>
                                        <th>Halle</th>
                                        <th>fällig am</th>
                                        <th>in Tagen</th>
                                        <th>Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['upcoming_inspections'] as $inspection): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($inspection['inventory_number']); ?></td>
                                            <td><?php echo htmlspecialchars($inspection['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($inspection['hall_garage']); ?></td>
                                            <td><?php echo formatDate($inspection['next_inspection_due']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $inspection['days_until'] <= 7 ? 'danger' : 'warning'; ?>">
                                                    <?php echo $inspection['days_until']; ?> Tage
                                                </span>
                                            </td>
                                            <td>
                                                <a href="pages/inspections.php?equipment_id=<?php echo $inspection['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-clipboard-check"></i> Prüfen
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Geräte nach Halle -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="bi bi-building text-info me-2"></i>
                        Geräte nach Halle
                    </h5>
                    <div class="row">
                        <?php foreach ($dashboardData['equipment_by_hall'] as $hall): ?>
                            <div class="col-md-4">
                                <div class="stat-card info">
                                    <div class="stat-number text-info"><?php echo $hall['count']; ?></div>
                                    <div class="stat-label"><?php echo htmlspecialchars($hall['hall_garage']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Letzte Inspektionen -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="bi bi-clock-history text-secondary me-2"></i>
                        Letzte Inspektionen
                    </h5>
                    <?php if (empty($dashboardData['recent_inspections'])): ?>
                        <p class="text-muted">Noch keine Inspektionen durchgeführt.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Gerät</th>
                                        <th>Inventarnr.</th>
                                        <th>Ergebnis</th>
                                        <th>Prüfer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['recent_inspections'] as $inspection): ?>
                                        <tr>
                                            <td><?php echo formatDate($inspection['inspection_date']); ?></td>
                                            <td><?php echo htmlspecialchars($inspection['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($inspection['inventory_number']); ?></td>
                                            <td><?php echo getStatusBadge($inspection['result']); ?></td>
                                            <td><?php echo htmlspecialchars($inspection['inspector'] ?? 'System'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Rechte Spalte -->
            <div class="col-md-4">

                <!-- Schnellzugriffe -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="bi bi-lightning text-warning me-2"></i>
                        Schnellzugriffe
                    </h5>
                    <?php if (hasPermission('add_equipment')): ?>
                        <a href="pages/large_equipment.php?action=add" class="quick-action">
                            <i class="bi bi-plus-circle text-success"></i>
                            <strong>Großgerät hinzufügen</strong>
                            <small class="text-muted d-block">Neues Gerät erfassen</small>
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('add_inspection')): ?>
                        <a href="pages/inspections.php?action=add" class="quick-action">
                            <i class="bi bi-clipboard-check text-primary"></i>
                            <strong>Inspektion durchführen</strong>
                            <small class="text-muted d-block">Gerät prüfen</small>
                        </a>
                    <?php endif; ?>

                    <a href="pages/large_equipment.php" class="quick-action">
                        <i class="bi bi-box text-info"></i>
                        <strong>Großgeräte</strong>
                        <small class="text-muted d-block">Verwalten</small>
                    </a>

                    <a href="pages/small_equipment.php" class="quick-action">
                        <i class="bi bi-basket text-warning"></i>
                        <strong>Kleingeräte</strong>
                        <small class="text-muted d-block">Mengen prüfen</small>
                    </a>

                    <a href="pages/reports.php" class="quick-action">
                        <i class="bi bi-file-earmark-bar-graph text-secondary"></i>
                        <strong>Berichte</strong>
                        <small class="text-muted d-block">Abfragen & Export</small>
                    </a>
                </div>

                <!-- Niedrige Bestände -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="bi bi-exclamation-circle text-danger me-2"></i>
                        Niedrige Bestände
                    </h5>
                    <?php if (empty($dashboardData['low_stock_items'])): ?>
                        <p class="text-muted">Alle Bestände sind ausreichend.</p>
                    <?php else: ?>
                        <?php foreach ($dashboardData['low_stock_items'] as $item): ?>
                            <div class="alert-item <?php echo $item['quantity'] == 0 ? '' : 'warning'; ?>">
                                <strong><?php echo htmlspecialchars($item['equipment_type']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($item['hall']); ?> - <?php echo htmlspecialchars($item['spindle_room']); ?><br>
                                    Bestand: <?php echo $item['quantity']; ?> / Min: <?php echo $item['min_quantity']; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Letzte Aktivitäten -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="bi bi-activity text-primary me-2"></i>
                        Letzte Aktivitäten
                    </h5>
                    <?php if (empty($dashboardData['recent_activities'])): ?>
                        <p class="text-muted">Keine Aktivitäten protokolliert.</p>
                    <?php else: ?>
                        <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                            <div class="activity-item">
                                <strong><?php echo htmlspecialchars($activity['action']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small><br>
                                <small class="text-muted"><?php echo formatDateTime($activity['created_at'], 'd.m. H:i'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Berufskolleg für Technik</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">Version <?php echo APP_VERSION; ?></small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>