<?php // $Id: $

/**
 * The purpose of this file is to add a log entry when the user views a
 * recording
 *
 * @author  Your Name <adelamarre@remote-learner.net>
 * @version $Id: view.php,v 1.1.2.13 2011/05/09 21:41:28 adelamarre Exp $
 * @package mod/colibriv2
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/connect_class.php');
require_once(dirname(__FILE__).'/connect_class_dom.php');

$id         = required_param('id', PARAM_INT);
$groupid    = required_param('groupid', PARAM_INT);
$recscoid   = required_param('recording', PARAM_INT);

global $CFG, $USER, $DB;

// Do the usual Moodle setup
if (! $cm = get_coursemodule_from_id('colibriv2', $id)) {
    error('Course Module ID was incorrect');
}
$cond = array('id' => $cm->course);
if (! $course = $DB->get_record('course', $cond)) {
    error('Course is misconfigured');
}

$cond = array('id' => $cm->instance);
if (! $colibriv2 = $DB->get_record('colibriv2', $cond)) {
    error('Course module is incorrect');
}

require_login($course, true, $cm);

// ---------- //


// Get HTTPS setting
$https      = false;
$protocol   = 'http://';
if (isset($CFG->colibriv2_https) and (!empty($CFG->colibriv2_https))) {
    $https      = true;
    $protocol   = 'https://';
}

// Create a Connect Pro login session for this user
$usrobj = new stdClass();
$usrobj = clone($USER);
$login  = $usrobj->username = _colibriv2_set_username($usrobj->username, $usrobj->email);

$params = array('instanceid' => $cm->instance, 'groupid' => $groupid);
$sql = "SELECT meetingscoid FROM {colibriv2_meeting_groups} amg WHERE ".
       "amg.instanceid = :instanceid AND amg.groupid = :groupid";

$meetscoid = $DB->get_record_sql($sql, $params);

// Get the Meeting recording details
$_colibriv2   = _colibriv2_login();
$recording  = array();
$fldid      = _colibriv2_get_folder($_colibriv2, 'content');
$usrcanjoin = false;
$context    = get_context_instance(CONTEXT_MODULE, $cm->id);
$data       = _colibriv2_get_recordings($_colibriv2, $fldid, $meetscoid->meetingscoid);

/// Set page global
$url = new moodle_url('/mod/colibriv2/view.php', array('id' => $cm->id));

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($colibriv2->name));
$PAGE->set_heading($course->fullname);

if (!empty($data) && array_key_exists($recscoid, $data)) {

    $recording = $data[$recscoid];
} else {

    // If at first you don't succeed ...
    $data2 = _colibriv2_get_recordings($_colibriv2, $meetscoid->meetingscoid, $meetscoid->meetingscoid);

    if (!empty($data2) && array_key_exists($recscoid, $data2)) {
        $recording = $data2[$recscoid];
    }
}

_colibriv2_logout($_colibriv2);

if (empty($recording) and confirm_sesskey()) {
    notify(get_string('errormeeting', 'colibriv2'));
    die();
}

// If separate groups is enabled, check if the user is a part of the selected group
if (NOGROUPS != $cm->groupmode) {
    $usrgroups = groups_get_user_groups($cm->course, $USER
    ->id);
    $usrgroups = $usrgroups[0]; // Just want groups and not groupings

    $group_exists = false !== array_search($groupid, $usrgroups);
    $aag          = has_capability('moodle/site:accessallgroups', $context);

    if ($group_exists || $aag) {
        $usrcanjoin = true;
    }
} else {
    $usrcanjoin = true;
}


if (!$usrcanjoin) {
    notice(get_string('usergrouprequired', 'colibriv2'), $url);
}

add_to_log($course->id, 'colibriv2', 'view',
           "view.php?id=$cm->id", "View recording {$colibriv2->name} details", $cm->id);

// Include the port number only if it is a port other than 80
$port = '';

if (!empty($CFG->colibriv2_port) and (80 != $CFG->colibriv2_port)) {
    $port = ':' . $CFG->colibriv2_port;
}

$_colibriv2 = new connect_class_dom($CFG->colibriv2_host, $CFG->colibriv2_port,
                                  '', '', '', $https);

$_colibriv2->request_http_header_login(1, $login);
$adobesession = $_colibriv2->get_cookie();

redirect($protocol . $CFG->colibriv2_meethost . $port
                     . $recording->url . '?session=' . $_colibriv2->get_cookie());
