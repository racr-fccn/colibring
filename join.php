<?php

/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/connect_class.php');
require_once(dirname(__FILE__).'/connect_class_dom.php');

$id       = required_param('id', PARAM_INT); // course_module ID, or
$groupid  = required_param('groupid', PARAM_INT);
$sesskey  = required_param('sesskey', PARAM_ALPHANUM);


global $CFG, $USER, $DB, $PAGE;

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

// Check if the user's email is the Connect Pro user's login
$usrobj = new stdClass();
$usrobj = clone($USER);
$usrobj->username = _colibriv2_set_username($usrobj->username, $usrobj->email);

$usrcanjoin = false;

$context   = get_context_instance(CONTEXT_MODULE, $cm->id);

// If separate groups is enabled, check if the user is a part of the selected group
if (NOGROUPS != $cm->groupmode) {

    $usrgroups = groups_get_user_groups($cm->course, $usrobj->id);
    $usrgroups = $usrgroups[0]; // Just want groups and not groupings

    $group_exists = false !== array_search($groupid, $usrgroups);
    $aag          = has_capability('moodle/site:accessallgroups', $context);

    if ($group_exists || $aag) {
        $usrcanjoin = true;
    }
} else {
    $usrcanjoin = true;
}

/// Set page global
$url = new moodle_url('/mod/colibriv2/view.php', array('id' => $cm->id));

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($colibriv2->name));
$PAGE->set_heading($course->fullname);

// user has to be in a group
if ($usrcanjoin and confirm_sesskey($sesskey)) {

    $usrprincipal = 0;
    $validuser    = true;

    // Get the meeting sco-id
    $param        = array('instanceid' => $cm->instance, 'groupid' => $groupid);
    $meetingscoid = $DB->get_field('colibriv2_meeting_groups', 'meetingscoid', $param);

    $_colibriv2 = _colibriv2_login();

    // Check if the meeting still exists in the shared folder of the Adobe server
    $meetfldscoid = _colibriv2_get_folder($_colibriv2, 'meetings');
    $filter       = array('filter-sco-id' => $meetingscoid);
    $meeting      = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);

    if (!empty($meeting)) {
        $meeting = current($meeting);
    } else {

        /* Check if the module instance has a user associated with it
           if so, then check the user's adobe connect folder for existince of the meeting */
        if (!empty($colibriv2->userid)) {
            $username     = _colibriv2_get_connect_username($colibriv2->userid);
            $meetfldscoid = _colibriv2_get_user_folder_sco_id($_colibriv2, $username);
            $meeting      = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);

            if (!empty($meeting)) {
                $meeting = current($meeting);
            }

        }
    }

    if (!($usrprincipal = _colibriv2_user_exists($_colibriv2, $usrobj))) {
        if (!($usrprincipal = _colibriv2_create_user($_colibriv2, $usrobj))) {
            // DEBUG
            print_object("error creating user");
            print_object($_colibriv2->_xmlresponse);
            $validuser = false;
        }
    }

    // Check the user's capabilities and assign them the Adobe Role
    if (!empty($meetingscoid) and !empty($usrprincipal) and !empty($meeting)) {
        if (has_capability('mod/colibriv2:meetinghost', $context, $usrobj->id, false)) {
            if (_colibriv2_check_user_perm($_colibriv2, $usrprincipal, $meetingscoid, COLIBRIV2_HOST, true)) {
                //DEBUG
//                 echo 'host';
//                 die();
            } else {
                //DEBUG
                print_object('error assign user adobe host role');
                print_object($_colibriv2->_xmlrequest);
                print_object($_colibriv2->_xmlresponse);
                $validuser = false;
            }
        } elseif (has_capability('mod/colibriv2:meetingpresenter', $context, $usrobj->id, false)) {
            if (_colibriv2_check_user_perm($_colibriv2, $usrprincipal, $meetingscoid, COLIBRIV2_PRESENTER, true)) {
                //DEBUG
//                 echo 'presenter';
//                 die();
            } else {
                //DEBUG
                print_object('error assign user adobe presenter role');
                print_object($_colibriv2->_xmlrequest);
                print_object($_colibriv2->_xmlresponse);
                $validuser = false;
            }
        } elseif (has_capability('mod/colibriv2:meetingparticipant', $context, $usrobj->id, false)) {
            if (_colibriv2_check_user_perm($_colibriv2, $usrprincipal, $meetingscoid, COLIBRIV2_PARTICIPANT, true)) {
                //DEBUG
//                 echo 'participant';
//                 die();
            } else {
                //DEBUG
                print_object('error assign user adobe particpant role');
                print_object($_colibriv2->_xmlrequest);
                print_object($_colibriv2->_xmlresponse);
                $validuser = false;
            }
        } else {
            // Check if meeting is public and allow them to join
            if ($colibriv2->meetingpublic) {
                // if for a public meeting the user does not not have either of presenter or participant capabilities then give
                // the user the participant role for the meeting
                _colibriv2_check_user_perm($_colibriv2, $usrprincipal, $meetingscoid, COLIBRIV2_PARTICIPANT, true);
                $validuser = true;
            } else {
                $validuser = false;
            }
        }
    } else {
        $validuser = false;
        notice(get_string('unableretrdetails', 'colibriv2'), $url);
    }

    _colibriv2_logout($_colibriv2);

    // User is either valid or invalid, if valid redirect user to the meeting url
    if (empty($validuser)) {
        notice(get_string('notparticipant', 'colibriv2'), $url);
    } else {

        $protocol = 'http://';
        $https = false;
        $login = $usrobj->username;

        if (isset($CFG->colibriv2_https) and (!empty($CFG->colibriv2_https))) {

            $protocol = 'https://';
            $https = true;
        }

        $_colibriv2 = new colibriv2_connect_class_dom($CFG->colibriv2_host, $CFG->colibriv2_port,
                                          '', '', '', $https);

        $_colibriv2->request_http_header_login(1, $login);

        // Include the port number only if it is a port other than 80
        $port = '';

        if (!empty($CFG->colibriv2_port) and (80 != $CFG->colibriv2_port)) {
            $port = ':' . $CFG->colibriv2_port;
        }

        add_to_log($course->id, 'colibriv2', 'join meeting',
                   "join.php?id=$cm->id&groupid=$groupid&sesskey=$sesskey",
                   "Joined $colibriv2->name meeting", $cm->id);

        redirect($protocol . $CFG->colibriv2_meethost . $port
                 . $meeting->url
                 . '?session=' . $_colibriv2->get_cookie());
    }
} else {
    notice(get_string('usergrouprequired', 'colibriv2'), $url);
}
