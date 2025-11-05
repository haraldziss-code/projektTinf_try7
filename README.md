# Sportgeräteverwaltung BKT

Digitale Verwaltungssystem für Sportgeräte des Berufskollegs für Technik mit webbasiertem Frontend und Datenbank-Backend zur Verfolgung von Groß- und Kleingeräten, Inspektionen und Lagerplätzen.

## Systemübersicht

Dieses System wurde im Rahmen des Praktikums für die Sportfachkonferenz des Berufskollegs für Technik entwickelt, um eine digitale Dokumentation und Verwaltung der Sportgeräte in der Sporthalle zu ermöglichen.

### Hauptfunktionen

- **Großgeräte-Verwaltung**: Inventarnummern, Inspektionsdaten, Standortverwaltung
- **Kleingeräte-Verwaltung**: Mengenverwaltung, Lagerplatz-Übersicht, Bestandskontrolle
- **Inspektions-Management**: TÜV-ähnliche Sicherheitskontrollen, Historie
- **Berichte & Abfragen**: Export-Funktionen, Statistiken, Inventurlisten
- **Benutzerverwaltung**: Rollenbasierte Rechte (Lehrer, Hausmeister, Admin)

## Technologie-Stack

- **Backend**: PHP 8.1 mit Apache Webserver
- **Datenbank**: MySQL/MariaDB 8.0
- **Frontend**: HTML5, CSS3, JavaScript (Bootstrap 5)
- **Containerisierung**: Docker mit docker-compose
- **Administratives**: phpMyAdmin für Datenbankzugriff

## Projektstruktur

```
sportgeraete/
├── docker-compose.yml          # Docker-Konfiguration
├── apache.conf                 # Apache-Konfiguration
├── src/                        # Web-Anwendung
│   ├── index.php              # Haupt-Dashboard
│   ├── login.php              # Login-Seite
│   ├── logout.php             # Logout-Funktion
│   ├── assets/
│   │   ├── css/style.css      # Custom Styles
│   │   ├── js/main.js         # JavaScript-Funktionen
│   │   └── images/            # Bilder und Icons
│   ├── includes/
│   │   ├── config.php         # Datenbank-Konfiguration
│   │   ├── auth.php           # Authentifizierung
│   │   └── functions.php      # Hilfsfunktionen
│   ├── pages/
│   │   ├── dashboard.php      # Dashboard
│   │   ├── large_equipment.php # Großgeräte-Verwaltung
│   │   ├── small_equipment.php # Kleingeräte-Verwaltung
│   │   ├── inspections.php     # Inspektions-Management
│   │   └── reports.php         # Berichte & Abfragen
│   └── api/                   # API-Endpunkte (zukünftig)
├── database/
│   ├── schema.sql             # Datenbank-Schema
│   └── sample_data.sql        # Beispieldaten
└── README.md                  # Diese Datei
```

## Installation & Start

### Voraussetzungen

- Docker und Docker Compose installiert
- Mindestens 2 GB RAM
- 2 GB freier Speicherplatz

### Schnellstart

1. **Repository klonen oder entpacken**
   ```bash
   cd projektTinf_try7
   ```

2. **Docker-Container starten**
   ```bash
   docker-compose up -d
   ```

3. **Datenbank initialisieren**
   - Die Datenbank wird automatisch mit Schema und Beispieldaten initialisiert
   - Warten Sie 1-2 Minuten bis alle Container gestartet sind

4. **Zugriff auf die Anwendung**
   - Web-Anwendung: http://localhost:8080
   - phpMyAdmin: http://localhost:8081
     - Server: database
     - Benutzer: root
     - Passwort: secure_root_password_123

### Demo-Zugänge

| Benutzer | Rolle | Passwort | Berechtigungen |
|----------|-------|----------|----------------|
| admin | Admin | admin123 | Vollzugriff auf alle Funktionen |
| lehrer1 | Lehrer | admin123 | Geräteverwaltung, Inspektionen, Berichte |
| hausmeister | Hausmeister | admin123 | Geräteaktualisierungen, Inspektionen |

## Funktionen im Detail

### Großgeräte-Verwaltung

