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
 * V1-Regeln wie in create_mc_question (single=1, shuffleanswers=1, genau
 * eine richtige Antwort, kein Teilpunkte-Modell).
 */
class update_mc_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid'      => new external_value(PARAM_INT,   'ID der bestehenden question-Zeile (latest version), z.B. aus get_question'),
            'name'            => new external_value(PARAM_TEXT,  'Name der Frage (i.d.R. unveraendert)'),
            'questiontext'    => new external_value(PARAM_RAW,   'Neuer Fragetext (HTML)'),
            'options'         => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Antwort-Option (HTML)'),
                'Antwort-Optionen, mindestens 2'
            ),
            'correctindex'    => new external_value(PARAM_INT,   '0-basierter Index der richtigen Antwort in options[]'),
            'defaultmark'     => new external_value(PARAM_FLOAT, 'Standard-Punktzahl der Frage', VALUE_DEFAULT, 1.0),
            'generalfeedback' => new external_value(PARAM_RAW,   'Allgemeines Feedback (HTML, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int    $questionid,
        string $name,
        string $questiontext,
        array  $options,
        int    $correctindex,
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
            'defaultmark'     => $defaultmark,
            'generalfeedback' => $generalfeedback,
        ]);

        $optioncount = count($params['options']);
        if ($optioncount < 2) {
            throw new \invalid_parameter_exception(
                'Eine Multiple-Choice-Frage braucht mindestens 2 Antwort-Optionen.');
        }
        if ($params['correctindex'] < 0 || $params['correctindex'] >= $optioncount) {
            throw new \invalid_parameter_exception(
                'correctindex liegt ausserhalb der options[]-Range.');
        }

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
            $newquestionid, $params['options'], $params['correctindex']);
        self::insert_multichoice_options($newquestionid);

        return [
            'questionid'          => $newquestionid,
            'questionbankentryid' => $entryid,
            'version'             => $newversionnum,
            'previousquestionid'  => (int) $oldquestion->id,
            'answerids'           => array_map('intval', $answerids),
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

    private static function insert_answers(int $questionid, array $options, int $correctindex): array {
        global $DB;
        $answerids = [];
        foreach ($options as $i => $opttext) {
            $a = new \stdClass();
            $a->question       = $questionid;
            $a->answer         = (string) $opttext;
            $a->answerformat   = FORMAT_HTML;
            $a->fraction       = ($i === $correctindex) ? 1.0 : 0.0;
            $a->feedback       = '';
            $a->feedbackformat = FORMAT_HTML;
            $answerids[] = (int) $DB->insert_record('question_answers', $a);
        }
        return $answerids;
    }

    private static function insert_multichoice_options(int $questionid): void {
        global $DB;
        $mc = new \stdClass();
        $mc->questionid                     = $questionid;
        $mc->layout                         = 0;
        $mc->single                         = 1;
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
            'message'             => new external_value(PARAM_TEXT, 'Status-Nachricht'),
        ]);
    }
}
