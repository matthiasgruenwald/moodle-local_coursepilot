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
 * Legt eine Multiple-Choice-Frage (qtype_multichoice) in einer Fragenbank-
 * Kategorie an. Bestehende options[] + correctindex-Aufrufe bleiben
 * Einfachauswahl; answers[] kann Gewicht und Antwortfeedback ausdruecken.
 *
 * Erzeugt einen neuen question_bank_entries-Eintrag sowie die initiale
 * question_versions-Zeile (version = 1). Spaetere update_mc_question-Aufrufe
 * haengen neue Versionen an dieselbe questionbankentryid (ADR-0001).
 */
class create_mc_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid'      => new external_value(PARAM_INT,   'ID der Fragenbank-Kategorie'),
            'name'            => new external_value(PARAM_TEXT,  'Eindeutiger Name der Frage innerhalb der Kategorie'),
            'questiontext'    => new external_value(PARAM_RAW,   'Fragetext (HTML)'),
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
        int    $categoryid,
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
            'categoryid'      => $categoryid,
            'name'            => $name,
            'questiontext'    => $questiontext,
            'options'         => $options,
            'correctindex'    => $correctindex,
            'selectionmode'   => $selectionmode,
            'answers'         => $answers,
            'defaultmark'     => $defaultmark,
            'generalfeedback' => $generalfeedback,
        ]);

        // Kategorie + Kontext aufloesen, Capability pruefen.
        $category = $DB->get_record('question_categories',
            ['id' => $params['categoryid']], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);
        self::validate_context($context);
        require_capability('local/coursepilot:use', $context);
        require_capability('moodle/question:add', $context);

        $answers = self::normalise_answers($params);

        $now = time();

        // 1) Neue question-Zeile.
        $questionid = self::insert_question_row(
            $params['categoryid'],
            $params['name'],
            $params['questiontext'],
            $params['generalfeedback'],
            $params['defaultmark'],
            $now,
            $USER->id
        );

        // 2) Neuer question_bank_entries-Eintrag (Identitaet der Frage ueber
        //    alle Versionen) + initiale question_versions-Zeile (version=1).
        $entryid = self::insert_bank_entry($params['categoryid'], $USER->id);
        self::insert_version_row($entryid, 1, $questionid);

        // 3) Antworten + qtype_multichoice_options.
        $answerids = self::insert_answers(
            $questionid, $answers);
        self::insert_multichoice_options($questionid, $params['selectionmode']);

        return [
            'questionid'          => (int) $questionid,
            'questionbankentryid' => (int) $entryid,
            'version'             => 1,
            'answerids'           => array_map('intval', $answerids),
            'warnings'            => self::warnings($answers, $params['selectionmode']),
            'message'             => 'MC-Frage "' . $params['name'] . '" erfolgreich angelegt.',
        ];
    }

    private static function insert_question_row(
        int    $categoryid,
        string $name,
        string $questiontext,
        string $generalfeedback,
        float  $defaultmark,
        int    $now,
        int    $userid
    ): int {
        global $DB;
        $question = new \stdClass();
        $question->category              = $categoryid;
        $question->parent                = 0;
        $question->name                  = $name;
        $question->questiontext          = $questiontext;
        $question->questiontextformat    = FORMAT_HTML;
        $question->generalfeedback       = $generalfeedback;
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark           = $defaultmark;
        $question->penalty               = 0;
        $question->qtype                 = 'multichoice';
        $question->length                = 1;
        $question->stamp                 = make_unique_id_code();
        $question->timecreated           = $now;
        $question->timemodified          = $now;
        $question->createdby             = $userid;
        $question->modifiedby            = $userid;
        $question->idnumber              = null;
        return (int) $DB->insert_record('question', $question);
    }

    private static function insert_bank_entry(int $categoryid, int $userid): int {
        global $DB;
        $entry = new \stdClass();
        $entry->questioncategoryid = $categoryid;
        $entry->idnumber           = null;
        $entry->ownerid            = $userid;
        return (int) $DB->insert_record('question_bank_entries', $entry);
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
            $answer = new \stdClass();
            $answer->question       = $questionid;
            $answer->answer         = (string) $answerinput['answer'];
            $answer->answerformat   = FORMAT_HTML;
            $answer->fraction       = (float) $answerinput['fraction'];
            $answer->feedback       = (string) $answerinput['feedback'];
            $answer->feedbackformat = FORMAT_HTML;
            $answerids[] = (int) $DB->insert_record('question_answers', $answer);
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
            'questionid'          => new external_value(PARAM_INT,  'ID der neu erzeugten question-Zeile'),
            'questionbankentryid' => new external_value(PARAM_INT,  'ID des question_bank_entries (Frage-Identitaet ueber alle Versionen)'),
            'version'             => new external_value(PARAM_INT,  'Versionsnummer (initial = 1)'),
            'answerids'           => new external_multiple_structure(
                new external_value(PARAM_INT, 'question_answers.id'),
                'IDs der angelegten Antworten in Reihenfolge der options[]'
            ),
            'warnings'            => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Hinweis zur Gewichtung'), 'Gewichtungswarnungen'
            ),
            'message'             => new external_value(PARAM_TEXT, 'Status-Nachricht'),
        ]);
    }
}
