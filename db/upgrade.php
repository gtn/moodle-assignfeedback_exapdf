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
 * Upgrade code for the feedback_exapdf module.
 *
 * @package   assignfeedback_exapdf
 * @copyright 2013 Jerome Mouneyrac
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * exapdf upgrade code
 * @param int $oldversion
 * @return bool
 */
function xmldb_assignfeedback_exapdf_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023053100) {

        // Define table assignfeedback_exapdf_annot to be created.
        $table = new xmldb_table('assignfeedback_exapdf_annot');

        // Adding fields to table assignfeedback_exapdf_annot.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gradeid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('filecontenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, null, null);
        $table->add_field('annotations', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table assignfeedback_exapdf_annot.
        $table->add_key('id', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for assignfeedback_exapdf_annot.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Exapdf savepoint reached.
        upgrade_plugin_savepoint(true, 2023053100, 'assignfeedback', 'exapdf');
    }

    return true;
}
