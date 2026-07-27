<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursepilot\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once(__DIR__ . '/create_quiz.php');

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Applies a Kurspilot quiz mode to an existing mod_quiz activity.
 */
class update_quiz_settings extends external_api {

    private static function read_saved_settings(\stdClass $cm, float $gradepass): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $cmrecord = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $reviewflags = create_quiz::review_form_flags($quiz);
        $feedbackrecords = create_quiz::read_feedback_records((int) $quiz->id);

        return array_merge([
            'preferredbehaviour' => (string) $quiz->preferredbehaviour,
            'questionsperpage'   => (int) $quiz->questionsperpage,
            'attempts'           => (int) $quiz->attempts,
            'grademethod'        => (int) $quiz->grademethod,
            'gradepass'          => (float) $gradepass,
            'decimalpoints'      => (int) $quiz->decimalpoints,
            'completion'         => (int) $cmrecord->completion,
            'completionusegrade' => isset($cmrecord->completiongradeitemnumber) && $cmrecord->completiongradeitemnumber !== null ? 1 : 0,
            'completionpassgrade'=> (int) $cmrecord->completionpassgrade,
            'reviewrightanswer'  => (int) $quiz->reviewrightanswer,
            'reviewmaxmarks'     => (int) $quiz->reviewmaxmarks,
            'reviewmarks'        => (int) $quiz->reviewmarks,
            'reviewoverallfeedback' => (int) $quiz->reviewoverallfeedback,
            'feedbackboundaries' => create_quiz::feedback_boundaries($feedbackrecords),
            'feedbackrecords'    => $feedbackrecords,
        ], $reviewflags);
    }

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array_merge([
            'cmid'      => new external_value(PARAM_INT, 'Course module ID of the quiz'),
            'mode'      => new external_value(PARAM_ALPHANUMEXT, "Quizmodus: 'mini-check', 'lernstandscheck' (Default) oder 'abschlusstest'. Deprecated aliases: 'intensiv', 'lerncheck', 'bewertung'.", VALUE_DEFAULT, 'lernstandscheck'),
            'gradepass' => new external_value(PARAM_FLOAT, 'Bestehensgrenze in Prozent (0-100). 0 = Modus-Default verwenden.', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Zeitlimit in Sekunden (0 = unbegrenzt / Modus-Default).', VALUE_DEFAULT, 0),
        ], create_quiz::overridable_field_params()));
    }

    public static function execute(
        int $cmid,
        string $mode = 'lernstandscheck',
        float $gradepass = 0,
        int $timelimit = 0,
        string $preferredbehaviour = '',
        string $navmethod = '',
        int    $questionsperpage = -1,
        int    $attempts = -1,
        int    $attemptonlast = -1,
        int    $grademethod = -1,
        int    $delay1 = -1,
        int    $delay2 = -1,
        int    $shuffleanswers = -1,
        int    $decimalpoints = -1,
        int    $completion = -1,
        int    $completionusegrade = -1,
        int    $completionpassgrade = -1,
        int    $reviewattempt = -1,
        int    $reviewcorrectness = -1,
        int    $reviewmaxmarks = -1,
        int    $reviewmarks = -1,
        int    $reviewspecificfeedback = -1,
        int    $reviewgeneralfeedback = -1,
        int    $reviewrightanswer = -1,
        int    $reviewoverallfeedback = -1,
        string $overallfeedbacktextpass = '',
        string $overallfeedbacktextfail = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'      => $cmid,
            'mode'      => $mode,
            'gradepass' => $gradepass,
            'timelimit' => $timelimit,
            'preferredbehaviour' => $preferredbehaviour,
            'navmethod' => $navmethod,
            'questionsperpage' => $questionsperpage,
            'attempts' => $attempts,
            'attemptonlast' => $attemptonlast,
            'grademethod' => $grademethod,
            'delay1' => $delay1,
            'delay2' => $delay2,
            'shuffleanswers' => $shuffleanswers,
            'decimalpoints' => $decimalpoints,
            'completion' => $completion,
            'completionusegrade' => $completionusegrade,
            'completionpassgrade' => $completionpassgrade,
            'reviewattempt' => $reviewattempt,
            'reviewcorrectness' => $reviewcorrectness,
            'reviewmaxmarks' => $reviewmaxmarks,
            'reviewmarks' => $reviewmarks,
            'reviewspecificfeedback' => $reviewspecificfeedback,
            'reviewgeneralfeedback' => $reviewgeneralfeedback,
            'reviewrightanswer' => $reviewrightanswer,
            'reviewoverallfeedback' => $reviewoverallfeedback,
            'overallfeedbacktextpass' => $overallfeedbacktextpass,
            'overallfeedbacktextfail' => $overallfeedbacktextfail,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/coursepilot:use', $context);
        require_capability('moodle/course:manageactivities', $context);

        $moderesolution = create_quiz::normalize_mode($params['mode']);
        $modekey = $moderesolution['mode'];
        $defaults = create_quiz::apply_field_overrides(create_quiz::mode_defaults($modekey), $params);

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->preferredbehaviour = $defaults['preferredbehaviour'];
        $quiz->attempts = $defaults['attempts'];
        $quiz->grademethod = $defaults['grademethod'];
        $quiz->timelimit = $params['timelimit'] > 0 ? $params['timelimit'] : $defaults['timelimit'];
        $quiz->questionsperpage = $defaults['questionsperpage'];
        $quiz->navmethod = $defaults['navmethod'];
        $quiz->shuffleanswers = $defaults['shuffleanswers'];
        $quiz->attemptonlast = $defaults['attemptonlast'];
        $quiz->delay1 = $defaults['delay1'];
        $quiz->delay2 = $defaults['delay2'];
        $quiz->decimalpoints = $defaults['decimalpoints'];
        $quiz->timemodified = time();

        // Includes reviewrightanswer, which Kurspilot modes intentionally clear.
        foreach (create_quiz::review_fields() as $field) {
            $quiz->{$field} = $defaults[$field];
        }

        $DB->update_record('quiz', $quiz);

        $gradepassvalue = $params['gradepass'] > 0 ? $params['gradepass'] : $defaults['gradepass'];
        $gradeitem = $DB->get_record('grade_items', [
            'courseid'     => $cm->course,
            'itemtype'     => 'mod',
            'itemmodule'   => 'quiz',
            'iteminstance' => $quiz->id,
        ]);
        if ($gradeitem) {
            $gradeitem->gradepass = $gradepassvalue;
            $gradeitem->timemodified = time();
            $DB->update_record('grade_items', $gradeitem);
        }

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        if (!$course->enablecompletion) {
            $DB->set_field('course', 'enablecompletion', 1, ['id' => $cm->course]);
        }
        $DB->set_field('course_modules', 'completion', $defaults['completion'], ['id' => $cm->id]);
        $DB->set_field('course_modules', 'completionview', 0, ['id' => $cm->id]);
        $DB->set_field('course_modules', 'completiongradeitemnumber', 0, ['id' => $cm->id]);
        $DB->set_field('course_modules', 'completionpassgrade', $defaults['completionpassgrade'], ['id' => $cm->id]);

        create_quiz::save_overall_feedback((int) $quiz->id, $defaults['overallfeedback'], (float) $gradepassvalue);
        rebuild_course_cache($cm->course, true);
        $savedsettings = self::read_saved_settings($cm, (float) $gradepassvalue);

        return array_merge([
            'cmid'           => (int) $cm->id,
            'mode'           => $modekey,
            'deprecatedmode' => $moderesolution['deprecated'],
            'message'        => 'Quiz settings successfully updated (mode=' . $modekey . ').',
        ], $savedsettings);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure(array_merge([
            'cmid'           => new external_value(PARAM_INT, 'Course module ID of the updated quiz'),
            'mode'           => new external_value(PARAM_TEXT, 'Tatsächlich angewendeter Modus'),
            'deprecatedmode' => new external_value(PARAM_BOOL, 'True when a deprecated alias was accepted and mapped'),
            'message'        => new external_value(PARAM_TEXT, 'Success message'),
        ], create_quiz::saved_settings_return_structure()));
    }
}
