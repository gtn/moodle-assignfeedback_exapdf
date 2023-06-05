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
        global $DB;

        static::validate_parameters(static::get_file_infos_parameters(), array(
            'fileid' => $fileid,
        ));

        static::require_can_view_submission($fileid);

        [$contextid, $gradeid, $filehash] = explode('/', $fileid);


        $grade = $DB->get_record('assign_grades', array('id' => $gradeid), '*', MUST_EXIST);

        $context = \context::instance_by_id($contextid);
        $combined_file = utils::get_combined_file($context, $grade);
        $submission_file_url = (string)\moodle_url::make_webservice_pluginfile_url(
            $combined_file->get_contextid(), $combined_file->get_component(), $combined_file->get_filearea(),
            $combined_file->get_itemid(), $combined_file->get_filepath(), $combined_file->get_filename(),
        );

        $fs = get_file_storage();
        $annotated_file = current($fs->get_directory_files($contextid, 'assignfeedback_exapdf', 'annotated', $gradeid, "/{$filehash}/", false, false));

        $annotation_records = $DB->get_records('assignfeedback_exapdf_annot', ['gradeid' => $gradeid]);
        $annotations = [];
        if ($annotation_records) {
            $pageids = utils::get_combined_file_pageids($context, $grade);
            $pageids_by_doc_and_page = array_map(function($pageid) {
                return join('/', $pageid);
            }, $pageids);
            $pageids_by_doc_and_page = array_flip($pageids_by_doc_and_page);

            foreach ($annotation_records as $annotation_record) {
                if (!$annotation_record->annotations) {
                    continue;
                }

                $file_annotations = json_decode($annotation_record->annotations);
                foreach ($file_annotations as $file_annotation) {
                    $file_annotation->pageIndex = @$pageids_by_doc_and_page[$annotation_record->filecontenthash . '/' . $file_annotation->pageIndex];
                    if ($file_annotation->pageIndex === null) {
                        // annotation for unknown file
                        continue;
                    }
                    $annotations[] = $file_annotation;
                }
            }
        }

        $annotations_timemodified = $annotation_records ? current($annotation_records)->timemodified : 0;

        return [
            'submission_file_url' => $submission_file_url,
            'annotations' => json_encode([
                'annotations' => $annotations,
                'format' => "https://pspdfkit.com/instant-json/v1",
            ]),
            'annotations_changed' => $annotations_timemodified && (($annotated_file ? $annotations_timemodified > $annotated_file->get_timemodified() : true)),
        ];
    }

    public static function get_file_infos_returns() {
        return new external_single_structure (array(
            'submission_file_url' => new external_value (PARAM_TEXT),
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
        global $DB;

        static::validate_parameters(static::save_annotations_parameters(), array(
            'fileid' => $fileid,
            'annotations' => $annotations,
        ));

        static::require_can_view_submission($fileid);

        [$contextid, $gradeid, $filehash] = explode('/', $fileid);

        // TODO: Prüfen, ob sich was geändert hat, sonst muss nichts gespeichert werden

        $grade = $DB->get_record('assign_grades', array('id' => $gradeid), '*', MUST_EXIST);
        $context = \context::instance_by_id($contextid);
        $combined_file = utils::get_combined_file($context, $grade);

        if ($combined_file->get_contenthash() !== $filehash) {
            throw new \moodle_exception('submission changed!');
        }

        $annotations = json_decode($annotations);

        if ($annotations) {
            $pageids = utils::get_combined_file_pageids($context, $grade);

            // combine all annotations for one document
            $annotations_per_document = [];
            foreach ($pageids as $pageid) {
                $annotations_per_document[$pageid[0]] = [];
            }
            foreach ($annotations->annotations as $annotation) {
                $originalPageIndex = $annotation->pageIndex;
                $annotation->pageIndex = $pageids[$originalPageIndex][1];
                $annotations_per_document[$pageids[$originalPageIndex][0]][] = $annotation;
            }

            // update db
            foreach ($annotations_per_document as $contenthash => $document_annotations) {
                // this is disabled, also save empty annotations, so we can remember the timemodified
                // if (!$document_annotations) {
                //     $DB->delete_records('assignfeedback_exapdf_annot', ['gradeid' => $gradeid, 'filecontenthash' => $contenthash]);
                //     continue;
                // }

                $document_annotations = json_encode($document_annotations);

                $record = $DB->get_record('assignfeedback_exapdf_annot', ['gradeid' => $gradeid, 'filecontenthash' => $contenthash]);
                if ($record) {
                    $record->annotations = $document_annotations;
                    $DB->update_record('assignfeedback_exapdf_annot', ['id' => $record->id, 'timemodified' => time(), 'annotations' => $document_annotations]);
                } else {
                    $DB->insert_record('assignfeedback_exapdf_annot', ['gradeid' => $gradeid, 'filecontenthash' => $contenthash, 'timemodified' => time(), 'annotations' => $document_annotations]);
                }
            }
        }

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
        global $USER, $DB;

        static::validate_parameters(static::save_annotated_parameters(), array(
            'fileid' => $fileid,
            'draftitemid' => $draftitemid,
        ));

        static::require_can_view_submission($fileid);

        [$contextid, $gradeid, $filehash] = explode('/', $fileid);

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $draftFile = current($fs->get_area_files($context->id, 'user', 'draft', $draftitemid, null, false));
        if (!$draftFile) {
            throw new moodle_exception('file not found');
        }

        // delete old files
        $fs->delete_area_files($contextid, 'assignfeedback_exapdf', 'annotated', $gradeid);

        $filerecord = (object)[
            'contextid' => $contextid,
            'component' => 'assignfeedback_exapdf',
            'filearea' => 'annotated',
            'filepath' => '/' . $filehash . '/',
            'userid' => null,
            'itemid' => $gradeid,
            'filename' => 'Annotated.pdf',
        ];
        $fs->create_file_from_storedfile($filerecord, $draftFile);
        $draftFile->delete();

        return array("success" => true);
    }

    public static function save_annotated_returns() {
        return new external_single_structure (array(
            'success' => new external_value (PARAM_BOOL, 'status'),
        ));
    }

    private static function require_can_view_submission($fileid) {
        global $DB;

        [$contextid, $gradeid, $filehash] = explode('/', $fileid);

        $context = context::instance_by_id($contextid);
        // $cm = \get_coursemodule_from_instance('assign', $context->instanceid, 0, false, MUST_EXIST);

        $assign = new assign($context, null, null);

        $record = $DB->get_record('assign_grades', array('id' => $gradeid), 'userid,assignment', MUST_EXIST);
        $userid = $record->userid;
        if (!$assign->can_view_submission($userid)) {
            return false;
        }
    }
}
