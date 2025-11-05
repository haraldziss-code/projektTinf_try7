-- Sportgeräteverwaltung BKT - Datenbankschema
-- Erstellt für Berufskolleg für Technik

-- Datenbank erstellen und verwenden
CREATE DATABASE IF NOT EXISTS sportgeraete CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sportgeraete;

-- Benutzertabelle
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('Lehrer', 'Hausmeister', 'Admin') NOT NULL DEFAULT 'Lehrer',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- Lagerplätze-Tabelle
CREATE TABLE storage_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hall ENUM('Halle 1', 'Halle 2', 'Regieraum', 'Keller') NOT NULL,
    spindle_room VARCHAR(50) NOT NULL,
    capacity INT DEFAULT 100,
    location_type ENUM('Spind', 'Raum', 'Garage') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (hall, spindle_room)
);

-- Großgeräte-Tabelle
CREATE TABLE large_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_number VARCHAR(50) UNIQUE NOT NULL,
    equipment_name VARCHAR(100) NOT NULL,
    equipment_type VARCHAR(100) NOT NULL,
    hall_garage ENUM('Halle 1', 'Halle 2', 'Halle 3') NOT NULL,
    location_description TEXT,
    last_inspection DATE,
    next_inspection_due DATE,
    status ENUM('aktiv', 'in Reparatur', 'ausgemustert') DEFAULT 'aktiv',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_inventory (inventory_number),
    INDEX idx_hall (hall_garage),
    INDEX idx_status (status),
    INDEX idx_inspection_due (next_inspection_due)
);

-- Inspektionen-Tabelle
CREATE TABLE inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspector_name VARCHAR(100) NOT NULL,
    result ENUM('bestanden', 'nicht bestanden', 'mit Auflagen') NOT NULL,
    notes TEXT,
    next_inspection DATE NOT NULL,
    inspected_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES large_equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (inspected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_equipment (equipment_id),
    INDEX idx_date (inspection_date),
    INDEX idx_result (result),
    INDEX idx_next_inspection (next_inspection)
);

-- Kleingeräte-Kategorien
CREATE TABLE small_equipment_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    category_type ENUM('Bälle', 'Schläger', 'Sonstiges') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category_name, category_type)
);

-- Kleingeräte-Tabelle
CREATE TABLE small_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_type VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    storage_location_id INT NOT NULL,
    characteristics TEXT, -- Farbe, Material, Gewicht etc.
    min_quantity INT DEFAULT 5,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (category_id) REFERENCES small_equipment_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (storage_location_id) REFERENCES storage_locations(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (equipment_type),
    INDEX idx_location (storage_location_id),
    INDEX idx_quantity (quantity),
    INDEX idx_category (category_id)
);

-- Mengenänderungs-Log für Kleingeräte
CREATE TABLE small_equipment_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    quantity_change INT NOT NULL, -- positiv für Zugänge, negativ für Abgänge
    old_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    change_reason VARCHAR(200) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES small_equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_equipment (equipment_id),
    INDEX idx_date (changed_at),
    INDEX idx_user (changed_by)
);

-- Aktivitäts-Log für Protokollierung
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Sessions für Authentifizierung
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
);

-- Trigger: nächste Inspektion automatisch berechnen
DELIMITER //
CREATE TRIGGER after_inspection_insert
AFTER INSERT ON inspections
FOR EACH ROW
BEGIN
    UPDATE large_equipment
    SET last_inspection = NEW.inspection_date,
        next_inspection_due = NEW.next_inspection
    WHERE id = NEW.equipment_id;
END//
DELIMITER ;

-- Trigger: Log für Mengenänderungen
DELIMITER //
CREATE TRIGGER after_small_equipment_update
AFTER UPDATE ON small_equipment
FOR EACH ROW
BEGIN
    IF OLD.quantity <> NEW.quantity THEN
        INSERT INTO small_equipment_log
        (equipment_id, quantity_change, old_quantity, new_quantity, change_reason, changed_by)
        VALUES
        (NEW.id, NEW.quantity - OLD.quantity, OLD.quantity, NEW.quantity, 'Mengenaktualisierung', NEW.created_by);
    END IF;
END//
DELIMITER ;

-- Standard-Lagerplätze einfügen
INSERT INTO storage_locations (hall, spindle_room, location_type, capacity) VALUES
('Halle 1', 'Spind 1', 'Spind', 50),
('Halle 1', 'Spind 2', 'Spind', 50),
('Halle 1', 'Spind 3', 'Spind', 50),
('Halle 1', 'Spind 4', 'Spind', 50),
('Halle 2', 'Spind 1', 'Spind', 50),
('Halle 2', 'Spind 2', 'Spind', 50),
('Halle 2', 'Spind 3', 'Spind', 50),
('Halle 2', 'Spind 4', 'Spind', 50),
('Regieraum', 'Hauptregal', 'Raum', 200),
('Keller', 'Metallspind', 'Spind', 100);

