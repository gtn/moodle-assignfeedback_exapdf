<?php

namespace assignfeedback_exapdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

class api {
    public static function ws_get_file_infos($fileid) {
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

    public static function ws_save_annotations($fileid, $annotations) {
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

    public static function ws_save_annotated($fileid, $draftitemid) {
        global $USER;

        static::validate_parameters(static::diggrplus_exapdf_save_annotated_parameters(), array(
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
            'filename' => $submissionFile->get_filename().'-Bewertet.pdf',
        ];
        $fs->create_file_from_storedfile($filerecord, $file);
        $file->delete();

        return array("success" => true);
    }

    private static function require_can_view_submission($fileid) {
        global $DB;

        [$contextid, $itemid, $filehash] = explode('/', $fileid);

        $context = \context::instance_by_id($contextid);
        // $cm = \get_coursemodule_from_instance('assign', $context->instanceid, 0, false, MUST_EXIST);

        $assign = new \assign($context, null, null);

        $record = $DB->get_record('assign_grades', array('id' => $itemid), 'userid,assignment', MUST_EXIST);
        $userid = $record->userid;
        if (!$assign->can_view_submission($userid)) {
            return false;
        }
    }
}
