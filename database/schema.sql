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

COMMIT;