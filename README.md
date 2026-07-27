# local_coursepilot

**Coursepilot** – Moodle-Plugin für KI-gestützten Kursaufbau via Webservice / MCP.

Dies ist das Moodle-Plugin (Komponente `local_coursepilot`, Moodle **5.0 oder neuer**).
Es ist nur zusammen mit dem lokal laufenden Coursepilot-MCP sinnvoll nutzbar. Das primäre
Entwicklungs-, Support- und Issue-Repository (MCP, Installer, Skills, Tests) ist
[matthiasgruenwald/moodle-coursepilot](https://github.com/matthiasgruenwald/moodle-coursepilot); dieser
Plugin-Quellbaum wird als schreibgeschützter Mirror
([matthiasgruenwald/moodle-local_coursepilot](https://github.com/matthiasgruenwald/moodle-local_coursepilot))
für das Moodle Plugin Directory veröffentlicht. Entwicklung, Issues und Support finden
ausschließlich im primären Repository statt.

## Lizenz

Das Moodle-Plugin `local_coursepilot` steht unter **GPL-3.0-or-later** (siehe
[LICENSE](LICENSE)). Der begleitende Coursepilot-MCP-Server und der Installer im
primären Repository stehen unter AGPL-3.0-or-later; für das Plugin gilt die GPL.

## Bereitgestellte Webservice-Funktionen

| Funktion | Beschreibung |
|---|---|
| `local_coursepilot_create_page` | Erstellt eine Textseite (mod_page) in einem Kursabschnitt |
| `local_coursepilot_create_assign` | Erstellt eine Aufgabe (mod_assign) in einem Kursabschnitt |
| `local_coursepilot_update_section` | Setzt Name und Zusammenfassung eines Abschnitts |
| `local_coursepilot_get_sections` | Gibt alle Abschnitte eines Kurses zurück |

---

## Installation

Voraussetzung: Moodle **5.0 oder neuer**.

1. **ZIP entpacken** in `[moodle-root]/local/coursepilot/`
2. Moodle-Admin-Bereich öffnen → **Upgrade durchführen**
3. Fertig – das Plugin ist installiert

> **Wichtig – Neuinstallation bei alter Komponente:** Coursepilot nutzt jetzt die
> Komponente `local_coursepilot`. Falls noch die alte Komponente `local_aicoursecreator`
> installiert ist, **deinstallieren** Sie diese zuerst (Website-Administration → Plugins →
> Plugins verwalten) und installieren Sie danach `local_coursepilot` neu. Eine Migration
> von Daten, Einstellungen oder Webservices aus `local_aicoursecreator` gibt es bewusst
> nicht.

---

## Datenschutz und KI-Client

Das Plugin ruft **selbst keinen KI-Anbieter** auf. Coursepilot nutzt einen **lokal** auf dem
Rechner der Lehrkraft konfigurierten KI-Client; erst wenn die Lehrkraft Kursinhalte an diesen
Client übergibt, können sie an dessen Anbieter übertragen werden.

Coursepilot ist ausschließlich für die Kursgestaltung durch die Lehrkraft gedacht und gibt
**keine Lernendendaten** frei – insbesondere keine Aufgabenabgaben, Forenbeiträge,
Quizversuche, Bewertungen oder Teilnehmendenlisten. Die Moodle-Privacy-API beschreibt dieses
Verhalten über einen `null_provider` (keine gespeicherten personenbezogenen Daten).

---

## Sprachen

Englisch (`lang/en/local_coursepilot.php`) ist die Basissprache. Deutsch wird in der
Übergangsphase **vorübergehend** mitgeliefert (`lang/de/local_coursepilot.php`), bis die
Übersetzung über **AMOS** gepflegt wird; danach wird die mitgelieferte deutsche Datei in
einem frühen Release entfernt.

---

## Konfiguration (Webservice + Token)

### 1. Web Services aktivieren
`Website-Administration → Erweiterte Funktionen → Webservices aktivieren` ✅

### 2. REST-Protokoll aktivieren
`Website-Administration → Plugins → Webservices → Protokolle verwalten → REST` ✅

### 3. Token erstellen
`Nutzerfeld oben → Einstellungen → Sicherheitsschlüssel`
- **Nutzer**: Lehrkraft mit globaler **Kurspilot-Nutzungsrolle** fuer Token/REST
- **Kursrechte**: Lesen und Schreiben laufen weiterhin ueber die Trainerrechte im jeweiligen Kurs; die Kurspilot-Nutzungsrolle verleiht selbst keine Kursbearbeitung
- **Dienst**: `Coursepilot`
- Token kopieren und sicher aufbewahren

### 4. Mit MCP verbinden (webservice_mcp Plugin)
MCP-Endpoint:
```
https://DEINE-MOODLE-URL/webservice/mcp/server.php?wstoken=DEIN_TOKEN
```

---

## Beispiel-API-Aufruf (REST)

### Textseite erstellen
```
POST https://moodle.example.com/webservice/rest/server.php
wstoken=abc123
wsfunction=local_coursepilot_create_page
moodlewsrestformat=json
courseid=5
sectionnum=1
name=Einführung in das Thema
content=<p>Willkommen in diesem Abschnitt...</p>
```

### Aufgabe erstellen
```
POST https://moodle.example.com/webservice/rest/server.php
wstoken=abc123
wsfunction=local_coursepilot_create_assign
moodlewsrestformat=json
courseid=5
sectionnum=1
name=Aufgabe 1: Recherche
description=<p>Recherchiere folgende Themen...</p>
duedate=1735689600
maxfiles=3
```

### Abschnitt benennen
```
POST .../server.php
wsfunction=local_coursepilot_update_section
courseid=5
sectionnum=1
name=Lerneinheit 1: Grundlagen
summary=<p>In diesem Abschnitt lernst du...</p>
```

---

## Parameter-Referenz

### create_page
| Parameter | Typ | Pflicht | Beschreibung |
|---|---|---|---|
| courseid | int | ✅ | Kurs-ID |
| sectionnum | int | ✅ | Abschnittsnummer (0-basiert) |
| name | string | ✅ | Titel der Textseite |
| content | string | ✅ | HTML-Inhalt |
| visible | int | – | 1=sichtbar (Standard), 0=versteckt |

### create_assign
| Parameter | Typ | Pflicht | Beschreibung |
|---|---|---|---|
| courseid | int | ✅ | Kurs-ID |
| sectionnum | int | ✅ | Abschnittsnummer (0-basiert) |
| name | string | ✅ | Titel der Aufgabe |
| description | string | – | HTML-Beschreibung |
| duedate | int | – | Abgabedatum als Unix-Timestamp (0 = kein Datum) |
| allowsubmissionsfromdate | int | – | Freischaltdatum (0 = sofort) |
| maxfiles | int | – | Max. Dateiuploads (Standard: 1, 0 = kein Upload) |
| submissiondrafts | int | – | 1 = Schüler müssen Submit klicken |
| visible | int | – | 1=sichtbar (Standard) |

### update_section
| Parameter | Typ | Pflicht | Beschreibung |
|---|---|---|---|
| courseid | int | ✅ | Kurs-ID |
| sectionnum | int | ✅ | Abschnittsnummer |
| name | string | – | Abschnittsname |
| summary | string | – | HTML-Zusammenfassung |
| visible | int | – | 1=sichtbar (Standard) |

---

## Kompatibilität
- Moodle 5.0 oder neuer
- PHP 8.1 oder neuer (Moodle-5.0-Unterstützung)
