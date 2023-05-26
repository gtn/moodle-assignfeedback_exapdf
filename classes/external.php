<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace assignfeedback_exapdf;

defined('MOODLE_INTERNAL') || die();

use external_function_parameters;
use external_value;
use external_single_structure;
use context;
use context_user;
use assign;

global $CFG;

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once $CFG->libdir . '/externallib.php';

class external extends \external_api {
    public static function get_file_infos_parameters() {
        return new external_function_parameters (array(
            'fileid' => new external_value (PARAM_TEXT),
        ));
    }

    /**
     * @ws-type-read
     */
    public static function get_file_infos($fileid) {
        static::validate_parameters(static::get_file_infos_parameters(), array(
            'fileid' => $fileid,
        ));

        static::require_can_view_submission($fileid);

        [$contextid, $itemid, $filehash] = explode('/', $fileid);

        $fs = get_file_storage();
        /* @var \stored_file $annotations_file */
        $annotations_file = current($fs->get_directory_files($contextid, 'assignfeedback_exapdf', 'annotations', $itemid, "/{$filehash}/", false, false));

        $annotated_file = current($fs->get_directory_files($contextid, 'assignfeedback_exapdf', 'annotated', $itemid, "/{$filehash}/", false, false));

        return [
            // 'file_url' => null,
            'annotations' => $annotations_file ? $annotations_file->get_content() : '',
            'annotations_changed' => $annotations_file && (($annotated_file ? $annotations_file->get_timemodified() > $annotated_file->get_timemodified() : true)),
        ];
    }

    public static function get_file_infos_returns() {
        return new external_single_structure (array(
            'annotations' => new external_value (PARAM_TEXT),
            'annotations_changed' => new external_value (PARAM_BOOL),
        ));
    }

    public static function save_annotations_parameters() {
        return new external_function_parameters (array(
            'fileid' => new external_value (PARAM_TEXT),
            'annotations' => new external_value (PARAM_TEXT),
        ));
    }

    /**
     * @ws-type-write
     */
    public static function save_annotations($fileid, $annotations) {
        static::validate_parameters(static::save_annotations_parameters(), array(
            'fileid' => $fileid,
            'annotations' => $annotations,
        ));

        static::require_can_view_submission($fileid);

        [$contextid, $itemid, $filehash] = explode('/', $fileid);

        // TODO: nicht in filearea speichern?!?

        // TODO: Prüfen, ob sich was geändert hat, sonst muss nichts gespeichert werden

        $fs = get_file_storage();

        // delete old files
        $files = $fs->get_directory_files($contextid, 'assignfeedback_exapdf', 'annotations', $itemid, "/{$filehash}/", false, false);
        foreach ($files as $fileToDelete) {
            $fileToDelete->delete();
        }

        $filerecord = (object)[
            'contextid' => $contextid,
            'component' => 'assignfeedback_exapdf',
            'filearea' => 'annotations',
            'filepath' => '/' . $filehash . '/',
            'userid' => null,
            'itemid' => $itemid,
            'filename' => 'annotations.json',
        ];
        $fs->create_file_from_string($filerecord, $annotations);

        return array("success" => true);
    }

    public static function save_annotations_returns() {
        return new external_single_structure (array(
            'success' => new external_value (PARAM_BOOL, 'status'),
        ));
    }

    public static function save_annotated_parameters() {
        return new external_function_parameters (array(
            'fileid' => new external_value (PARAM_TEXT),
            'draftitemid' => new external_value (PARAM_INT),
        ));
    }

    /**
     * @ws-type-write
     */
    public static function save_annotated($fileid, $draftitemid) {
        global $USER;

        static::validate_parameters(static::save_annotated_parameters(), array(
            'fileid' => $fileid,
            'draftitemid' => $draftitemid,
        ));

        static::require_can_view_submission($fileid);

        [$contextid, $itemid, $filehash] = explode('/', $fileid);

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $file = current($fs->get_area_files($context->id, 'user', 'draft', $draftitemid, null, false));
        if (!$file) {
            throw new moodle_exception('file not found');
        }

        // delete old files
        $files = $fs->get_directory_files($contextid, 'assignfeedback_exapdf', 'annotated', $itemid, "/{$filehash}/", false, false);
        foreach ($files as $fileToDelete) {
            $fileToDelete->delete();
        }

        $submissionFiles = $fs->get_area_files($contextid,
            'assignsubmission_file',
            ASSIGNSUBMISSION_FILE_FILEAREA,
            $itemid,
            'timemodified',
            false);
        $submissionFile = current(array_filter($submissionFiles, function($file) use ($filehash) {
            return $file->get_contenthash() == $filehash;
        }));


        $filerecord = (object)[
            'contextid' => $contextid,
            'component' => 'assignfeedback_exapdf',
            'filearea' => 'annotated',
            'filepath' => '/' . $filehash . '/',
            'userid' => null,
            'itemid' => $itemid,
            'filename' => $submissionFile->get_filename() . '-Bewertet.pdf',
        ];
        $fs->create_file_from_storedfile($filerecord, $file);
        $file->delete();

        return array("success" => true);
    }

    public static function save_annotated_returns() {
        return new external_single_structure (array(
            'success' => new external_value (PARAM_BOOL, 'status'),
        ));
    }

    private static function require_can_view_submission($fileid) {
        global $DB;

        [$contextid, $itemid, $filehash] = explode('/', $fileid);

        $context = context::instance_by_id($contextid);
        // $cm = \get_coursemodule_from_instance('assign', $context->instanceid, 0, false, MUST_EXIST);

        $assign = new assign($context, null, null);

        $record = $DB->get_record('assign_grades', array('id' => $itemid), 'userid,assignment', MUST_EXIST);
        $userid = $record->userid;
        if (!$assign->can_view_submission($userid)) {
            return false;
        }
    }
}
