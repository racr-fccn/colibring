<?php

/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// (Replace colibriv2 with the name of your module and remove this line)

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/connect_class.php');
require_once(dirname(__FILE__).'/connect_class_dom.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // colibriv2 instance ID
$groupid = optional_param('group', 0, PARAM_INT);

global $CFG, $USER, $DB, $PAGE, $OUTPUT, $SESSION;

if ($id) {
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

} else if ($a) {

    $cond = array('id' => $a);
    if (! $colibriv2 = $DB->get_record('colibriv2', $cond)) {
        error('Course module is incorrect');
    }

    $cond = array('id' => $colibriv2->course);
    if (! $course = $DB->get_record('course', $cond)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('colibriv2', $colibriv2->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

// Check for submitted data
if (($formdata = data_submitted($CFG->wwwroot . '/mod/colibriv2/view.php')) && confirm_sesskey()) {

    // Edit participants
    if (isset($formdata->participants)) {

        $cond = array('shortname' => 'colibriv2presenter');
        $roleid = $DB->get_field('role', 'id', $cond);

        if (!empty($roleid)) {
            redirect("participants.php?id=$id&contextid={$context->id}&roleid=$roleid&groupid={$formdata->group}", '', 0);
        } else {
            $message = get_string('nopresenterrole', 'colibriv2');
            $OUTPUT->notification($message);
        }
    }
}


// Check if the user's email is the Connect Pro user's login
$usrobj = new stdClass();
$usrobj = clone($USER);

$usrobj->username = _colibriv2_set_username($usrobj->username, $usrobj->email);

/// Print the page header
$url = new moodle_url('/mod/colibriv2/view.php', array('id' => $cm->id));

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($colibriv2->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$strcolibriv2s = get_string('modulenameplural', 'colibriv2');
$strcolibriv2  = get_string('modulename', 'colibriv2');

$params = array('instanceid' => $cm->instance);
$sql = "SELECT meetingscoid ". 
       "FROM {colibriv2_meeting_groups} amg ".
       "WHERE amg.instanceid = :instanceid ";

$meetscoids = $DB->get_records_sql($sql, $params);
$recording = array();

if (!empty($meetscoids)) {
    $recscoids = array();

    $_colibriv2 = _colibriv2_login();

    // Get the forced recordings folder sco-id
    // Get recordings that are based off of the meeting
    $fldid = _colibriv2_get_folder($_colibriv2, 'forced-archives');
    foreach($meetscoids as $scoid) {

        $data = _colibriv2_get_recordings($_colibriv2, $fldid, $scoid->meetingscoid);

        if (!empty($data)) {
          // Store recordings in an array to be moved to the Adobe shared folder later on
          $recscoids = array_merge($recscoids, array_keys($data));

        }

    }

    // Move the meetings to the shared content folder
    if (!empty($recscoids)) {
        $recscoids = array_flip($recscoids);

        if (_colibriv2_move_to_shared($_colibriv2, $recscoids)) {
            // do nothing
        }
    }

    //Get the shared content folder sco-id
    // Create a list of recordings moved to the shared content folder
    $fldid = _colibriv2_get_folder($_colibriv2, 'content');
    foreach($meetscoids as $scoid) {

        // May need this later on
        $data = _colibriv2_get_recordings($_colibriv2, $fldid, $scoid->meetingscoid);

        if (!empty($data)) {
            $recording[] = $data;
        }

        $data2 = _colibriv2_get_recordings($_colibriv2, $scoid->meetingscoid, $scoid->meetingscoid);

        if (!empty($data2)) {
             $recording[] = $data2;
        }

    }


    // Clean up any duplciated meeting recordings.  Duplicated meeting recordings happen when the
    // recording settings on ACP server change between "publishing recording links in meeting folders" and
    // not "publishing recording links in meeting folders"
    $names = array();
    foreach ($recording as $key => $recordingarray) {

        foreach ($recordingarray as $key2 => $record) {


            if (!empty($names)) {

                if (!array_search($record->name, $names)) {

                    $names[] = $record->name;
                } else {

                    unset($recording[$key][$key2]);
                }
            } else {

                $names[] = $record->name;
            }
        }
    }
    
    unset($names);


    // Check if the user exists and if not create the new user
    if (!($usrprincipal = _colibriv2_user_exists($_colibriv2, $usrobj))) {
        if (!($usrprincipal = _colibriv2_create_user($_colibriv2, $usrobj))) {
            // DEBUG
            debugging("error creating user", DEBUG_DEVELOPER);

//            print_object("error creating user");
//            print_object($_colibriv2->_xmlresponse);
            $validuser = false;
        }
    }

    // Check the user's capability and assign them view permissions to the recordings folder
    // if it's a public meeting give them permissions regardless
    if ($cm->groupmode) {


        if (has_capability('mod/colibriv2:meetingpresenter', $context, $usrobj->id) or
            has_capability('mod/colibriv2:meetingparticipant', $context, $usrobj->id)) {
            if (_colibriv2_assign_user_perm($_colibriv2, $usrprincipal, $fldid, COLIBRIV2_VIEW_ROLE)) {
                //DEBUG
                // echo 'true';
            } else {
                //DEBUG
                debugging("error assign user recording folder permissions", DEBUG_DEVELOPER);
//                print_object('error assign user recording folder permissions');
//                print_object($_colibriv2->_xmlrequest);
//                print_object($_colibriv2->_xmlresponse);
            }
        }
    } else {
        _colibriv2_assign_user_perm($_colibriv2, $usrprincipal, $fldid, COLIBRIV2_VIEW_ROLE);
    }

    _colibriv2_logout($_colibriv2);
}

// Log in the current user
$login = $usrobj->username;
$password  = $usrobj->username;
$https = false;

if (isset($CFG->colibriv2_https) and (!empty($CFG->colibriv2_https))) {
    $https = true;
}

$_colibriv2 = new connect_class_dom($CFG->colibriv2_host, $CFG->colibriv2_port,
                                  '', '', '', $https);

$_colibriv2->request_http_header_login(1, $login);
$adobesession = $_colibriv2->get_cookie();

// The batch of code below handles the display of Moodle groups
if ($cm->groupmode) {

    $querystring = array('id' => $cm->id);
    $url = new moodle_url('/mod/colibriv2/view.php', $querystring);

    // Retrieve a list of groups that the current user can see/manage
    $user_groups = groups_get_activity_allowed_groups($cm, $USER->id);

    if ($user_groups) {

        // Print groups selector drop down
        groups_print_activity_menu($cm, $url, false, true);


        // Retrieve the currently active group for the user's session
        $groupid = groups_get_activity_group($cm);

        /* Depending on the series of events groups_get_activity_group will 
         * return a groupid value of  0 even if the user belongs to a group.
         * If the groupid is set to 0 then use the first group that the user
         * belongs to.
         */
        $aag = has_capability('moodle/site:accessallgroups', $context);
        
        if (0 == $groupid) {
            $groups = groups_get_user_groups($cm->course, $USER->id);
            $groups = current($groups);

            if (!empty($groups)) {

                $groupid = key($SESSION->activegroup[$cm->course]);
            } elseif ($aag) {
                /* If the user does not explicitely belong to any group
                 * check their capabilities to see if they have access
                 * to manage all groups; and if so display the first course
                 * group by default
                 */
                $groupid = key($user_groups);
            }
        }
    }
}


$_colibriv2 = _colibriv2_login();

// Get the Meeting details
$cond = array('instanceid' => $colibriv2->id, 'groupid' => $groupid);
$scoid = $DB->get_field('colibriv2_meeting_groups', 'meetingscoid', $cond);

$meetfldscoid = _colibriv2_get_folder($_colibriv2, 'meetings');


$filter = array('filter-sco-id' => $scoid);

if (($meeting = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter))) {
    $meeting = current($meeting);
} else {

    /* First check if the module instance has a user associated with it
       if so, then check the user's adobe connect folder for existince of the meeting */
    if (!empty($colibriv2->userid)) {
        $username     = _colibriv2_get_connect_username($colibriv2->userid);
        $meetfldscoid = _colibriv2_get_user_folder_sco_id($_colibriv2, $username);
        $meeting      = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);
        
        if (!empty($meeting)) {
            $meeting = current($meeting);
        }
    }
    
    // If meeting does not exist then display an error message
    if (empty($meeting)) {

        $message = get_string('nomeeting', 'colibriv2');
        echo $OUTPUT->notification($message);
        _colibriv2_logout($_colibriv2);
        die();
    }
}

_colibriv2_logout($_colibriv2);

$sesskey = !empty($usrobj->sesskey) ? $usrobj->sesskey : '';

$renderer = $PAGE->get_renderer('mod_colibriv2');

$meetingdetail = new stdClass();
$meetingdetail->name = html_entity_decode($meeting->name);

// Determine if the Meeting URL is to appear
if (has_capability('mod/colibriv2:meetingpresenter', $context) or
    has_capability('mod/colibriv2:meetinghost', $context)) {

    // Include the port number only if it is a port other than 80
    $port = '';

    if (!empty($CFG->colibriv2_port) and (80 != $CFG->colibriv2_port)) {
        $port = ':' . $CFG->colibriv2_port;
    }

    $protocol = 'http://';

    if ($https) {
        $protocol = 'https://';
    }

    $url = $protocol . $CFG->colibriv2_meethost . $port
           . $meeting->url;

    $meetingdetail->url = $url;

    $meetingdetail->scoid = 

    $url = $protocol.$CFG->colibriv2_meethost.$port.'/admin/meeting/sco/info?principal-id='.
           $usrprincipal.'&sco-id='.$scoid.'&session='.$adobesession;

    // Get the server meeting details link
    $meetingdetail->servermeetinginfo = $url;

} else {
    $meetingdetail->url = '';
    $meetingdetail->servermeetinginfo = '';
}

// Determine if the user has the permissions to assign perticipants
$meetingdetail->participants = false;

if (has_capability('mod/colibriv2:meetingpresenter', $context, $usrobj->id) or
    has_capability('mod/colibriv2:meetinghost', $context, $usrobj->id)){

    $meetingdetail->participants = true;
}

//  CONTRIB-2929 - remove date format and let Moodle decide the format
// Get the meeting start time
$time = userdate($colibriv2->starttime);
$meetingdetail->starttime = $time;

// Get the meeting end time
$time = userdate($colibriv2->endtime);
$meetingdetail->endtime = $time;

// Get the meeting intro text
$meetingdetail->intro = $colibriv2->intro;
$meetingdetail->introformat = $colibriv2->introformat;

echo $OUTPUT->box_start('generalbox', 'meetingsummary');

// If groups mode is enabled for the activity and the user belongs to a group
if (NOGROUPS != $cm->groupmode && 0 != $groupid) {

    echo $renderer->display_meeting_detail($meetingdetail, $id, $groupid);
} elseif (NOGROUPS == $cm->groupmode) { 

    // If groups mode is disabled
    echo $renderer->display_meeting_detail($meetingdetail, $id, $groupid);
} else {

    // If groups mode is enabled but the user is not in a group
    echo $renderer->display_no_groups_message();
}

echo $OUTPUT->box_end();

echo '<br />';

$showrecordings = false;
// Check if meeting is private, if so check the user's capability.  If public show recorded meetings
if (!$colibriv2->meetingpublic) {

    // Check capabilities
    if (has_capability('mod/colibriv2:meetingpresenter', $context, $usrobj->id) or
        has_capability('mod/colibriv2:meetingparticipant', $context, $usrobj->id)) {
        $showrecordings = true;
    }
} else {
    
    // Check group mode and group membership
    $showrecordings = true;
}

// Lastly check group mode and group membership
if (NOGROUPS != $cm->groupmode && 0 != $groupid) {
    $showrecordings = $showrecordings && true;
} elseif (NOGROUPS == $cm->groupmode) {
    $showrecording = $showrecordings && true;
} else {
    $showrecording = $showrecordings && false;
}

$recordings = $recording;

if ($showrecordings and !empty($recordings)) {
    echo $OUTPUT->box_start('generalbox', 'meetingsummary');

    // Echo the rendered HTML to the page
    echo $renderer->display_meeting_recording($recordings, $cm->id, $groupid, $scoid);

    echo $OUTPUT->box_end();
}

add_to_log($course->id, 'colibriv2', 'view',
           "view.php?id=$cm->id", "View {$colibriv2->name} details", $cm->id);

/// Finish the page
echo $OUTPUT->footer();
