<?php
namespace local_coursepilot\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use local_coursepilot\assign_settings;

class update_assign extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'New assignment title', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'New HTML description', VALUE_DEFAULT, ''),
            'duedate' => new external_value(PARAM_INT, 'New due date (-1 = no change)', VALUE_DEFAULT, -1),
            'visible' => new external_value(PARAM_INT, '1 = visible, 0 = hidden, -1 = no change', VALUE_DEFAULT, -1),
            'grade' => new external_value(PARAM_INT, 'Maximale Bewertung (0 = unbewertet, -1 = nicht ändern)', VALUE_DEFAULT, -1),
            'submissiondrafts' => new external_value(PARAM_INT, 'Endgültige Abgabe nötig (0 oder 1, -1 = nicht ändern)', VALUE_DEFAULT, -1),
            'maxattempts' => new external_value(PARAM_INT, 'Maximale Versuche (-1 = unbegrenzt, -2 = nicht ändern)', VALUE_DEFAULT, -2),
            'attemptreopenmethod' => new external_value(PARAM_TEXT, 'Wiedereröffnungsmethode (leer = nicht ändern)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $cmid, string $name = '', string $description = '', int $duedate = -1, int $visible = -1, int $grade = -1, int $submissiondrafts = -1, int $maxattempts = -2, string $attemptreopenmethod = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'name', 'description', 'duedate', 'visible', 'grade', 'submissiondrafts', 'maxattempts', 'attemptreopenmethod'));
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/coursepilot:use', $context);
        require_capability('moodle/course:manageactivities', $context);

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinfo = assign_settings::patch(assign_settings::snapshot($cm, $course), $params);
        update_moduleinfo($cm, $moduleinfo, $course);
        rebuild_course_cache($cm->course, true);

        return array_merge([
            'cmid' => $cm->id,
            'message' => 'Assignment updated successfully.',
        ], assign_settings::result($cm->id));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'message' => new external_value(PARAM_TEXT, 'Success message'),
            'name' => new external_value(PARAM_TEXT, 'Gespeicherter Aufgabentitel'),
            'grade' => new external_value(PARAM_INT, 'Gespeicherte maximale Bewertung'),
            'submissiondrafts' => new external_value(PARAM_INT, 'Gespeicherte Einstellung für endgültige Abgabe'),
            'maxattempts' => new external_value(PARAM_INT, 'Gespeicherte maximale Versuche'),
            'attemptreopenmethod' => new external_value(PARAM_TEXT, 'Gespeicherte Wiedereröffnungsmethode'),
            'visible' => new external_value(PARAM_INT, 'Gespeicherte Sichtbarkeit'),
        ]);
    }
}