-- Standard-Kategorien für Kleingeräte
INSERT INTO small_equipment_categories (category_name, category_type, description) VALUES
('Basketball', 'Bälle', 'Standard Basketbälle für die Halle'),
('Volleyball', 'Bälle', 'Standard Volleybälle'),
('Fußball', 'Bälle', 'Standard Fußbälle'),
('Handball', 'Bälle', 'Standard Handbälle'),
('Softball', 'Bälle', 'Softbälle für verschiedene Aktivitäten'),
('Gymnastikball', 'Bälle', 'Verschiedene Größen'),
('Tischtennis', 'Schläger', 'Tischtennisschläger und Bälle'),
('Unihockey', 'Schläger', 'Unihockeyschläger und Bälle'),
('Badminton', 'Schläger', 'Badmintonschläger und Bälle'),
('Racketball', 'Schläger', 'Racketball Schläger'),
('Kugelstoß-Kugel', 'Sonstiges', 'Kugeln mit verschiedenen Gewichten'),
('Frisbee', 'Sonstiges', 'Frisbees verschiedene Größen'),
('Isomatte/Fitnessmatte', 'Sonstiges', 'Matten für Gymnastik und Fitness'),
('Netze', 'Sonstiges', 'Netze für verschiedene Sportarten'),
('Springseil', 'Sonstiges', 'Springseile verschiedener Längen'),
('Markierungsbänder', 'Sonstiges', 'Bänder für Feldmarkierung'),
('Football', 'Bälle', 'Football für American Football');

