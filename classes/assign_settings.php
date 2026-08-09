<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursepilot;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');

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
        $moduleinfo->cutoffdate = $params['cutoffdate'];
        $moduleinfo->gradingduedate = $params['gradingduedate'];
        $moduleinfo->submissiondrafts = $params['mode'] === 'übung' ? 0 : 1;
        $moduleinfo->requiresubmissionstatement = $params['requiresubmissionstatement'];
        $moduleinfo->sendnotifications = $params['sendnotifications'];
        $moduleinfo->sendlatenotifications = $params['sendlatenotifications'];
        $moduleinfo->sendstudentnotifications = $params['sendstudentnotifications'];
        $moduleinfo->assignsubmission_onlinetext_enabled = 1;
        $moduleinfo->assignsubmission_file_enabled = $params['maxfiles'] > 0 ? 1 : 0;
        $moduleinfo->assignsubmission_file_maxfiles = $params['maxfiles'];
        $moduleinfo->assignsubmission_file_maxsizebytes = 0;
        $moduleinfo->assignfeedback_comments_enabled = 1;
        $moduleinfo->assignfeedback_editpdf_enabled = 0;
        $moduleinfo->grade = $params['mode'] === 'übung' ? 0 : 100;
        $moduleinfo->gradepass = 0;
        $moduleinfo->gradecat = $params['gradecat'];
        $moduleinfo->teamsubmission = $params['teamsubmission'];
        $moduleinfo->requireallteammemberssubmit = $params['requireallteammemberssubmit'];
        $moduleinfo->teamsubmissiongroupingid = $params['teamsubmissiongroupingid'];
        $moduleinfo->blindmarking = $params['blindmarking'];
        $moduleinfo->attemptreopenmethod = 'manual';
        $moduleinfo->maxattempts = -1;
        $moduleinfo->markingworkflow = $params['markingworkflow'];
        $moduleinfo->markingallocation = $params['markingallocation'];
        $moduleinfo->advancedgradingmethod_submissions = $params['gradingmethod'] === 'none' ? '' : $params['gradingmethod'];
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
        $moduleinfo->advancedgradingmethod_submissions = \grading_manager::instance(
            \context_module::instance($cm->id), 'mod_assign', 'submissions'
        )->get_active_method();
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
        if (($params['grade'] ?? -1) !== -1) {
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
        foreach (['allowsubmissionsfromdate', 'cutoffdate', 'gradingduedate', 'requiresubmissionstatement',
            'teamsubmission', 'requireallteammemberssubmit', 'teamsubmissiongroupingid', 'sendnotifications',
            'sendlatenotifications', 'sendstudentnotifications', 'blindmarking', 'markingworkflow',
            'markingallocation', 'gradecat'] as $field) {
            if (($params[$field] ?? -1) >= 0) {
                $moduleinfo->{$field} = $params[$field];
            }
        }
        if (($params['gradingmethod'] ?? '') !== '') {
            $moduleinfo->advancedgradingmethod_submissions = $params['gradingmethod'] === 'none' ? '' : $params['gradingmethod'];
        }
        return $moduleinfo;
    }

    /** Validates core assignment dependencies and referenced Moodle objects. */
    public static function validate_core_settings(\stdClass $moduleinfo, array $params, int $courseid): void {
        global $DB;

        $from = (int) $moduleinfo->allowsubmissionsfromdate;
        $due = (int) $moduleinfo->duedate;
        $cutoff = (int) $moduleinfo->cutoffdate;
        if ($from && $due && $from > $due || $due && $cutoff && $due > $cutoff) {
            throw new \invalid_parameter_exception('Ungültige Terminfolge: Abgabebeginn, Abgabetermin und letzter Abgabetermin müssen zeitlich aufeinander folgen.');
        }
        foreach (['requiresubmissionstatement', 'teamsubmission', 'requireallteammemberssubmit', 'sendnotifications',
            'sendlatenotifications', 'sendstudentnotifications', 'blindmarking', 'markingworkflow', 'markingallocation'] as $field) {
            if (!in_array((int) $moduleinfo->{$field}, [0, 1], true)) {
                throw new \invalid_parameter_exception('Die Einstellung ' . $field . ' muss 0 oder 1 sein.');
            }
        }
        if (!empty($moduleinfo->requireallteammemberssubmit) && empty($moduleinfo->teamsubmission)) {
            throw new \invalid_parameter_exception('Alle Gruppenmitglieder können nur bei aktivierter Gruppenabgabe zur Abgabe verpflichtet werden.');
        }
        if ((int) $moduleinfo->teamsubmissiongroupingid > 0) {
            if (empty($moduleinfo->teamsubmission) || !$DB->record_exists('groupings', ['id' => $moduleinfo->teamsubmissiongroupingid, 'courseid' => $courseid])) {
                throw new \invalid_parameter_exception('Gruppenabgabe braucht eine vorhandene Kurs-Gruppierung.');
            }
        }
        if (!empty($moduleinfo->markingallocation) && empty($moduleinfo->markingworkflow)) {
            throw new \invalid_parameter_exception('Bewertungszuordnung setzt den Bewertungsworkflow voraus.');
        }
        if (($params['gradecat'] ?? -1) > 0 && !$DB->record_exists('grade_categories', ['id' => $moduleinfo->gradecat, 'courseid' => $courseid])) {
            throw new \invalid_parameter_exception('Die Bewertungskategorie gehört nicht zu diesem Kurs.');
        }
        if (($params['grade'] ?? -1) < -1 && !$DB->record_exists_select('scale', 'id = ? AND (courseid = 0 OR courseid = ?)', [-$moduleinfo->grade, $courseid])) {
            throw new \invalid_parameter_exception('Die ausgewählte Skala ist nicht im Kurs oder systemweit vorhanden.');
        }
        $method = (string) ($moduleinfo->advancedgradingmethod_submissions ?? '');
        if ($method !== '' && !array_key_exists($method, \grading_manager::available_methods())) {
            throw new \invalid_parameter_exception('Die Bewertungsmethode ist in Moodle nicht verfügbar.');
        }
    }

    /** Refuses settings Moodle freezes once learner work exists. */
    public static function validate_frozen_core_changes(\stdClass $moduleinfo, array $params): void {
        global $DB;

        $frozen = ['teamsubmission', 'requireallteammemberssubmit', 'teamsubmissiongroupingid', 'blindmarking'];
        $changesfrozen = false;
        foreach ($frozen as $field) {
            if (($params[$field] ?? -1) >= 0 && (int) $params[$field] !== (int) $moduleinfo->{$field}) {
                $changesfrozen = true;
            }
        }
        if (($params['gradingmethod'] ?? '') !== ''
            && ($params['gradingmethod'] === 'none' ? '' : $params['gradingmethod']) !== ($moduleinfo->advancedgradingmethod_submissions ?? '')) {
            $changesfrozen = true;
        }
        if (empty($moduleinfo->id) || !$changesfrozen) {
            return;
        }
        if ($DB->record_exists('assign_submission', ['assignment' => $moduleinfo->id]) || $DB->record_exists('assign_grades', ['assignment' => $moduleinfo->id])) {
            throw new \invalid_parameter_exception('Diese Einstellung ist eingefroren, weil bereits Abgaben oder Bewertungen vorhanden sind.');
        }
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
        $gradingmethod = \grading_manager::instance(\context_module::instance($cmid), 'mod_assign', 'submissions')->get_active_method();
        return [
            'name' => (string) $assign->name,
            'grade' => (int) $assign->grade,
            'gradepass' => (float) ($gradepass ?: 0),
            'submissiondrafts' => (int) $assign->submissiondrafts,
            'maxattempts' => (int) $assign->maxattempts,
            'attemptreopenmethod' => (string) $assign->attemptreopenmethod,
            'visible' => (int) $cm->visible,
            'allowsubmissionsfromdate' => (int) $assign->allowsubmissionsfromdate,
            'cutoffdate' => (int) $assign->cutoffdate,
            'gradingduedate' => (int) $assign->gradingduedate,
            'requiresubmissionstatement' => (int) $assign->requiresubmissionstatement,
            'teamsubmission' => (int) $assign->teamsubmission,
            'requireallteammemberssubmit' => (int) $assign->requireallteammemberssubmit,
            'teamsubmissiongroupingid' => (int) $assign->teamsubmissiongroupingid,
            'sendnotifications' => (int) $assign->sendnotifications,
            'sendlatenotifications' => (int) $assign->sendlatenotifications,
            'sendstudentnotifications' => (int) $assign->sendstudentnotifications,
            'blindmarking' => (int) $assign->blindmarking,
            'markingworkflow' => (int) $assign->markingworkflow,
            'markingallocation' => (int) $assign->markingallocation,
            'gradecat' => (int) $assign->gradecat,
            'gradingmethod' => (string) ($gradingmethod ?: 'none'),
        ];
    }
}
