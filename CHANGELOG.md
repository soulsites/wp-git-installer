# Changelog

## Version 2.1 - 2024

### Neue Features

#### Multi-Projekt-Unterstützung
- **Projekt-Verwaltung**: Speichern und Verwalten mehrerer GitHub-Projekte an einem Ort
- **Karten-basierte UI**: Moderne, übersichtliche Darstellung aller gespeicherten Projekte als Karten
- **Ein-Klick-Synchronisation**: Schnelle Aktualisierung einzelner Projekte mit dem "Synchronisieren"-Button
- **Projekt-Bearbeitung**: Neuer Edit-Modal zum Bearbeiten gespeicherter Projekt-Konfigurationen
- **Zeitstempel**: Anzeige der letzten Synchronisationszeit für jedes Projekt

#### Design & Benutzeroberfläche
- **Material Design 3**: Komplette Überarbeitung mit modernem Material Design 3
- **Dark Design**: Elegantes dunkles Design mit konsistenten Violett-Farbverläufen
- **Sanfte Animationen**: Flüssige Übergänge und Hover-Effekte für bessere UX
- **Responsive Layout**: Verbesserte Darstellung auf verschiedenen Bildschirmgrößen
- **Umfassende CSS-Verbesserungen**: Optimierte Styles für alle UI-Komponenten

#### Verbesserte Benutzerfreundlichkeit
- **Überarbeitete Settings-Seite**: Komplett neugestaltete Einstellungsseite mit verbesserter UX
- **Tabellen-Format**: Übersichtliche Darstellung von Projekt-Informationen
- **Checkbox für Projekt-Speicherung**: Klare Option zum Speichern von Projekten

### Fehlerbehebungen

#### Git-Operationen
- **Multi-Projekt-Sync**: Behebung von Fehlern beim gleichzeitigen Synchronisieren mehrerer Projekte
- **Merge-Konflikte**: Automatisches Verwerfen lokaler Änderungen beim Sync zur Vermeidung von Konflikten
- **Private Repositories**: Fix für Git-Fetch-Fehler bei privaten Repositories
- **Empty Pathspec**: Behebung des "empty pathspec"-Fehlers beim Plugin-Update

#### Funktionalität
- **Projekt-Speicherung**: Korrektur der Projekt-Speicher-Funktionalität
- **Stabilitätsverbesserungen**: Allgemeine Verbesserungen der Zuverlässigkeit

### Technische Änderungen

- Über 1.400 Zeilen neuer und überarbeiteter Code
- 869 Zeilen neue CSS-Styles
- Verbesserte JavaScript-Funktionalität mit über 400 Zeilen Updates
- Optimierte PHP-Backend-Logik

### Upgrade-Hinweise

Diese Version ist ein umfangreiches Update mit vielen neuen Features und Verbesserungen. Alle gespeicherten Projekte bleiben beim Update erhalten.

---

## Frühere Versionen

### Version 2.0
- Initiales Release mit grundlegender GitHub-Plugin-Installation
- Unterstützung für öffentliche und private Repositories
- Versions-Auswahl (Tags)
- Repository-Vorschau
