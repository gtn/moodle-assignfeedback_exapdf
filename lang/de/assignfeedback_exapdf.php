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
 * Strings for component 'assignfeedback_exapdf', language 'en'
 *
 * @package   assignfeedback_exapdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

$lang_file = $CFG->langlocalroot.'/de/assignfeedback_editpdf.php';
if (file_exists($lang_file)) {
    require $lang_file;
}

$string['exapdf'] = 'Anmerkungen im PDF (mit Dakora+)';
$string['exapdf_help'] = 'Annotate student submissions directly in the browser and produce an edited downloadable PDF.';
$string['enabled'] = 'Anmerkungen im PDF (mit Dakora+)';
$string['pluginname'] = 'Anmerkungen im PDF (mit Dakora+)';
