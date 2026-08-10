<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursepilot\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/questionlib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

/**
 * Aktualisiert eine bestehende Multiple-Choice-Frage als NEUE Moodle-Version
 * derselben Frage (ADR-0001).
 *
 * Konkret:
 *   - Anhand der bestehenden questionid wird die zugehoerige
 *     questionbankentryid ueber question_versions ermittelt.
 *   - Eine NEUE question-Zeile mit den neuen Inhalten wird angelegt.
 *   - Eine NEUE question_versions-Zeile mit version = max(version)+1 wird
 *     an dieselbe questionbankentryid gehaengt.
 *   - Die alte question-/Versions-Zeile bleibt unangetastet, damit bestehende
 *     Quiz-Attempts gueltig bleiben.
 *   - Neue question_answers und qtype_multichoice_options-Zeilen werden zur
 *     neuen question.id erzeugt.
 *
 * Der strukturierte Antwortvertrag entspricht create_mc_question; der alte
 * options[] + correctindex-Pfad bleibt als Einfachauswahl erhalten.
 */
class update_mc_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid'      => new external_value(PARAM_INT,   'ID der bestehenden question-Zeile (latest version), z.B. aus get_question'),
            'name'            => new external_value(PARAM_TEXT,  'Name der Frage (i.d.R. unveraendert)'),
            'questiontext'    => new external_value(PARAM_RAW,   'Neuer Fragetext (HTML)'),
            'options'         => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Antwort-Option (HTML)'),
                'Alte Antwort-Optionen fuer correctindex-Kompatibilitaet', VALUE_DEFAULT, []
            ),
            'correctindex'    => new external_value(PARAM_INT,   '0-basierter Index der richtigen Antwort in options[]', VALUE_DEFAULT, -1),
            'selectionmode'   => new external_value(PARAM_ALPHA, 'single oder multiple', VALUE_DEFAULT, 'single'),
            'answers'         => new external_multiple_structure(new external_single_structure([
                'answer'   => new external_value(PARAM_RAW, 'Antworttext (HTML)'),
                'correct'  => new external_value(PARAM_BOOL, 'Als richtig markiert'),
                'feedback' => new external_value(PARAM_RAW, 'Antwortspezifisches Feedback (HTML)'),
                'fraction' => new external_value(PARAM_FLOAT, 'Gewicht zwischen -1 und 1'),
            ]), 'Strukturierte Antworten', VALUE_DEFAULT, []),
            'defaultmark'     => new external_value(PARAM_FLOAT, 'Standard-Punktzahl der Frage', VALUE_DEFAULT, 1.0),
            'generalfeedback' => new external_value(PARAM_RAW,   'Allgemeines Feedback (HTML, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int    $questionid,
        string $name,
        string $questiontext,
        array  $options = [],
        int    $correctindex = -1,
        string $selectionmode = 'single',
        array  $answers = [],
        float  $defaultmark = 1.0,
        string $generalfeedback = ''
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid'      => $questionid,
            'name'            => $name,
            'questiontext'    => $questiontext,
            'options'         => $options,
            'correctindex'    => $correctindex,
            'selectionmode'   => $selectionmode,
            'answers'         => $answers,
            'defaultmark'     => $defaultmark,
            'generalfeedback' => $generalfeedback,
        ]);

        $answers = self::normalise_answers($params);

        // Bestehende question + zugehoerige question_versions-Zeile finden.
        $oldquestion = $DB->get_record('question',
            ['id' => $params['questionid']], '*', MUST_EXIST);
        $oldversion = $DB->get_record('question_versions',
            ['questionid' => $params['questionid']], '*', MUST_EXIST);
        $entryid = (int) $oldversion->questionbankentryid;

        // Kontext aus aktueller Kategorie der entry. Capability pruefen.
        $entry = $DB->get_record('question_bank_entries',
            ['id' => $entryid], '*', MUST_EXIST);
        $category = $DB->get_record('question_categories',
            ['id' => $entry->questioncategoryid], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);
        self::validate_context($context);
        require_capability('local/coursepilot:use', $context);
        require_capability('moodle/question:add', $context);

        $now = time();

        // 1) NEUE question-Zeile (ADR-0001: alte bleibt unangetastet).
        // Die Kategorie liegt seit Moodle 5.0 nicht mehr auf question.category,
        // sondern auf question_bank_entries.questioncategoryid (= $entry).
        $newquestionid = self::insert_new_question_row(
            (int) $entry->questioncategoryid,
            $params['name'],
            $params['questiontext'],
            $params['generalfeedback'],
            $params['defaultmark'],
            $now,
            $USER->id
        );

        // 2) NEUE question_versions-Zeile mit version = max(version)+1 zur
        //    selben questionbankentryid.
        $maxversion = (int) $DB->get_field_sql(
            'SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = ?',
            [$entryid]
        );
        $newversionnum = $maxversion + 1;
        self::insert_version_row($entryid, $newversionnum, $newquestionid);

        // 3) Antworten + qtype_multichoice_options fuer die neue question.id.
        $answerids = self::insert_answers(
            $newquestionid, $answers);
        self::insert_multichoice_options($newquestionid, $params['selectionmode']);

        return [
            'questionid'          => $newquestionid,
            'questionbankentryid' => $entryid,
            'version'             => $newversionnum,
            'previousquestionid'  => (int) $oldquestion->id,
            'answerids'           => array_map('intval', $answerids),
            'warnings'            => self::warnings($answers, $params['selectionmode']),
            'message'             => 'MC-Frage "' . $params['name']
                . '" erfolgreich als neue Version (' . $newversionnum . ') gespeichert.',
        ];
    }

    private static function insert_new_question_row(
        int    $categoryid,
        string $name,
        string $questiontext,
        string $generalfeedback,
        float  $defaultmark,
        int    $now,
        int    $userid
    ): int {
        global $DB;
        $q = new \stdClass();
        $q->category              = $categoryid;
        $q->parent                = 0;
        $q->name                  = $name;
        $q->questiontext          = $questiontext;
        $q->questiontextformat    = FORMAT_HTML;
        $q->generalfeedback       = $generalfeedback;
        $q->generalfeedbackformat = FORMAT_HTML;
        $q->defaultmark           = $defaultmark;
        $q->penalty               = 0;
        $q->qtype                 = 'multichoice';
        $q->length                = 1;
        $q->stamp                 = make_unique_id_code();
        $q->timecreated           = $now;
        $q->timemodified          = $now;
        $q->createdby             = $userid;
        $q->modifiedby            = $userid;
        $q->idnumber              = null;
        return (int) $DB->insert_record('question', $q);
    }

    private static function insert_version_row(int $entryid, int $version, int $questionid): void {
        global $DB;
        $row = new \stdClass();
        $row->questionbankentryid = $entryid;
        $row->version             = $version;
        $row->questionid          = $questionid;
        $row->status              = 'ready';
        $DB->insert_record('question_versions', $row);
    }

    private static function normalise_answers(array $params): array {
        $answers = $params['answers'];
        if (empty($answers)) {
            foreach ($params['options'] as $index => $option) {
                $answers[] = ['answer' => $option, 'correct' => $index === $params['correctindex'],
                    'feedback' => '', 'fraction' => $index === $params['correctindex'] ? 1.0 : 0.0];
            }
        }
        if (count($answers) < 2) {
            throw new \invalid_parameter_exception('Eine Multiple-Choice-Frage braucht mindestens 2 Antworten.');
        }
        if (!in_array($params['selectionmode'], ['single', 'multiple'], true)) {
            throw new \invalid_parameter_exception('selectionmode muss single oder multiple sein.');
        }
        foreach ($answers as $answer) {
            if ($answer['fraction'] < -1 || $answer['fraction'] > 1) {
                throw new \invalid_parameter_exception('fraction muss zwischen -1 und 1 liegen.');
            }
            if ((bool) $answer['correct'] !== ((float) $answer['fraction'] > 0)) {
                throw new \invalid_parameter_exception('Korrektheit und fraction muessen uebereinstimmen.');
            }
        }
        if ($params['selectionmode'] === 'single' && count(array_filter($answers,
                static fn($answer) => !empty($answer['correct']))) !== 1) {
            throw new \invalid_parameter_exception('Eine Einfachauswahl braucht genau eine richtige Antwort.');
        }
        return $answers;
    }

    private static function insert_answers(int $questionid, array $answers): array {
        global $DB;
        $answerids = [];
        foreach ($answers as $answerinput) {
            $a = new \stdClass();
            $a->question       = $questionid;
            $a->answer         = (string) $answerinput['answer'];
            $a->answerformat   = FORMAT_HTML;
            $a->fraction       = (float) $answerinput['fraction'];
            $a->feedback       = (string) $answerinput['feedback'];
            $a->feedbackformat = FORMAT_HTML;
            $answerids[] = (int) $DB->insert_record('question_answers', $a);
        }
        return $answerids;
    }

    private static function warnings(array $answers, string $selectionmode): array {
        $sum = array_sum(array_map(static fn($answer) => (float) $answer['fraction'], $answers));
        $warnings = [];
        if ($selectionmode === 'multiple' && $sum >= 1.0) {
            $warnings[] = 'Wer alle Antworten auswählt, erhält volle Punktzahl; prüfe die Gewichte der Distraktoren.';
        }
        $correct = count(array_filter($answers, static fn($answer) => (float) $answer['fraction'] > 0));
        if ($selectionmode === 'multiple' && $correct !== count($answers) - $correct) {
            $warnings[] = 'Die Zahl richtiger und nicht richtiger Antworten ist unausgewogen; prüfe die Gewichte für eine faire Bewertung.';
        }
        return $warnings;
    }

    private static function insert_multichoice_options(int $questionid, string $selectionmode): void {
        global $DB;
        $mc = new \stdClass();
        $mc->questionid                     = $questionid;
        $mc->layout                         = 0;
        $mc->single                         = $selectionmode === 'single' ? 1 : 0;
        $mc->shuffleanswers                 = 1;
        $mc->correctfeedback                = '';
        $mc->correctfeedbackformat          = FORMAT_HTML;
        $mc->partiallycorrectfeedback       = '';
        $mc->partiallycorrectfeedbackformat = FORMAT_HTML;
        $mc->incorrectfeedback              = '';
        $mc->incorrectfeedbackformat        = FORMAT_HTML;
        $mc->answernumbering                = 'abc';
        $mc->shownumcorrect                 = 0;
        $mc->showstandardinstruction        = 0;
        $DB->insert_record('qtype_multichoice_options', $mc);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid'          => new external_value(PARAM_INT,  'ID der neu erzeugten question-Zeile (=latest version)'),
            'questionbankentryid' => new external_value(PARAM_INT,  'ID des question_bank_entries (unveraendert)'),
            'version'             => new external_value(PARAM_INT,  'Neue Versionsnummer (max+1)'),
            'previousquestionid'  => new external_value(PARAM_INT,  'questionid der Vorgaengerversion (bleibt fuer existierende Attempts gueltig)'),
            'answerids'           => new external_multiple_structure(
                new external_value(PARAM_INT, 'question_answers.id'),
                'IDs der neu angelegten Antworten in Reihenfolge der options[]'
            ),
            'warnings'            => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Hinweis zur Gewichtung'), 'Gewichtungswarnungen'
            ),
            'message'             => new external_value(PARAM_TEXT, 'Status-Nachricht'),
        ]);
    }
}
