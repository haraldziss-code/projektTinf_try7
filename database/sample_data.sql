-- Sportgeräteverwaltung BKT - Beispieldaten
-- Beispiel-Daten für Testing und Demonstration

USE sportgeraete;

-- Großgeräte-Beispieldaten
INSERT INTO large_equipment (inventory_number, equipment_name, equipment_type, hall_garage, location_description, last_inspection, next_inspection_due, status, notes, created_by) VALUES
('MK-2023-001', 'Turnkasten 5-teilig', 'Turnkasten', 'Halle 1', 'Links neben der Eingangstür', '2024-01-15', '2025-01-15', 'aktiv', 'Guter Zustand, alle Teile vorhanden', 1),
('MK-2023-002', 'Turnkasten 3-teilig', 'Turnkasten', 'Halle 1', 'Rechts neben der Wand', '2024-02-20', '2025-02-20', 'aktiv', 'Kleinere Kratzer an der Oberfläche', 1),
('MK-2023-003', 'Turnbank', 'Turnbank', 'Halle 1', 'Mitte der Halle', '2023-12-10', '2024-12-10', 'aktiv', 'Neue Polsterung im letzten Jahr', 1),
('MK-2023-004', 'Barren (männlich)', 'Barren', 'Halle 2', 'Ganz hinten rechts', '2024-03-01', '2025-03-01', 'in Reparatur', 'Griff beschädigt, muss repariert werden', 1),
('MK-2023-005', 'Barren (weiblich)', 'Barren', 'Halle 2', 'Neben dem männlichen Barren', '2024-01-08', '2025-01-08', 'aktiv', 'Kürzlich neu lackiert', 1),
('MK-2023-006', 'Tischtennisplatte 1', 'Tischtennistisch', 'Halle 1', 'Neben dem Eingang', '2024-01-20', '2025-01-20', 'aktiv', 'Netz intakt, Oberfläche in gutem Zustand', 1),
('MK-2023-007', 'Tischtennisplatte 2', 'Tischtennistisch', 'Halle 1', 'Gegenüber von Platte 1', '2023-11-15', '2024-11-15', 'aktiv', 'Leichte Kratzer auf der Oberfläche', 1),
('MK-2023-008', 'Sprungbrett', 'Sprungbrett', 'Halle 2', 'Beim Sprunggraben', '2024-02-10', '2025-02-10', 'aktiv', 'Federung funktioniert einwandfrei', 1),
('MK-2023-009', 'Reck', 'Reck', 'Halle 2', 'An der Rückwand', '2023-10-05', '2024-10-05', 'aktiv', 'Griffe müssen bald ausgetauscht werden', 1),
('MK-2023-010', 'Ringe', 'Ringe', 'Halle 2', 'An der Decke befestigt', '2024-01-25', '2025-01-25', 'aktiv', 'Seile in gutem Zustand', 1),
('MK-2023-011', 'Pauschen', 'Pauschen', 'Halle 1', 'Kleine Geräte-Ecke', '2023-09-20', '2024-09-20', 'aktiv', 'Polsterung noch gut', 1),
('MK-2023-012', 'Kletterwand (klein)', 'Kletterwand', 'Halle 3', 'GBB-Bereich', '2024-03-15', '2025-03-15', 'aktiv', 'Alle Griffe vorhanden und fest', 1),
('MK-2023-013', 'Minitrampolin', 'Minitrampolin', 'Halle 1', 'Gymnastik-Ecke', '2024-02-28', '2025-02-28', 'aktiv', 'Federung geprüft und sicher', 1),
('MK-2023-014', 'Sprossenwand', 'Sprossenwand', 'Halle 2', 'Linke Wand', '2024-01-05', '2025-01-05', 'aktiv', 'Alle Sprossen fest', 1),
('MK-2023-015', 'Matten (groß)', 'Matten', 'Halle 1', 'Im Schrank links', '2023-12-01', '2024-12-01', 'aktiv', '10 Stück, alle in gutem Zustand', 1);

-- Inspektions-Beispieldaten
INSERT INTO inspections (equipment_id, inspection_date, inspector_name, result, notes, next_inspection, inspected_by) VALUES
(1, '2024-01-15', 'Max Mustermann', 'bestanden', 'Alle Teile intakt, keine Mängel festgestellt', '2025-01-15', 2),
(2, '2024-02-20', 'Max Mustermann', 'bestanden', 'Kleine Kratzer, aber sicher in der Nutzung', '2025-02-20', 2),
(3, '2023-12-10', 'Max Mustermann', 'bestanden', 'Neue Polsterung, sehr guter Zustand', '2024-12-10', 2),
(4, '2024-03-01', 'Hans Hausmeister', 'nicht bestanden', 'Griff ist locker und muss dringend repariert werden', '2024-03-15', 3),
(5, '2024-01-08', 'Max Mustermann', 'bestanden', 'Neue Lackierung, alle Teile fest', '2025-01-08', 2),
(6, '2024-01-20', 'Hans Hausmeister', 'bestanden', 'Netz intakt, Tisch gerade', '2025-01-20', 3),
(7, '2023-11-15', 'Max Mustermann', 'mit Auflagen', 'Kratzer sollten mit Lack behandelt werden', '2024-11-15', 2),
(8, '2024-02-10', 'Hans Hausmeister', 'bestanden', 'Federung perfekt, kein Mangel', '2025-02-10', 3),
(9, '2023-10-05', 'Max Mustermann', 'mit Auflagen', 'Griffe zeigen Verschleiß, sollten im nächsten Jahr getauscht werden', '2024-10-05', 2),
(10, '2024-01-25', 'Hans Hausmeister', 'bestanden', 'Seile intakt, Halterungen fest', '2025-01-25', 3);

