<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursepilot;

defined('MOODLE_INTERNAL') || die();

/** Builds the complete moduleinfo snapshot used for assignment changes. */
class assign_settings {
    public static function create_moduleinfo(array $params): \stdClass {
        global $DB;

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'assign';
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);
        $moduleinfo->course = $params['courseid'];
        $moduleinfo->section = $params['sectionnum'];
        $moduleinfo->name = $params['name'];
        $moduleinfo->visible = $params['visible'];
        $moduleinfo->intro = $params['description'];
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->allowsubmissionsfromdate = $params['allowsubmissionsfromdate'];
        $moduleinfo->duedate = $params['duedate'];
        $moduleinfo->cutoffdate = 0;
        $moduleinfo->gradingduedate = 0;
        $moduleinfo->submissiondrafts = $params['mode'] === 'übung' ? 0 : 1;
        $moduleinfo->requiresubmissionstatement = 0;
        $moduleinfo->sendnotifications = 0;
        $moduleinfo->sendlatenotifications = 0;
        $moduleinfo->sendstudentnotifications = 1;
        $moduleinfo->assignsubmission_onlinetext_enabled = 1;
        $moduleinfo->assignsubmission_file_enabled = $params['maxfiles'] > 0 ? 1 : 0;
        $moduleinfo->assignsubmission_file_maxfiles = $params['maxfiles'];
        $moduleinfo->assignsubmission_file_maxsizebytes = 0;
        $moduleinfo->assignfeedback_comments_enabled = 1;
        $moduleinfo->assignfeedback_editpdf_enabled = 0;
        $moduleinfo->grade = $params['mode'] === 'übung' ? 0 : 100;
        $moduleinfo->gradepass = 0;
        $moduleinfo->gradecat = 0;
        $moduleinfo->teamsubmission = 0;
        $moduleinfo->requireallteammemberssubmit = 0;
        $moduleinfo->teamsubmissiongroupingid = 0;
        $moduleinfo->blindmarking = 0;
        $moduleinfo->attemptreopenmethod = 'manual';
        $moduleinfo->maxattempts = -1;
        $moduleinfo->markingworkflow = 0;
        $moduleinfo->markingallocation = 0;
        $moduleinfo->cmidnumber = '';

