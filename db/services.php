<?php

defined('MOODLE_INTERNAL') || die;

$functions = array(
    'assignfeedback_exapdf_get_file_infos' => array(
        'classname'     => 'assignfeedback_exapdf\external',
        'methodname'    => 'get_file_infos',
        'classpath'   => '',
        'description'   => '',
        'type'          => 'read',
        'capabilities'  => 'mod/assign:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'assignfeedback_exapdf_save_annotations' => array(
        'classname'     => 'assignfeedback_exapdf\external',
        'methodname'    => 'save_annotations',
        'classpath'   => '',
        'description'   => '',
        'type'          => 'write',
        'capabilities'  => 'mod/assign:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'assignfeedback_exapdf_save_annotated' => array(
        'classname'     => 'assignfeedback_exapdf\external',
        'methodname'    => 'save_annotated',
        'classpath'   => '',
        'description'   => '',
        'type'          => 'write',
        'capabilities'  => 'mod/assign:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

