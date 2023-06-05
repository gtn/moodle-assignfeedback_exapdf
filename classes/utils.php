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
 * This file contains the definition for the library class for PDF feedback plugin
 *
 *
 * @package   assignfeedback_exapdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_exapdf;

require __DIR__ . '/../inc.php';

class utils {
    /**
     * @param $contextid
     * @param $grade
     * @return \stored_file[]
     */
    public static function get_submission_files($grade): array {
        global $DB;

        $assignment = $grade->assignment;
        $attemptnumber = $grade->attemptnumber;
        $userid = $grade->userid;

        $assignment = document_services::get_assignment_from_param($assignment);
        if ($assignment->get_instance()->teamsubmission) {
            $submission = $assignment->get_group_submission($userid, 0, false, $attemptnumber);
        } else {
            $submission = $assignment->get_user_submission($userid, false, $attemptnumber);
        }
        $user = $DB->get_record('user', array('id' => $userid));

        /* @var \stored_file[] $files */
        $files = [];

        // Ask each plugin for it's list of files.
        foreach ($assignment->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $pluginfiles = $plugin->get_files($submission, $user);
                foreach ($pluginfiles as $filename => $file) {
                    $files[] = $file;
                }
            }
        }

        // TODO: maybe sort by date/time?
        // foreach ($files as $file) {
        //     echo $file->get_filename().' '.$file->get_timemodified().' ';
        // }

        // $files = array_get_assignment_from_paramreverse($files);

        return $files;
    }

    public static function get_combined_file_info($context, $grade) {
        $combined_file = static::get_combined_file($context, $grade);

        if ($combined_file->get_component() == 'assignfeedback_editpdf') {
            // this is a combined file from the moodle converter in editpdf and not exapdf
            // this file may be used in case there are docx etc. files
            // then there is no combined_file_info and no way to find out what files are included in the combined file!
            return null;
        }

        $fs = get_file_storage();

        $combined_info = current($fs->get_area_files($context->id, 'assignfeedback_exapdf', 'combinedfileinfo', $grade->id, 'itemid', false));
        return $combined_info;
    }

    public static function get_combined_file_pageids($context, $grade) {
        $combined_info = static::get_combined_file_info($context, $grade);

        if (!$combined_info) {
            $combined_file = static::get_combined_file($context, $grade);

            // kind of a hack
            // for a combined file from editpdf plugin, just use the combined file content hash for 100 pages
            $pageids = [];
            for ($i = 0; $i < 100; $i++) {
                $pageids[] = [$combined_file->get_contenthash(), $i];
            }

            return $pageids;
        }

        $combined_info = json_decode($combined_info->get_content());

        if ($combined_info) {
            $pageids = [];
            foreach ($combined_info->files as $file) {
                for ($page = 0; $page < $file->pagecnt; $page++) {
                    $pageids[] = [$file->contenthash, $page];
                }
            }

            return $pageids;
        }
    }

    public static function get_combined_file($context, $grade) {
        $submission_files = \assignfeedback_exapdf\utils::get_submission_files($grade);

        $combined_hash = '';
        foreach ($submission_files as $submission_file) {
            $combined_hash .= $submission_file->get_contenthash();
        }
        $combined_hash = md5($combined_hash);

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'assignfeedback_exapdf',
            'filearea' => document_services::COMBINED_PDF_FILEAREA,
            'filepath' => '/' . $combined_hash . '/',
            'userid' => null,
            'itemid' => $grade->id,
            'filename' => document_services::COMBINED_PDF_FILENAME,
        ];

        $fs = get_file_storage();

        $only_pdfs_and_images = true;
        foreach ($submission_files as $file) {
            if ($file->get_mimetype() == 'application/pdf' || $file->get_imageinfo()) {
                // ok
            } else {
                $only_pdfs_and_images = false;
                break;
            }
        }

        if (!$only_pdfs_and_images) {
            // check if the moodle conversion created a document
            $editpdf_combined_file = current($fs->get_area_files($filerecord['contextid'], 'assignfeedback_editpdf', $filerecord['filearea'], $filerecord['itemid'], 'itemid', false, false));
            if ($editpdf_combined_file) {
                return $editpdf_combined_file;
            }
        }

        $combined_file = current($fs->get_directory_files($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'], $filerecord['itemid'], $filerecord['filepath'], false, false));

        if ($combined_file) {
            // test delete
            // $combined_file->delete();
            return $combined_file;
        }

        // create
        $pdf = new \setasign\Fpdi\Fpdi();

        $combined_info = (object)[
            'files' => [],
        ];

        foreach ($submission_files as $file) {
            if ($file->get_mimetype() == 'application/pdf') {
                // set the source file
                $pagecount = $pdf->setSourceFile($file->get_content_file_handle());

                for ($i = 1; $i <= $pagecount; $i++) {
                    $tplidx = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tplidx);

                    // create page with the size of the template
                    $pdf->addPage($size['orientation'], $size);

                    // draw template over whole page
                    $pdf->useTemplate($tplidx, 0, 0, $size[0], $size[1]);
                }

                $combined_info->files[] = (object)[
                    'contenthash' => $file->get_contenthash(),
                    'pagecnt' => $pagecount,
                ];
            } elseif ($info = $file->get_imageinfo()) {
                // image
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
                // $pdf = new \FPDF($orientation, 'mm', array($width, $height));

                // add page and image to PDF
                $pdf->AddPage($orientation, array($width, $height));

                if (!$tmp_image = $file->copy_content_to_temp()) {
                    die("couldn't create tmp image");
                }

                // rename tmp image to include extension, fpdf needs the correct extension!
                $tmp_image_with_extension = $tmp_image . '.' . pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                rename($tmp_image, $tmp_image_with_extension);

                $pdf->Image($tmp_image_with_extension, 0, 0, $width, $height);

                // Delete temporary image file.
                // @unlink($tmp_image_with_extension);
                $combined_info->files[] = (object)[
                    'contenthash' => $file->get_contenthash(),
                    'pagecnt' => 1,
                ];
            } else {
                $a4_width = 210;
                $a4_height = 297;
                $pdf->AddPage('P', array($a4_width, $a4_height));

                $pdf->SetFont('Arial', '', 12);
                $pdf->Text(20, 20, $file->get_filename() . ' kann leider nicht in Pdf konvertiert werden');

                $combined_info->files[] = (object)[
                    'contenthash' => $file->get_contenthash(),
                    'pagecnt' => 1,
                ];
                // die('no image? (no image info)' . $file->get_mimetype());
            }
        }

        $pdf_output = $pdf->Output('', 'S');

        $fs->delete_area_files($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'], $filerecord['itemid']);
        $combined_file = $fs->create_file_from_string($filerecord, $pdf_output);

        // save combined info
        $fs->delete_area_files($filerecord['contextid'], $filerecord['component'], 'combinedfileinfo', $filerecord['itemid']);
        $combined_info->contenthash = $combined_file->get_contenthash();
        $filerecord['filearea'] = 'combinedfileinfo';
        $filerecord['filename'] = 'data.json';
        $fs->create_file_from_string($filerecord, json_encode($combined_info));

        return $combined_file;
    }
}