        return self::patch($moduleinfo, $params);
    }

    /** Loads all core and subplugin settings, so a partial patch cannot erase them. */
    public static function snapshot(\stdClass $cm, \stdClass $course): \stdClass {
        global $DB;

        // Moodle prepares the CM fields and introeditor draft required by update_moduleinfo().
        [, , , $moduleinfo] = get_moduleinfo_data($cm, $course);
        foreach ($DB->get_records('assign_plugin_config', ['assignment' => $moduleinfo->id]) as $config) {
            $field = $config->subtype . '_' . $config->plugin . '_' . $config->name;
            $moduleinfo->{$field} = $config->value;
        }
        return $moduleinfo;
    }

    /** Applies only explicitly supplied values; sentinels keep the snapshot value. */
    public static function patch(\stdClass $moduleinfo, array $params): \stdClass {
        if (($params['name'] ?? '') !== '') {
            $moduleinfo->name = $params['name'];
        }
        if (($params['description'] ?? '') !== '') {
            $moduleinfo->intro = $params['description'];
            $moduleinfo->introformat = FORMAT_HTML;
        }
        if (($params['duedate'] ?? -1) >= 0) {
            $moduleinfo->duedate = $params['duedate'];
        }
        if (($params['visible'] ?? -1) >= 0) {
            $moduleinfo->visible = $params['visible'];
        }
        if (($params['grade'] ?? -1) >= 0) {
            $moduleinfo->grade = $params['grade'];
        }
        if (($params['gradepass'] ?? -1) >= 0) {
            $moduleinfo->gradepass = $params['gradepass'];
        }
        if (($params['submissiondrafts'] ?? -1) >= 0) {
            $moduleinfo->submissiondrafts = $params['submissiondrafts'];
        }
        if (($params['maxattempts'] ?? -2) >= -1) {
            $moduleinfo->maxattempts = $params['maxattempts'];
        }
        if (($params['attemptreopenmethod'] ?? '') !== '') {
            $moduleinfo->attemptreopenmethod = $params['attemptreopenmethod'];
        }
        return $moduleinfo;
    }

    /** Validates Moodle's dependent submission and attempt settings. */
    public static function validate_attempt_settings(\stdClass $moduleinfo, array $params): void {
        $submissiondrafts = ($params['submissiondrafts'] ?? -1) >= 0
            ? $params['submissiondrafts']
            : (int) $moduleinfo->submissiondrafts;
        $maxattempts = ($params['maxattempts'] ?? -2) >= -1
            ? $params['maxattempts']
            : (int) $moduleinfo->maxattempts;
        $attemptreopenmethod = ($params['attemptreopenmethod'] ?? '') !== ''
            ? $params['attemptreopenmethod']
            : ($moduleinfo->attemptreopenmethod ?? '');

        if (!in_array($submissiondrafts, [0, 1], true)) {
            throw new \invalid_parameter_exception('Die endgültige Abgabe muss 0 oder 1 sein.');
        }
        if (($params['maxattempts'] ?? -2) !== -2 && ($maxattempts < -1 || $maxattempts === 0 || $maxattempts > 30)) {
            throw new \invalid_parameter_exception('Die maximale Versuchszahl muss -1 (unbegrenzt) oder 1 bis 30 sein.');
        }
        if (($params['attemptreopenmethod'] ?? '') !== '' && !in_array($params['attemptreopenmethod'], ['manual', 'automatic', 'untilpass'], true)) {
            throw new \invalid_parameter_exception('Die Wiedereröffnung muss manual, automatic oder untilpass sein.');
        }
        if (($params['gradepass'] ?? -1) >= 0
            && ((int) $moduleinfo->grade <= 0 || (float) $params['gradepass'] > (float) $moduleinfo->grade)) {
            throw new \invalid_parameter_exception('Die Bestehensgrenze braucht eine Bewertung und darf diese nicht überschreiten.');
        }

        $changesattemptflow = ($params['maxattempts'] ?? -2) >= -1
            || ($params['attemptreopenmethod'] ?? '') !== '';
        if ($changesattemptflow && $submissiondrafts !== 1) {
            throw new \invalid_parameter_exception('Eine Wiedereröffnung setzt die endgültige Abgabe voraus.');
        }
        if ($changesattemptflow && $maxattempts !== -1 && $maxattempts < 2) {
            throw new \invalid_parameter_exception('Eine Wiedereröffnung braucht mindestens zwei Versuche oder unbegrenzte Versuche.');
        }
        $checksuntilpass = $attemptreopenmethod === 'untilpass' && ($changesattemptflow
            || ($params['grade'] ?? -1) >= 0 || ($params['gradepass'] ?? -1) >= 0);
        if ($checksuntilpass
            && ((int) $moduleinfo->grade <= 0 || (float) ($moduleinfo->gradepass ?? 0) <= 0)) {
            throw new \invalid_parameter_exception('untilpass braucht eine Bewertung mit Bestehensgrenze.');
        }
        if ($checksuntilpass && !empty($moduleinfo->blindmarking)) {
            throw new \invalid_parameter_exception('untilpass ist mit anonymer Bewertung nicht zulässig.');
        }
    }

    /** Moodle freezes final-submission mode once learner data exists. */
    public static function validate_submissiondrafts_change(\stdClass $moduleinfo, array $params): void {
        global $DB;

        if (($params['submissiondrafts'] ?? -1) < 0
            || (int) $params['submissiondrafts'] === (int) $moduleinfo->submissiondrafts
            || empty($moduleinfo->id)) {
            return;
        }
        if ($DB->record_exists('assign_submission', ['assignment' => $moduleinfo->id])
            || $DB->record_exists('assign_grades', ['assignment' => $moduleinfo->id])) {
            throw new \invalid_parameter_exception('Die endgültige Abgabe ist eingefroren, weil bereits Abgaben oder Bewertungen vorhanden sind.');
        }
    }

    public static function result(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $gradepass = $DB->get_field('grade_items', 'gradepass', [
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'courseid' => $cm->course,
        ]);
        return [
            'name' => (string) $assign->name,
            'grade' => (int) $assign->grade,
            'gradepass' => (float) ($gradepass ?: 0),
            'submissiondrafts' => (int) $assign->submissiondrafts,
            'maxattempts' => (int) $assign->maxattempts,
            'attemptreopenmethod' => (string) $assign->attemptreopenmethod,
            'visible' => (int) $cm->visible,
        ];
    }
}
