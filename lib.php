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

/**
 * This file contains the version information for the comments feedback plugin
 *
 * @package assignfeedback_exapdf
 * @copyright  2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once(__DIR__.'/inc.php');

/**
 * Serves assignment feedback and other files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options - List of options affecting file serving.
 * @return bool false if file not found, does not return if found - just send the file
 */
function assignfeedback_exapdf_pluginfile(
    $course,
    $cm,
    context $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = array()
) {
    global $DB;
    if ($filearea === 'systemstamps') {

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return false;
        }

        $filename = array_pop($args);
        $filepath = '/'.implode('/', $args).'/';

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'assignfeedback_exapdf', $filearea, 0, $filepath, $filename);
        if (!$file) {
            return false;
        }

        $options['cacheability'] = 'public';
        $options['immutable'] = true;

        send_stored_file($file, null, 0, false, $options);
    }

    if ($context->contextlevel == CONTEXT_MODULE) {

        require_login($course, false, $cm);
        $itemid = (int)array_shift($args);

        $assign = new assign($context, $cm, $course);

        $record = $DB->get_record('assign_grades', array('id' => $itemid), 'userid,assignment', MUST_EXIST);
        $userid = $record->userid;
        if ($assign->get_instance()->id != $record->assignment) {
            return false;
        }

        // Rely on mod_assign checking permissions.
        if (!$assign->can_view_submission($userid)) {
            return false;
        }

        $relativepath = implode('/', $args);

        $fullpath = "/{$context->id}/assignfeedback_exapdf/$filearea/$itemid/$relativepath";

        $fs = get_file_storage();

        if ($filearea != 'submission_files') {
            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                return false;
            }
        } else {
            // if submission_files, then load it directly from assignsubmission_file plugin
            $fullpath_submission = "/{$context->id}/assignsubmission_file/$filearea/$itemid/$relativepath";

            if (!$file = $fs->get_file_by_hash(sha1($fullpath_submission)) or $file->is_directory()) {
                return false;
            }

            $as_pdf = optional_param('as_pdf', false, PARAM_BOOL);
            if ($as_pdf) {
                if ($file->get_mimetype() == 'application/pdf') {
                    // no conversion needed, already pdf
                } else {
                    // converted file, has additional ".pdf" ending
                    $converted_file = $fs->get_file_by_hash(sha1($fullpath.'.pdf'));
                    if ($converted_file) {
                        $file = $converted_file;
                    } else {
                        // convert
                        $file = exapdf_convert_stored_file_to_pdf($file, [
                            'contextid' => $context->id,
                            'component' => 'assignfeedback_exapdf',
                            'filearea' => $filearea,
                            'filepath' => $file->get_filepath(),
                            'userid' => null,
                            'itemid' => $itemid,
                            'filename' => $file->get_filename().'.pdf',
                        ]);
                    }
                }
            }
        }

        // Download MUST be forced - security!
        send_stored_file($file, 0, 0, false, $options);// Check if we want to retrieve the stamps.
    }

}

function exapdf_convert_stored_file_to_pdf(\stored_file $file, $filerecord) {
    $info = $file->get_imageinfo();
    if (!$info) {
        die('no image? (no image info)');
    }

    $a4_width = 210;
    $a4_height = 297;

    $width = $info['width'];
    $height = $info['height'];

    // bildmaße: 100x300 -> portrait
    // bildmaße: 200x250 -> landscape

    // check if image is bigger than a4, else shrink it.
    // this is needed, because annotation (pencil, stamps, etc.) look best with a4, or else are very small.
    $ratio = 1;
    if ($width / $height <= $a4_width / $a4_height) {
        $orientation = 'P';
        if ($height > $a4_height) {
            $ratio = $a4_height / $height;
        }
    } else {
        $orientation = 'L';
        if ($width > $a4_height) {
            $ratio = $a4_height / $width;
        }
    }

    $width = $width * $ratio;
    $height = $height * $ratio;

    // create PDF object with image size
    $pdf = new \FPDF($orientation, 'mm', array($width, $height));

    // add page and image to PDF
    $pdf->AddPage();

    if (!$tmp_image = $file->copy_content_to_temp()) {
        die("couldn't create tmp image");
    }

    // rename tmp image to include extension, fpdf needs the correct extension!
    $tmp_image_with_extension = $tmp_image.'.'.pathinfo($file->get_filename(), PATHINFO_EXTENSION);
    rename($tmp_image, $tmp_image_with_extension);

    $pdf->Image($tmp_image_with_extension, 0, 0, $width, $height);

    $pdf_output = $pdf->Output('', 'S');

    // Delete temporary image file.
    @unlink($tmp_image_with_extension);

    $fs = get_file_storage();
    return $fs->create_file_from_string($filerecord, $pdf_output);
}


/**
 * Files API hook to remove stale conversion records.
 *
 * When a file is update, its contenthash will change, but its ID
 * remains the same. The document converter API records source file
 * IDs and destination file IDs. When a file is updated, the document
 * converter API has no way of knowing that the content of the file
 * has changed, so it just serves the previously stored destination
 * file.
 *
 * In this hook we check if the contenthash has changed, and if it has
 * we delete the existing conversion so that a new one will be created.
 *
 * @param stdClass $file The updated file record.
 * @param stdClass $filepreupdate The file record pre-update.
 */
function assignfeedback_exapdf_after_file_updated(stdClass $file, stdClass $filepreupdate) {
    $contenthashchanged = $file->contenthash !== $filepreupdate->contenthash;
    if ($contenthashchanged && $file->component == 'assignsubmission_file' && $file->filearea == 'submission_files') {
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($file->id);
        $conversions = \core_files\conversion::get_conversions_for_file($file, 'pdf');

        foreach ($conversions as $conversion) {
            if ($conversion->get('id')) {
                $conversion->delete();
            }
        }
    }
}
