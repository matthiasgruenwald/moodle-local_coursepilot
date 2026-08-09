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
        $moduleinfo->submissiondrafts = 0;
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
        $moduleinfo->attemptreopenmethod = 'none';
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

    public static function result(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        return [
            'name' => (string) $assign->name,
            'grade' => (int) $assign->grade,
            'submissiondrafts' => (int) $assign->submissiondrafts,
            'maxattempts' => (int) $assign->maxattempts,
            'attemptreopenmethod' => (string) $assign->attemptreopenmethod,
            'visible' => (int) $cm->visible,
        ];
    }
}