-- Kleingeräte-Beispieldaten
INSERT INTO small_equipment (equipment_type, category_id, quantity, storage_location_id, characteristics, min_quantity, notes, created_by) VALUES
('Basketball (Größe 7)', 1, 15, 1, 'Orange, Leder', 10, 'Alle in gutem Zustand', 2),
('Basketball (Größe 5)', 1, 12, 2, 'Orange, Kunststoff', 8, 'Für jüngere Schüler', 2),
('Volleyball', 2, 20, 1, 'Weiß/Blau, Indoor', 15, 'Alle geprüft und bereit', 2),
('Fußball (Größe 5)', 3, 25, 3, 'Schwarz/Weiß', 15, 'Einige zeigen leichte Abnutzung', 2),
('Handball', 4, 18, 4, 'Gelb/Schwarz', 12, 'Guter Grip', 2),
('Softball', 5, 30, 5, 'Weich, verschiedene Farben', 20, 'Für verschiedene Altersgruppen', 2),
('Gymnastikball (65cm)', 6, 8, 6, 'Blau, Anti-Burst', 5, 'Alle halten Luft', 2),
('Gymnastikball (75cm)', 6, 6, 6, 'Rot, Anti-Burst', 4, 'Für größere Personen', 2),
('Tischtennisschläger', 7, 12, 7, 'Verschiedene Marken', 8, 'Griffe in gutem Zustand', 2),
('Tischtennisbälle', 7, 50, 7, 'Weiß, 3 Sterne', 30, 'Einige haben Dellen', 2),
('Unihockeyschläger', 8, 20, 8, 'Blau/Schwarz', 12, 'Linkshänder-Schläger vorhanden', 2),
('Unihockeybälle', 8, 25, 8, 'Orange, Lochbälle', 15, 'Alle gleichmäßig', 2),
('Badmintonschläger', 9, 16, 9, 'Verschiedene Gewichte', 10, 'Besaitung geprüft', 2),
('Badmintonbälle (Feder)', 9, 30, 9, 'Weiße Federn', 20, 'Einige beschädigt', 2),
('Racketball Schläger', 10, 8, 10, 'Schwarz/Gelb', 5, 'Griffe intakt', 2),
('Kugelstoß-Kugel (4kg)', 11, 4, 1, 'Metall, 4kg', 2, 'Für Wettkampf', 2),
('Kugelstoß-Kugel (5kg)', 11, 3, 1, 'Metall, 5kg', 2, 'Für ältere Schüler', 2),
('Kugelstoß-Kugel (3kg)', 11, 6, 2, 'Metall, 3kg', 3, 'Für jüngere Schüler', 2),
('Frisbee', 12, 15, 11, 'Verschiedene Farben', 10, 'Leichte Kratzer', 2),
('Isomatte (groß)', 13, 20, 12, 'Blau, 200cm x 100cm', 15, 'Alle sauber', 2),
('Isomatte (klein)', 13, 25, 12, 'Grün, 150cm x 80cm', 15, 'Für Kinder', 2),
('Fußball-Netz', 14, 3, 9, 'Standard Größe', 2, 'Alle einsatzbereit', 2),
('Volleyball-Netz', 14, 2, 9, 'Hallennetz', 2, 'Höhe verstellbar', 2),
('Badminton-Netz', 14, 4, 10, 'Portabel', 2, 'Mit Ständer', 2),
('Springseil', 15, 20, 11, 'Verschiedene Längen', 10, 'Griffe intakt', 2),
('Markierungsbänder', 16, 30, 12, 'Verschiedene Farben', 20, 'Für Feldmarkierung', 2),
('Football', 17, 8, 3, 'Braun, Leder', 5, 'American Football', 2);

-- Mengenänderungs-Log für Kleingeräte
INSERT INTO small_equipment_log (equipment_id, quantity_change, old_quantity, new_quantity, change_reason, changed_by) VALUES
(1, 5, 10, 15, 'Neue Lieferung Basketbälle', 2),
(2, 3, 9, 12, 'Defekte Bälle ersetzt', 2),
(3, -2, 22, 20, '2 Bälle verloren bei Turnier', 3),
(4, 5, 20, 25, 'Neue Fußbälle gekauft', 2),
(5, 2, 16, 18, 'Ersatzhandbälle', 2),
(6, 10, 20, 30, 'Neue Softbälle für Grundschule', 2),
(7, -1, 7, 6, 'Ein Ball geplatzt', 3),
(8, 2, 4, 6, 'Zusätzliche Bälle für neue Klasse', 2),
(9, 4, 8, 12, 'Neue Schläger gekauft', 2),
(10, 20, 30, 50, 'Große Packung Tischtennisbälle', 2),
(11, 8, 12, 20, 'Unihockey-Set erweitert', 2),
(12, 15, 10, 25, 'Zusätzliche Bälle', 2),
(13, 6, 10, 16, 'Neue Badmintonschläger', 2),
(14, 20, 10, 30, 'Vorrat an Federbällen aufgestockt', 2);

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
(2, 'add_small_equipment', 'Kleingerät hinzugefügt: Basketball (Größe 7)', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 'update_small_equipment', 'Menge aktualisiert: Basketball (Größe 7) von 10 auf 15', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'update_small_equipment', 'Menge aktualisiert: Volleyball von 22 auf 20', '127.0.0.1', 'Mozilla/5.0 (Demo)', DATE_SUB(NOW(), INTERVAL 1 DAY));

COMMIT;