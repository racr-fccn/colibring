<?php
/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$capabilities = array(
    'mod/colibriv2:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ),

    'mod/colibriv2:meetingpresenter' => array(

        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'mod/colibriv2:meetingparticipant' => array(

        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'mod/colibriv2:meetinghost' => array(

        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

);