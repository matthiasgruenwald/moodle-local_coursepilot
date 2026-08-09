<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursepilot\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_course;
use local_coursepilot\assign_settings;

/**
 * Creates a mod_assign activity inside a given section.
 */
class create_assign extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'        => new external_value(PARAM_INT,  'Course ID'),
            'sectionnum'      => new external_value(PARAM_INT,  'Section number (0-based)'),
            'name'            => new external_value(PARAM_TEXT, 'Assignment title'),
            'description'     => new external_value(PARAM_RAW,  'Assignment description (HTML)', VALUE_DEFAULT, ''),
            'duedate'         => new external_value(PARAM_INT,  'Due date as Unix timestamp (0 = no due date)', VALUE_DEFAULT, 0),
            'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'Allow submissions from (Unix timestamp, 0 = always)', VALUE_DEFAULT, 0),
            'maxfiles'        => new external_value(PARAM_INT,  'Max number of uploaded files (0 = no file upload)', VALUE_DEFAULT, 1),
            'submissiondrafts' => new external_value(PARAM_INT, 'Require students to click Submit (1) or auto-submit (0)', VALUE_DEFAULT, -1),
            'visible'         => new external_value(PARAM_INT,  'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
            'mode' => new external_value(PARAM_TEXT, 'Aufgaben-Preset: standard oder übung', VALUE_DEFAULT, 'standard'),
            'grade' => new external_value(PARAM_INT, 'Maximale Bewertung (0 = unbewertet, -1 = Preset-Standard)', VALUE_DEFAULT, -1),
            'gradepass' => new external_value(PARAM_FLOAT, 'Bestehensgrenze in Punkten (-1 = Preset-Standard)', VALUE_DEFAULT, -1),
            'maxattempts' => new external_value(PARAM_INT, 'Maximale Versuche (-1 = unbegrenzt, -2 = Preset-Standard)', VALUE_DEFAULT, -2),
            'attemptreopenmethod' => new external_value(PARAM_TEXT, 'Wiedereröffnungsmethode (leer = Preset-Standard)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int    $courseid,
        int    $sectionnum,
        string $name,
        string $description = '',
        int    $duedate = 0,
        int    $allowsubmissionsfromdate = 0,
        int    $maxfiles = 1,
        int    $submissiondrafts = -1,
        int    $visible = 1,
        string $mode = 'standard',
        int    $grade = -1,
        float  $gradepass = -1,
        int    $maxattempts = -2,
        string $attemptreopenmethod = ''
    ): array {
        global $DB, $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'                 => $courseid,
            'sectionnum'               => $sectionnum,
            'name'                     => $name,
            'description'              => $description,
            'duedate'                  => $duedate,
            'allowsubmissionsfromdate' => $allowsubmissionsfromdate,
            'maxfiles'                 => $maxfiles,
            'submissiondrafts'         => $submissiondrafts,
            'visible'                  => $visible,
            'mode'                     => $mode,
            'grade'                    => $grade,
            'gradepass'                => $gradepass,
            'maxattempts'              => $maxattempts,
            'attemptreopenmethod'      => $attemptreopenmethod,
        ]);

        // Check permissions.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursepilot:use', $context);
        require_capability('moodle/course:manageactivities', $context);

        // Get the course.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        if (!in_array($params['mode'], ['standard', 'übung'], true)) {
            throw new \invalid_parameter_exception('Unzulässiges Aufgaben-Preset.');
        }
        $moduleinfo = assign_settings::create_moduleinfo($params);
        assign_settings::validate_attempt_settings($moduleinfo, $params);

        // Add the module to the course.
        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return array_merge([
            'cmid'    => (int) $moduleinfo->coursemodule,
            'message' => 'Assignment "' . $params['name'] . '" successfully created.',
        ], assign_settings::result((int) $moduleinfo->coursemodule));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'    => new external_value(PARAM_INT,  'Course module ID of the created assignment'),
            'message' => new external_value(PARAM_TEXT, 'Success message'),
            'name' => new external_value(PARAM_TEXT, 'Gespeicherter Aufgabentitel'),
            'grade' => new external_value(PARAM_INT, 'Gespeicherte maximale Bewertung'),
            'gradepass' => new external_value(PARAM_FLOAT, 'Gespeicherte Bestehensgrenze'),
            'submissiondrafts' => new external_value(PARAM_INT, 'Gespeicherte Einstellung für endgültige Abgabe'),
            'maxattempts' => new external_value(PARAM_INT, 'Gespeicherte maximale Versuche'),
            'attemptreopenmethod' => new external_value(PARAM_TEXT, 'Gespeicherte Wiedereröffnungsmethode'),
            'visible' => new external_value(PARAM_INT, 'Gespeicherte Sichtbarkeit'),
        ]);
    }
}
