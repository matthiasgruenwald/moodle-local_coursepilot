<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_coursepilot';
$plugin->version   = 2026072905;  // Format: YYYYMMDDNN – NN bei mehreren Releases pro Tag hochzählen
$plugin->requires  = 2025041400;  // Moodle 5.0+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.41';

// Changelog:
// 1.0.41 (2026072905) – Bugfix (#226): move_section Zielabschnitt auto-create.
//   - move_section.php: Vor dem Verschieben wird course_create_sections_if_missing()
//     aufgerufen, damit ein nicht-existierender Zielabschnitt automatisch angelegt
//     wird statt mit invalidrecord zu scheitern.
// 1.0.40 (2026072904) – Neu (#224): Aktivitaets-MCP Forum (mod_forum).
//   - create_forum.php: legt ein Forum (mod_forum) mit waehlbarem Forumtyp
//     (general, qanda, eachuser, single) in einem Kursabschnitt an. Nutzt
//     add_moduleinfo() (delegiert an forum_add_instance). Moodle-5.0-Schema:
//     forum_grade_item_update() liest cmidnumber, assessed, scale und
//     grade_forum unconditional, daher werden cmidnumber='' sowie alle
//     bewertungs-/kalenderrelevanten Pflichtfelder explizit gesetzt (analog
//     zum cmidnumber-Fix #2/#6). Rueckgabe enthaelt cmid, name und type.
//   - update_forum.php: aendert Name, Beschreibung und/oder Forumtyp eines
//     bestehenden Forums. Der volle Forum-Datensatz wird geladen und nur die
//     uebergebenen Felder werden ueberschrieben, damit forum_update_instance()
//     jedes gelesene Feld (assessed, scale, grade_forum, forcesubscribe, ...)
//     vorfindet. cmidnumber und coursemodule kommen aus course_modules, da
//     cmidnumber keine Spalte der forum-Tabelle ist.
//   - db/services.php: local_coursepilot_create_forum und
//     local_coursepilot_update_forum registriert und dem Coursepilot-Dienst
//     hinzugefuegt.
// 1.0.39 (2026072903) – Neu (#223): Aktivitaets-MCP Abstimmung (mod_choice).
//   - create_choice.php: legt eine Abstimmung (mod_choice) mit 2-6 Optionen
//     in einem Kursabschnitt an. Optionen werden als String-Array uebergeben
//     und als $moduleinfo->option plus $moduleinfo->limit (0 = unbegrenzt)
//     gesetzt; add_moduleinfo() delegiert an choice_add_instance(), das die
//     Optionen als eigene Zeilen in choice_options anlegt. Moodle-5.0-Schema:
//     mod_choice nutzt display (nicht optioncolumns) und allowmultiple; alle
//     Pflichtfelder der choice-Tabelle werden explizit gesetzt. Rueckgabe
//     enthaelt cmid, name und die gespeicherten Optionen.
//   - update_choice.php: aendert Name, Beschreibung und/oder Optionen einer
//     bestehenden Abstimmung. Die Optionspflege nutzt choice_update_instance()
//     (denselben Pfad wie das Moodle-Editformular): vorhandene Optionen werden
//     nach Position gematcht (IDs bleiben erhalten), neue Optionen angehaengt,
//     leer gelassene entfernt. Ohne Optionen-Parameter bleiben die Optionen
//     unveraendert.
//   - db/services.php: local_coursepilot_create_choice und
//     local_coursepilot_update_choice registriert und dem Coursepilot-Dienst
//     hinzugefuegt.
// 1.0.38 (2026072902) – Neu (#222): Aktivitaets-MCP Verzeichnis (mod_folder).
//   - create_folder.php: legt ein Verzeichnis (mod_folder) in einem Kurs-
//     abschnitt an (display=0 separate Seite, showexpanded=1,
//     showdownloadfolder=1). Nutzt add_moduleinfo() (delegiert an
//     folder_add_instance); Moodle 5.0: folder_add_instance() liest
//     $moduleinfo->files unconditional, daher wird files=0 gesetzt.
//     Rueckgabe enthaelt cmid.
//   - update_folder.php: aendert den Namen und/oder die Sichtbarkeit eines
//     bestehenden Verzeichnisses (folder.name + course_modules.visible,
//     timemodified wird aktualisiert).
//   - upload_folder_file.php: laedt eine Base64-Datei in den Content-
//     Dateibereich eines Verzeichnisses (component mod_folder, filearea
//     content, itemid 0). Unterverzeichnisse werden ueber filepath angelegt
//     (file_storage::create_directory inkl. Eltern), eine vorhandene Datei
//     gleichen Namens im selben Verzeichnis wird ersetzt, folder.revision
//     wird erhoeht. Antwort enthaelt fileid, filename, filepath und die
//     Liste aller Dateien (voller Pfad).
//   - db/services.php: local_coursepilot_create_folder,
//     local_coursepilot_update_folder und local_coursepilot_upload_folder_file
//     registriert und dem Coursepilot-Dienst hinzugefuegt.
// 1.0.37 (2026072901) – Neu (#221): Aktivitaets-MCP Datei (mod_resource).
//   - create_resource.php: legt eine Datei-Ressource (mod_resource) in einem
//     Kursabschnitt an und speichert eine Base64-Datei als Hauptdatei im
//     Content-Dateibereich (component mod_resource, filearea content, itemid 0,
//     sortorder 1). Nutzt add_moduleinfo() (delegiert an resource_add_instance)
//     und die file_storage-API; Rueckgabe enthaelt cmid, fileid, filename.
//     Moodle 5.0: resource_add_instance() liest $moduleinfo->files
//     unconditional, daher wird files=0 gesetzt (Hauptdatei entsteht erst
//     danach direkt im content-Filearea, analog zum cmidnumber-Fix #2/#6).
//   - update_resource.php: aendert den Namen und/oder tauscht die Hauptdatei
//     aus (alte Content-Dateien loeschen, neue anlegen, revision erhoehen).
//   - db/services.php: local_coursepilot_create_resource +
//     local_coursepilot_update_resource registriert und dem Coursepilot-Dienst
//     hinzugefuegt.
// 1.0.36 (2026072900) – Bugfix (#218): Moodle-5.0-Schema fuer upload_assignfile.
//   - upload_assignfile.php: Die assign-Tabelle hatte nie Spalten
//     introattachments/introattachmentsonsubmission; der defensive Lese-/
//     Schreibblock darauf scheiterte in Moodle 5.0 immer. Intro-Anhaenge
//     werden ausschliesslich ueber die Dateiablage verwaltet (mod_assign/
//     introattachment, itemid 0) und automatisch gerendert. Block entfernt,
//     $DB wird nicht mehr genutzt.
//   - Antwort enthaelt neu 'files': Dateinamen im Intro-Anhang-Bereich nach
//     dem Upload (End-to-End-Verifikation in test/integration/
//     upload-assignfile.integration.test.js).
// 1.0.35 (2026072702) – Neu (#189, Parent #146): Produktoberflaeche, Sprachen
//   und Neuinstallation erklaert.
//   - lang/de/local_coursepilot.php: vollstaendige, voruebergehende deutsche
//     Uebersetzung aller englischen Lang-Schluessel. Englisch bleibt die
//     Basissprache; Deutsch wird mitgeliefert, bis AMOS die Pflege uebernimmt
//     (danach Entfernung in einem fruehen Release). Die Datei ist im Kopf
//     entsprechend markiert.
//   - README.md (Plugin-Mirror): einheitlicher Produktname Coursepilot,
//     Moodle-5.0+-Hinweis, Neuinstallation ohne Migration (local_aicoursecreator
//     zuerst deinstallieren), Datenschutz/KI-Client-Grenze (lokal konfigurierter
//     KI-Client, keine Lernendendaten), Sprachen/AMOS-Abschnitt und Verweis auf
//     das primaere Repository matthiasgruenwald/Kurspilot.
//   - Die Hinweise (Neuinstallation, KI-Client/Lernendendaten) stehen ausserdem
//     im primaeren README, in RELEASE_NOTES.md und sichtbar im Konfigurator
//     (lib/setup-render.js#renderCoursepilotNotices) und werden per
//     Vertragstest geschuetzt (test/coursepilot-notices-contract.test.js,
//     test/setup-render.test.js im primaeren Repository).
// 1.0.34 (2026072701) – Neu (#188, Parent #146): Komponenten-Umstellung auf
//   local_coursepilot.
//   - Die Moodle-Komponente heisst durchgaengig local_coursepilot: Verzeichnis,
//     PHP-Namensraum, Webservice-Funktionen, Dienst-Shortname (coursepilot),
//     Capability (local/coursepilot:use), Lang-Datei und Build-Artefakt
//     (Plugin/local_coursepilot.zip) tragen dieselbe Identitaet.
//   - plugin->requires auf Moodle 5.0 (2025041400) angehoben: Coursepilot
//     richtet sich an frische Moodle-5.0+-Installationen.
//   - Bewusst KEINE Kompatibilitaets- oder Datenmigration von der alten
//     Komponente local_aicoursecreator: Bestehende Installationen deinstallieren
//     das alte Plugin und installieren local_coursepilot neu (siehe
//     docs/specs/0003-coursepilot-marketplace-readiness.md).
//   - End-to-End-Identitaet (MCP-Aufrufe -> Webservice-Funktionen) und die
//     Moodle-5.0-Mindestversion werden per Vertragstest erzwungen
//     (test/plugin-identity-contract.test.js im primaeren Repository).
// 1.0.33 (2026072700) – Neu (#187): Lernendendaten-Schutzgrenze.
//   - classes/privacy/provider.php: Privacy-API als null_provider umgesetzt.
//     Das Plugin ist eine zustandslose Webservice-Schicht fuer die
//     lehrkraftbezogene Kursgestaltung, besitzt keine eigenen Tabellen und
//     speichert keine personenbezogenen Daten; der null_provider behauptet
//     bewusst keine Verarbeitung von Lernendendaten.
//   - lang/en: privacy:metadata praezisiert die Schutzgrenze (keine Abgaben,
//     Forenbeitraege, Quizversuche, Bewertungen oder Teilnehmendenlisten).
//   - Die erlaubte MCP-/Webservice-Oberflaeche wird als positive Allowlist
//     gepflegt und per Vertragstest erzwungen (lib/data-protection-allowlist.js
//     + test/data-protection-contract.test.js im primaeren Repository).
// 1.0.32 (2026063000) – Bugfix:
//   - lang/en: fehlenden Lang-String coursepilot:use ergaenzt. Die in
//     db/access.php definierte Capability hatte keinen zugehoerigen String,
//     was in der Moodle-Rechteverwaltung (Websiteadministration -> Kursbereich
//     -> Rechte) zu einem Fehler fuehrte.
// 1.0.31 (2026062401) – Neu (#86) + Bugfix (#5):
//   - create_quiz/update_quiz_settings: alle einzeln ueberschreibbaren
//     Quiz-Formularfelder (Frageverhalten, Layout, Navigation, Versuche,
//     Wartezeiten, Bewertungsmethode, Review-Optionen, Gesamtfeedback,
//     Abschlussbedingungen) ueber Sentinel-Defaults ('' / -1 = Modus-
//     Default verwenden). Neue zentrale apply_field_overrides() +
//     overridable_field_params() vermeiden Duplikation.
//   - db/services.php: Webservice-Name "AI Course Creator Service" ->
//     "Coursepilot".
// 1.0.30 (2026062004) – Neu (#76):
//   - local_coursepilot_move_module + moodle_move_module: verschiebt eine
//     bestehende Aktivitaet per cmid vor/nach eine andere Aktivitaet oder ans
//     Abschnittsende, ohne Inhalte, Sichtbarkeit, Abschlussbedingungen,
//     Voraussetzungen, Quizsettings oder Fragen zu aendern.
// 1.0.29 (2026062003) – Bugfix:
//   - Quiz-Reviewoption "Max. Punkte" nutzt in Moodle 5.x das eigene
//     DB-/Formularfeld reviewmaxmarks/maxmarks*, getrennt von
//     reviewmarks/marks*. Kurspilot setzt und meldet dieses Feld jetzt
//     separat, damit die UI-Checkboxen nicht faelschlich als aktiv
//     zurueckgemeldet werden.
// 1.0.28 (2026062002) – Bugfix/Erweiterung:
//   - lernstandscheck setzt Abschluss ueber Note plus Bestehensgrenze
//     explizit, blendet richtige Antworten in Review-Optionen aus, zeigt
//     Bewertung/Gesamtfeedback nach Versuch an und nutzt drei
//     Gesamtfeedback-Baender (ab 80 %, 50 bis unter 80 %, unter 50 %).
//   - update_quiz_settings gibt nach dem Speichern die tatsaechlich
//     gesetzten Quiz-, Completion-, Review- und Feedback-Werte zurueck,
//     damit MCP-Tests nicht nur success pruefen.
// 1.0.27 (2026062001) – Neu (#82):
//   - create_quiz akzeptiert die neuen Kurspilot-Quizmodi mini-check,
//     lernstandscheck und abschlusstest nativ. Alte Werte werden nur noch als
//     deprecated Aliases intern gemappt.
//   - Neu local_coursepilot_update_quiz_settings +
//     moodle_update_quiz_settings: setzt bestehende Quizaktivitaeten
//     nachtraeglich auf dieselben Settings-Kombinationen inklusive
//     Frageverhalten, Layout, Navigation, Versuchen, Wartezeit,
//     Bewertungsmethode, Review-Optionen, Gesamtfeedback,
//     Bestehensgrenze und Abschlussbedingungen.
// 1.0.26 (2026062000) – Bugfix (#80):
//   - Kurspilot-Webservices werden zusaetzlich ueber die Kurs-Capability
//     local/coursepilot:use gegated statt ueber pluginverwaltete globale
//     Rollen-/Webservice-Rechte. Bestehende Moodle-Fachrechte pro Funktion
//     bleiben unveraendert.
// 1.0.25 (2026061903) – Neu (#78):
//   - local_coursepilot_update_question_category + moodle_update_question_category:
//     verschiebt oder benennt Fragenkategorien nicht-destruktiv innerhalb der
//     ausgewaehlten Kurs-Fragensammlung um. Kein Delete-Tool in V1.
// 1.0.24 (2026061902) – Neu (#77):
//   - local_coursepilot_ensure_question_bank + moodle_ensure_question_bank:
//     legt eine benannte Kurs-/Projekt-Fragensammlung als standard-mod_qbank
//     an oder waehlt eine gleichnamige bestehende aus (idempotent).
//   - create_question_category + get_question_categories schreiben/lesen
//     Kategorien nicht mehr ueber die systemweite Altlast-Fragensammlung,
//     sondern explizit in der ausgewaehlten benannten Fragensammlung
//     (`questionbankid`/CMID erforderlich).
// 1.0.23 (2026061901) – Neu (#76):
//   - local_coursepilot_move_section + moodle_move_section: verschiebt
//     einen bestehenden Kursabschnitt an eine neue Position, ohne Name,
//     Beschreibung, Aktivitaeten oder Sichtbarkeit zu aendern. Nutzt
//     Moodle-Core move_section_to() und bleibt auf moodle/course:update
//     begrenzt.
// 1.0.22 (2026061900) – Bugfix (#73):
//   - AI Course Creator Service ist nicht mehr auf eine manuell gepflegte
//     authorised-users-Liste angewiesen (restrictedusers=0).
//   - Lesefunktionen in db/services.php tragen keine zusaetzlichen
//     Capability-Metadaten mehr; Lesen/Schreiben im Zielkurs bleiben an die
//     require_capability()-Pruefungen der einzelnen externen Funktionen
//     gekoppelt.
// 1.0.21 (2026061801) – Bugfix:
//   - get_course_catalog nutzt wie get_sections/get_modules nur
//     validate_context(); eingeschriebene Nutzer brauchen nicht die
//     Capability moodle/course:view ("Kurse ohne Beteiligung anzeigen").
// 1.0.20 (2026061800) – Wartungsrelease:
//   - Versions-Bump, damit Moodle beim Plugin-Update die Webservice-
//     Registrierung inklusive local_coursepilot_get_course_catalog
//     sicher aktualisiert.
// 1.0.19 (2026061401) – Neu/Bugfix:
//   - Neu upload_assign_intro_image: Bilder in den mod_assign intro-
//     Dateibereich hochladen und per @@PLUGINFILE@@ direkt in die
//     Aufgabenbeschreibung einbetten.
//   - Bugfix upload_assignfile: fehlendes introattachments-Feld defensiv
//     behandeln.
// 1.0.18 (2026061203) – Neu: fehlende Kursabschnitte idempotent anlegen.
//   - local_coursepilot_ensure_section + moodle_ensure_section:
//     nutzt Moodle-Core course_create_sections_if_missing(), damit Abschnitte
//     vor update/create-Aufrufen existieren und invalidrecord vermieden wird.
// 1.0.17 (2026061202) – Bugfix: Moodle 5.0 Kompatibilitaet fuer Aufgaben.
//   - create_assign (#2): $moduleinfo->cmidnumber ergaenzt –
//     edit_module_post_actions() (course/modlib.php, von add_moduleinfo()
//     aufgerufen) liest dieses Feld beim grade_item-Sync unconditional,
//     fehlte es warf Moodle 5.0 "Undefined property: stdClass::$cmidnumber".
// 1.0.16 (2026061201) – Neu (#13, ADR-0001):
//   - local_coursepilot_add_questions_to_quiz – fuegt Fragenbank-Fragen
//     (#9) zu einem Quiz (#6) als question_references mit version=null
//     ("immer aktuellste Version") hinzu. Reihenfolge folgt questionids[],
//     Duplikate (gleiche questionbankentryid) werden uebersprungen. Wrapper
//     um Moodle-Core quiz_add_quiz_question() + quiz_update_sumgrades() +
//     rebuild_course_cache(). Antwort enthaelt 'slots': aktueller Quiz-
//     Inhalt in Slot-Reihenfolge mit jeweils aktuellster questionid/version
//     je Frage.
// 1.0.15 (2026061105) – Bugfix: letzte 6 Tests (Moodle 5.0 Schemaaenderungen).
//   - update_mc_question (#9): $oldquestion->category existiert in Moodle 5.0
//     nicht mehr (question.category-Spalte entfernt; Kategorie liegt jetzt
//     auf question_bank_entries.questioncategoryid). Neue question-Zeile
//     verwendet jetzt $entry->questioncategoryid.
//   - set_restriction quiz_passed (#10): mod_quiz hat seit Moodle 5.0 keine
//     gradepass-Spalte mehr (nur noch grade_items.gradepass). SELECT auf
//     quiz.gradepass entfernt, Bestehensgrenze kommt ausschliesslich aus
//     grade_items.
//   - db/services.php: Core-Funktion mod_quiz_get_quizzes_by_courses (lesend)
//     zum Service hinzugefuegt – wird von quiz-modes.integration.test.js
//     genutzt, um gespeicherte Quiz-Settings nach create_quiz zu verifizieren.
// 1.0.14 (2026061104) – Bugfix: weitere Moodle 5.0 Kompatibilitaet.
//   - create_quiz (#6/#11): $moduleinfo->cmidnumber ergaenzt –
//     edit_module_post_actions() (course/modlib.php, von add_moduleinfo()
//     aufgerufen) liest dieses Feld beim grade_item-Sync unconditional,
//     fehlte es warf Moodle 5.0 "Undefined property: stdClass::$cmidnumber".
//   - get_question (#9): Capability moodle/question:view existiert in
//     aktuellem Moodle nicht mehr (nur viewmine/viewall) -> "nopermissions
//     ([[question:view]])". Auf moodle/question:viewall umgestellt
//     (classes/external/get_question.php + db/services.php).
// 1.0.13 (2026061103) – Bugfix: Moodle 5.0 Kompatibilitaet (mod_qbank).
//   - create_question_category + get_question_categories (#7/#9): Fragen-
//     kategorien liegen seit Moodle 5.0 im Kontext einer "Question bank"-
//     Aktivitaet (mod_qbank), nicht mehr direkt im Kurskontext.
//     question_get_top_category() liefert fuer CONTEXT_COURSE jetzt false
//     (statt die top-Kategorie anzulegen). Fix: Kontext ueber
//     question_bank_helper::get_default_open_instance_system_type() aufloesen
//     (legt bei Bedarf die system-mod_qbank-Instanz des Kurses an).
//   - Tabellenname-Tippfehler durchgaengig korrigiert: question_category ->
//     question_categories (create_question_category, get_question_categories,
//     create_mc_question, get_question, update_mc_question).
//   - create_quiz (#6/#11): $moduleinfo->quizpassword ergaenzt –
//     quiz_process_options() liest dieses Feld unconditional, fehlte es warf
//     Moodle 5.0 "Undefined property: stdClass::$quizpassword".
//   - create_quiz (#11): review*-Bitmasken aus mode_defaults() wurden von
//     quiz_process_options() verworfen, weil die Funktion sie aus einzelnen
//     "<feld><zeitpunkt>"-Formularfeldern neu berechnet. Neue Methode
//     apply_review_options() setzt diese Felder passend zu den Kombi-Masken.
// 1.0.12 (2026061102) – Welle 3:
//   - Neu (#9, ADR-0001): MC-Fragen erstellen und bearbeiten mit nativer
//     Versionierung.
//     - local_coursepilot_create_mc_question – legt eine MC-Frage
//       (qtype_multichoice) in einer Fragenbank-Kategorie an. V1-Regeln:
//       genau eine richtige Antwort (fraction=1.0), variable Optionen,
//       single=1, shuffleanswers=1, kein Teilpunkte-Modell. Erzeugt
//       question + question_bank_entries + question_versions (version=1)
//       + question_answers + qtype_multichoice_options.
//     - local_coursepilot_update_mc_question – speichert Edits als NEUE
//       Moodle-Version derselben Frage (gleiche questionbankentryid, neue
//       question.id, neue question_versions-Zeile mit max+1). Alte Version
//       bleibt fuer bestehende Quiz-Attempts gueltig (ADR-0001).
//     - local_coursepilot_get_question – liefert die latest version
//       einer Frage in einer Kategorie, identifiziert per Name oder
//       questionid (zur eindeutigen Identifikation vor einem Edit).
//   - Erweiterung (#10): Quiz-Bestehensabschluss und Sperre fuer Folgeaktivitaet.
//     - set_completion: neuer Parameter completionpassgrade (0/1). Bei
//       completion=2 + completionpassgrade=1 wird course_modules.
//       completionpassgrade gesetzt – Aktivitaet gilt erst als abgeschlossen,
//       wenn die Bestehensgrenze (gradepass) erreicht ist. Aktuell genutzt
//       fuer mod_quiz.
//     - set_restriction: neue Parameter condition_type ('quiz_passed') und
//       condition_target_cmid. Im Modus 'quiz_passed' wird eine availability-
//       Notenbedingung gebaut: {type:"grade", id:<grade_item_id>,
//       min:<gradepass>}. Die grade_item-ID wird aus grade_items (itemmodule=
//       quiz, iteminstance=quiz.id) ermittelt. Standardpfad (Completion-
//       Bedingungen) bleibt unveraendert; require_cmids ist jetzt optional
//       (Default []), damit der Aufruf ohne require_cmids im quiz_passed-
//       Modus moeglich ist.
//     - Keine impliziten Defaults: ohne expliziten quiz_passed-Aufruf wird
//       keine Notenbedingung gesetzt.
//   - Erweiterung (#11): create_quiz um Parameter `mode` (lerncheck|intensiv|
//     bewertung). Drei Modi mit jeweils dokumentierter Settings-Kombination:
//       * lerncheck (Default): deferredfeedback, unbegrenzte Versuche,
//         beste Bewertung, gradepass ~80% – Lernstandscheck (#6-Verhalten).
//       * intensiv: immediatefeedback, unbegrenzte Versuche, Durchschnittsnote,
//         gradepass ~80% – Intensiv-Ueben mit sofortiger Rueckmeldung.
//       * bewertung: deferredfeedback, genau 1 Versuch, beste Bewertung,
//         Review erst nach Schliessung, gradepass ~50%, Zeitlimit optional.
//     Layered Defaults: explizite gradepass/timelimit-Parameter ueberschreiben
//     den Modus-Default. Rueckgabewert enthaelt jetzt `mode`.
// 1.0.11 (2026061101) – Neu:
//   - local_coursepilot_create_quiz (#6) – legt ein Quiz (mod_quiz) mit
//     Lerncheck-Defaults an (unbegrenzte Versuche, beste Bewertung zaehlt,
//     kein Zeitlimit, gemischte Antworten). gradepass parametrisierbar
//     (Prozent von grade=100), ~80% empfohlen.
//   - create_question_category + get_question_categories (#7) – Fragenbank-
//     Kategorien je Unterthema/Inhaltsabschnitt. Kategorien werden im
//     Kurs-Fragenkontext unter der "top"-Kategorie angelegt;
//     create_question_category ist idempotent (gleicher Name+Parent ->
//     bestehende id, created=false statt Dublette).
// 1.0.10 (2026061100) – Bugfix: get_sections/get_modules verlangten faelschlich
//   moodle/course:view (Capability fuer Kurse OHNE Beteiligung). Eingeschriebene
//   Trainer/Studierende hatten diese nicht und bekamen "nopermissions".
//   validate_context() reicht fuer eingeschriebene Nutzer aus.
// 1.0.2 (2025041902) – Bugfix: course_modules hat kein timemodified-Feld
// 1.0.1 (2025041901) – Bugfixes:
//   - modlib.php require_once ergänzt (add_moduleinfo war nicht gefunden)
//   - markingworkflow + markingallocation in create_assign ergänzt
//   - update_* Funktionen: !== '' statt if() + timemodified immer setzen
//   - create_url + create_label als neue Funktionen
// 1.0.0 (2025041800) – Erstveröffentlichung