- **Erfassung**: Inventarnummer, Gerätename, Gerätetyp, Halle/Garage
- **Inspektionen**: Automatische Berechnung nächster Prüftermine
- **Status-Verwaltung**: Aktiv, In Reparatur, Ausgemustert
- **Suche & Filter**: Nach Halle, Status, Gerätetyp
- **Inspektionshistorie**: Komplette Historie aller Prüfungen

### Kleingeräte-Verwaltung

- **Mengenverwaltung**: Aktuelle Bestände, Mindestbestände
- **Lagerplatz-Verwaltung**: Visuelle Übersicht aller Lagerorte
- **Schnellaktualisierung**: +1/-1 Buttons für Mengenänderungen
- **Bestandskontrolle**: Automatische Warnung bei niedrigen Beständen
- **Kategorien**: Bälle, Schläger, Sonstiges mit Unterteilungen

### Inspektions-Management

- **Prüfprotokolle**: Datum, Prüfer, Ergebnis, Notizen
- **Bewertungen**: Bestanden, Nicht bestanden, Mit Auflagen
- **Automatische Termine**: Berechnung nächster Inspektion (Standard 1 Jahr)
- **Überwachung**: Dashboard zeigt fällige und überfällige Inspektionen
- **Checklisten**: Vordefinierte Prüfpunkte und Kriterien

### Berichte & Abfragen

- **Standardabfragen**: Beantwortung typischer Fragen
  - "Wie viele Badmintonschläger verfügbar?"
  - "Wann letzte technische Prüfung?"
  - "Welche Geräte benötigen Inspektion?"
- **Export-Funktionen**: CSV, PDF, Excel Formate
- **Statistiken**: Inspektionsraten, Geräteverteilung, Bestände
- **Druckbare Listen**: Inventurlisten, Inspektionspläne

## Lagerkonzept

### Großgeräte (Garagen/Hallen)
- **Halle 1**: Geräte für Hauptsporthalle
- **Halle 2**: Zusätzliche Geräte und Reserven
- **Halle 3**: Geräte für Gertrud-Bäumer-Berufskolleg

### Kleingeräte (Spind-System)
- **Halle 1**: Spind 1-4 für verschiedene Gerätearten
- **Halle 2**: Spind 1-4 für zusätzliche Lagerung
- **Regieraum**: Verwaltung und spezielle Geräte
- **Keller**: Metallspind für schwere/seltene Geräte

### Gerätetypen

**Bälle**: Basketball, Volleyball, Fußball, Handball, Softball, Gymnastikball, Football
**Schläger**: Tischtennis, Unihockey, Badminton, Racketball
**Sonstiges**: Kugelstoß-Kugeln, Frisbees, Matten, Netze, Springseile, Markierungsbänder

## Sicherheit

### Implementierte Sicherheitsmaßnahmen

- **Passwort-Hashing**: bcrypt mit salt
- **Session-Management**: Sichere Cookies, Timeout nach 2 Stunden
- **CSRF-Schutz**: Token für alle Formulare
- **SQL-Injection Schutz**: Prepared Statements
- **Eingabe-Validierung**: Serverseitige Prüfung aller Daten
- **Rollenbasierte Rechte**: Verschiedene Zugriffsebenen

### Admin-Zugang

- **Datenbankzugriff**: phpMyAdmin auf localhost:8081
- **Logs**: Aktivitätsprotokollierung in Datenbank
- **Backup**: Docker-Volumes für Datensicherheit

## Wartung

### Regelmäßige Aufgaben

1. **Inspektionen**: Jährliche Prüfung aller Großgeräte
2. **Bestandskontrolle**: Monatliche Überprüfung der Kleingeräte
3. **Daten-Backup**: Sicherung der Docker-Volumes
4. **System-Updates**: Container und PHP-Module aktuell halten

### Fehlerbehebung

**Container starten nicht:**
```bash
docker-compose logs  # Logs anzeigen
docker-compose down  # Container stoppen
docker-compose up -d # Neu starten
```

**Datenbank-Verbindungsprobleme:**
- MySQL-Container vollständig starten lassen (ca. 1 Minute)
- Ports überprüfen (8080 für Web, 8081 für phpMyAdmin)
- Firewall-Einstellungen prüfen

## Lizenz

Dieses Projekt wurde entwickelt für das Berufskolleg für Technik im Rahmen der praktischen Ausbildung.

**Entwickelt von:** Praktikantenteam Berufskolleg für Technik
**Version:** 1.0.0
**Stand:** November 2025 