-- Admin-Benutzer erstellen (Passwort: admin123)
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@bkt-sport.de', 'Admin'),
('lehrer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Max Mustermann', 'm.mustermann@bkt.de', 'Lehrer'),
('hausmeister', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hans Hausmeister', 'h.hausmeister@bkt.de', 'Hausmeister');

-- Beispieldaten für Großgeräte
INSERT INTO large_equipment (inventory_number, equipment_name, equipment_type, hall_garage, location_description, last_inspection, next_inspection_due, status, notes, created_by) VALUES
('MK-2023-001', 'Turnkasten 5-teilig', 'Turnkasten', 'Halle 1', 'Links neben der Eingangstür', '2024-01-15', '2025-01-15', 'aktiv', 'Guter Zustand, alle Teile vorhanden', 1),
('MK-2023-002', 'Turnkasten 3-teilig', 'Turnkasten', 'Halle 1', 'Rechts neben der Wand', '2024-02-20', '2025-02-20', 'aktiv', 'Kleinere Kratzer an der Oberfläche', 1),
('MK-2023-003', 'Turnbank', 'Turnbank', 'Halle 1', 'Mitte der Halle', '2023-12-10', '2024-12-10', 'aktiv', 'Neue Polsterung im letzten Jahr', 1),
('MK-2023-004', 'Barren (männlich)', 'Barren', 'Halle 2', 'Ganz hinten rechts', '2024-03-01', '2025-03-01', 'in Reparatur', 'Griff beschädigt, muss repariert werden', 1),
('MK-2023-005', 'Barren (weiblich)', 'Barren', 'Halle 2', 'Neben dem männlichen Barren', '2024-01-08', '2025-01-08', 'aktiv', 'Kürzlich neu lackiert', 1),
('MK-2023-006', 'Tischtennisplatte 1', 'Tischtennistisch', 'Halle 1', 'Neben dem Eingang', '2024-01-20', '2025-01-20', 'aktiv', 'Netz intakt, Oberfläche in gutem Zustand', 1),
('MK-2023-007', 'Tischtennisplatte 2', 'Tischtennistisch', 'Halle 1', 'Gegenüber von Platte 1', '2023-11-15', '2024-11-15', 'aktiv', 'Leichte Kratzer auf der Oberfläche', 1),
('MK-2023-008', 'Sprungbrett', 'Sprungbrett', 'Halle 2', 'Beim Sprunggraben', '2024-02-10', '2025-02-10', 'aktiv', 'Federung funktioniert einwandfrei', 1);

-- Beispieldaten für Inspektionen
INSERT INTO inspections (equipment_id, inspection_date, inspector_name, result, notes, next_inspection, inspected_by) VALUES
(1, '2024-01-15', 'Max Mustermann', 'bestanden', 'Alle Teile intakt, keine Mängel festgestellt', '2025-01-15', 2),
(2, '2024-02-20', 'Max Mustermann', 'bestanden', 'Kleine Kratzer, aber sicher in der Nutzung', '2025-02-20', 2),
(3, '2023-12-10', 'Max Mustermann', 'bestanden', 'Neue Polsterung, sehr guter Zustand', '2024-12-10', 2),
(4, '2024-03-01', 'Hans Hausmeister', 'nicht bestanden', 'Griff ist locker und muss dringend repariert werden', '2024-03-15', 3),
(5, '2024-01-08', 'Max Mustermann', 'bestanden', 'Neue Lackierung, alle Teile fest', '2025-01-08', 2),
(6, '2024-01-20', 'Hans Hausmeister', 'bestanden', 'Netz intakt, Tisch gerade', '2025-01-20', 3),
(7, '2023-11-15', 'Max Mustermann', 'mit Auflagen', 'Kratzer sollten mit Lack behandelt werden', '2024-11-15', 2),
(8, '2024-02-10', 'Hans Hausmeister', 'bestanden', 'Federung perfekt, kein Mangel', '2025-02-10', 3);

-- Beispieldaten für Kleingeräte
INSERT INTO small_equipment (equipment_type, category_id, quantity, storage_location_id, characteristics, min_quantity, notes, created_by) VALUES
('Basketball (Größe 7)', 1, 15, 1, 'Orange, Leder', 10, 'Alle in gutem Zustand', 2),
('Basketball (Größe 5)', 1, 12, 2, 'Orange, Kunststoff', 8, 'Für jüngere Schüler', 2),
('Volleyball', 2, 20, 1, 'Weiß/Blau, Indoor', 15, 'Alle geprüft und bereit', 2),
('Fußball (Größe 5)', 3, 25, 3, 'Schwarz/Weiß', 15, 'Einige zeigen leichte Abnutzung', 2),
('Handball', 4, 18, 4, 'Gelb/Schwarz', 12, 'Guter Grip', 2),
('Softball', 5, 30, 5, 'Weich, verschiedene Farben', 20, 'Für verschiedene Altersgruppen', 2),
('Gymnastikball (65cm)', 6, 8, 6, 'Blau, Anti-Burst', 5, 'Alle halten Luft', 2),
('Tischtennisschläger', 7, 12, 7, 'Verschiedene Marken', 8, 'Griffe in gutem Zustand', 2),
('Tischtennisbälle', 7, 50, 7, 'Weiß, 3 Sterne', 30, 'Einige haben Dellen', 2),
('Unihockeyschläger', 8, 20, 8, 'Blau/Schwarz', 12, 'Linkshänder-Schläger vorhanden', 2),
('Unihockeybälle', 8, 25, 8, 'Orange, Lochbälle', 15, 'Alle gleichmäßig', 2),
('Badmintonschläger', 9, 16, 9, 'Verschiedene Gewichte', 10, 'Besaitung geprüft', 2),
('Badmintonbälle (Feder)', 9, 30, 9, 'Weiße Federn', 20, 'Einige beschädigt', 2),
('Springseil', 15, 20, 11, 'Verschiedene Längen', 10, 'Griffe intakt', 2),
('Markierungsbänder', 16, 30, 12, 'Verschiedene Farben', 20, 'Für Feldmarkierung', 2);

-- Aktivitäts-Log für Demo
INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES
(1, 'login', 'Benutzer angemeldet: admin', '127.0.0.1', 'Mozilla/5.0 (Demo)', NOW()),
(2, 'login', 'Benutzer angemeldet: lehrer1', '127.0.0.1', 'Mozilla/5.0 (Demo)', NOW()),
(3, 'login', 'Benutzer angemeldet: hausmeister', '127.0.0.1', 'Mozilla/5.0 (Demo)', NOW()),
(2, 'add_large_equipment', 'Großgerät hinzugefügt: MK-2023-001 - Turnkasten 5-teilig', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'add_large_equipment', 'Großgerät hinzugefügt: MK-2023-002 - Turnkasten 3-teilig', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'edit_large_equipment', 'Großgerät bearbeitet: MK-2023-004 - Barren (männlich)', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'add_inspection', 'Inspektion durchgeführt für Gerät MK-2023-001', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'add_inspection', 'Inspektion durchgeführt für Gerät MK-2023-004', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'add_small_equipment', 'Kleingerät hinzugefügt: Basketball (Größe 7)', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 3 DAY));

COMMIT;