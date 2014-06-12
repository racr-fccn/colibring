<?php  // $Id: lib.php,v 1.9 2011/05/03 22:43:25 adelamarre Exp $
/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('locallib.php');

/**
 * Library of functions and constants for module colibriv2
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the colibriv2 specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */

$colibriv2_EXAMPLE_CONSTANT = 42;

/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
/** Include calendar/lib.php */
require_once($CFG->dirroot.'/calendar/lib.php');


/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function colibriv2_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $colibriv2 An object from the form in mod_form.php
 * @return int The id of the newly inserted colibriv2 record
 */
function colibriv2_add_instance($colibriv2) {
    global $COURSE, $USER, $DB, $CFG;

    if ($CFG->colibri_mode) {
      $colibriv2->endtime = $colibriv2->starttime + $colibriv2->duration * 60;
    }

    $colibriv2->timecreated  = time();
    $colibriv2->meeturl      = colibriv2_clean_meet_url($colibriv2->meeturl);
    $colibriv2->userid       = $USER->id;

    $return       = false;
    $meeting      = new stdClass();
    $username     = _colibriv2_set_username($USER->username, $USER->email);
    $meetfldscoid = '';

    // Assign the current user with the Adobe Presenter role
    $context = get_context_instance(CONTEXT_COURSE, $colibriv2->course);

    if (!has_capability('mod/colibriv2:meetinghost', $context, $USER->id, false)) {

        $param = array('shortname' => 'colibriv2host');
        $roleid = $DB->get_field('role', 'id', $param);

        if (role_assign($roleid, $USER->id, $context->id, 'mod_colibriv2')) {
            //DEBUG
        } else {
            debugging('role assignment failed', DEBUG_DEVELOPER);
            return false;
        }
    }

    $recid = $DB->insert_record('colibriv2', $colibriv2);

    if (empty($recid)) {
        debugging('creating colibriv2 module instance failed', DEBUG_DEVELOPER);
        return false;
    }
    
    $_colibriv2 = _colibriv2_login();
    
    // Get the user's meeting folder location, if non exists then get the shared
    // meeting folder location
    $meetfldscoid = _colibriv2_get_user_folder_sco_id($_colibriv2, $username);
    if (empty($meetfldscoid)) {
        $meetfldscoid = _colibriv2_get_folder($_colibriv2, 'meetings');
    }

    $meeting = clone $colibriv2;

    if (0 != $colibriv2->groupmode) { // Allow for multiple groups

        // get all groups for the course
        $crsgroups = groups_get_all_groups($COURSE->id);

        if (empty($crsgroups)) {
            return 0;
        }

        require_once(dirname(dirname(dirname(__FILE__))).'/group/lib.php');

        // Create the meeting for each group
        foreach($crsgroups as $crsgroup) {

            // The teacher role if they don't already have one and
            // Assign them to each group
            if (!groups_is_member($crsgroup->id, $USER->id)) {

                groups_add_member($crsgroup->id, $USER->id);

            }

            $meeting->name = $colibriv2->name . '_' . $crsgroup->name;

            if (!empty($colibriv2->meeturl)) {
                $meeting->meeturl = colibriv2_clean_meet_url($colibriv2->meeturl   . '_' . $crsgroup->name);
            }

            // If creating the meeting failed, then return false and revert the group role assignments
            if (!$meetingscoid = _colibriv2_create_meeting($_colibriv2, $meeting, $meetfldscoid)) {
                
                groups_remove_member($crsgroup->id, $USER->id);
                debugging('error creating meeting', DEBUG_DEVELOPER);
                return false;
            }

            // Update permissions for meeting
            if (empty($colibriv2->meetingpublic)) {
                _colibriv2_update_meeting_perm($_colibriv2, $meetingscoid, COLIBRIV2_MEETPERM_PRIVATE);
            } else {
                _colibriv2_update_meeting_perm($_colibriv2, $meetingscoid, COLIBRIV2_MEETPERM_PUBLIC);
            }


            // Insert record to activity instance in meeting_groups table
            $record = new stdClass;
            $record->instanceid = $recid;
            $record->meetingscoid = $meetingscoid;
            $record->groupid = $crsgroup->id;

            $record->id = $DB->insert_record('colibriv2_meeting_groups', $record);

            // Add event to calendar
            $event = new stdClass();

            $event->name = $meeting->name;
            $event->description = format_module_intro('colibriv2', $colibriv2, $colibriv2->coursemodule);
            $event->courseid = $colibriv2->course;
            $event->groupid = $crsgroup->id;
            $event->userid = 0;
            $event->instance = $recid;
            $event->eventtype = 'group';
            $event->timestart = $colibriv2->starttime;
            $event->timeduration = $colibriv2->endtime - $colibriv2->starttime;
            $event->visible = 1;
            $event->modulename = 'colibriv2';

            calendar_event::create($event);

        }

    } else { // no groups support
        $meetingscoid = _colibriv2_create_meeting($_colibriv2, $meeting, $meetfldscoid);
        
        // If creating the meeting failed, then return false and revert the group role assignments
        if (!$meetingscoid) {
            debugging('error creating meeting', DEBUG_DEVELOPER);
            return false;
        }
        
        // Update permissions for meeting
        if (empty($colibriv2->meetingpublic)) {
            _colibriv2_update_meeting_perm($_colibriv2, $meetingscoid, COLIBRIV2_MEETPERM_PRIVATE);
        } else {
            _colibriv2_update_meeting_perm($_colibriv2, $meetingscoid, COLIBRIV2_MEETPERM_PUBLIC);
        }

        // Insert record to activity instance in meeting_groups table
        $record = new stdClass;
        $record->instanceid = $recid;
        $record->meetingscoid = $meetingscoid;
        $record->groupid = 0;

        $record->id = $DB->insert_record('colibriv2_meeting_groups', $record);

        // Add event to calendar
        $event = new stdClass();

        $event->name = $meeting->name;
        $event->description = format_module_intro('colibriv2', $colibriv2, $colibriv2->coursemodule);
        $event->courseid = $colibriv2->course;
        $event->groupid = 0;
        $event->userid = 0;
        $event->instance = $recid;
        $event->eventtype = 'course';
        $event->timestart = $colibriv2->starttime;
        $event->timeduration = $colibriv2->endtime - $colibriv2->starttime;
        $event->visible = 1;
        $event->modulename = 'colibriv2';

        calendar_event::create($event);

    }

    // If no meeting URL was submitted,
    // update meeting URL for activity with server assigned URL
    if (empty($colibriv2->meeturl) and (0 == $colibriv2->groupmode)) {
        $filter = array('filter-sco-id' => $meetingscoid);
        $meeting = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);

        if (!empty($meeting)) {
            $meeting = current($meeting);

            $record = new stdClass();
            $record->id = $recid;
            $record->meeturl = trim($meeting->url, '/');
            $DB->update_record('colibriv2', $record);
        }
    }

    _colibriv2_logout($_colibriv2);

    return $recid;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $colibriv2 An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function colibriv2_update_instance($colibriv2) {
    global $DB, $USER;

    $colibriv2->timemodified = time();
    $colibriv2->id           = $colibriv2->instance;
    
    $meetfldscoid = '';

    $_colibriv2 = _colibriv2_login();

    $meetfldscoid = _colibriv2_get_folder($_colibriv2, 'meetings');

    // Look for meetings whose names are similar
    $filter = array('filter-name' => $colibriv2->name);

    $namematches = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);

    if (empty($namematches)) {
        $namematches = array();
    }

    // Find meeting URLs that are similar
    $url = $colibriv2->meeturl;
    $filter = array('filter-url-path' => $url);

    $urlmatches = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);

    if (empty($urlmatches)) {
            $urlmatches = array();
    } else {
        // format url for comparison
        if ((false === strpos($url, '/')) or (0 != strpos($url, '/'))) {
            $url = '/' . $url;
        }
    }

    $url = colibriv2_clean_meet_url($url);

    // Get all instances of the activity meetings
    $param = array('instanceid' => $colibriv2->instance);
    $grpmeetings = $DB->get_records('colibriv2_meeting_groups', $param);

    if (empty($grpmeetings)) {
        $grpmeetings = array();
    }


    // If no errors then check to see if the updated name and URL are actually different
    // If true, then update the meeting names and URLs now.
    $namechange = true;
    $urlchange = true;
    $timechange = true;

    // Look for meeting name change
    foreach($namematches as $matchkey => $match) {
        if (array_key_exists($match->scoid, $grpmeetings)) {
            if (0 == substr_compare($match->name, $colibriv2->name . '_', 0, strlen($colibriv2->name . '_'), false)) {
                // Break out of loop and change all referenced meetings
                $namechange = false;
                break;
            } elseif (date('c', $colibriv2->starttime) == $match->starttime) {
                $timechange = false;
                break;
            } elseif (date('c', $colibriv2->endtime) == $match->endtime) {
                $timechange = false;
                break;
            }
        }
    }

    // Look for URL change
    foreach($urlmatches as $matchkey => $match) {
        if (array_key_exists($match->scoid, $grpmeetings)) {
            if (0 == substr_compare($match->url, $url . '_', 0, strlen($url . '_'), false)) {
                // Break out of loop and change all referenced meetings
                $urlchange = false;
                break;
            } elseif (date('c', $colibriv2->starttime) == $match->starttime) {
                $timechange = false;
                break;
            } elseif (date('c', $colibriv2->endtime) == $match->endtime) {
                $timechange = false;
                break;
            }
        }
    }

    if ($timechange or $urlchange or $namechange) {
        $group = '';

        $meetingobj = new stdClass;
        foreach ($grpmeetings as $scoid => $grpmeeting) {

            if ($colibriv2->groupmode) {
                $group = groups_get_group($grpmeeting->groupid);
                $group = '_' . $group->name;
            } else {
                $group = '';
            }

            $meetingobj->scoid = $grpmeeting->meetingscoid;
            $meetingobj->name = $colibriv2->name . $group;
            // updating meeting URL using the API corrupts the meeting for some reason
            //  $meetingobj->meeturl = $data['meeturl'] . '_' . $group->name;
            $meetingobj->starttime = date('c', $colibriv2->starttime);
            $meetingobj->endtime = date('c', $colibriv2->endtime);
            
            /* if the userid is not empty then set the meeting folder sco id to 
               the user's connect folder.  If this line of code is not executed
               then user's meetings that were previously in the user's connect folder
               would be moved into the shared folder */
            if (!empty($colibriv2->userid)) {
                
                $username = _colibriv2_get_connect_username($colibriv2->userid);
                $user_folder = _colibriv2_get_user_folder_sco_id($_colibriv2, $username);
                
                if (!empty($user_folder)) {
                    $meetfldscoid = $user_folder;
                }

            }
            
            // Update each meeting instance
            if (!_colibriv2_update_meeting($_colibriv2, $meetingobj, $meetfldscoid)) {
                debugging('error updating meeting', DEBUG_DEVELOPER);
            }

            if (empty($colibriv2->meetingpublic)) {
                _colibriv2_update_meeting_perm($_colibriv2, $grpmeeting->meetingscoid, COLIBRIV2_MEETPERM_PRIVATE);
            } else {
                _colibriv2_update_meeting_perm($_colibriv2, $grpmeeting->meetingscoid, COLIBRIV2_MEETPERM_PUBLIC);
            }

            // Update calendar event
            $param = array('courseid' => $colibriv2->course, 'instance' =>
                           $colibriv2->id, 'groupid' => $grpmeeting->groupid,
                           'modulename' => 'colibriv2');

            $eventid = $DB->get_field('event', 'id', $param);

            if (!empty($eventid)) {

                $event = new stdClass();
                $event->id = $eventid;
                $event->name = $meetingobj->name;
                $event->description = format_module_intro('colibriv2', $colibriv2, $colibriv2->coursemodule);
                $event->courseid = $colibriv2->course;
                $event->groupid = $grpmeeting->groupid;
                $event->userid = 0;
                $event->instance = $colibriv2->id;
                $event->eventtype = 0 == $grpmeeting->groupid ? 'course' : 'group';
                $event->timestart = $colibriv2->starttime;
                $event->timeduration = $colibriv2->endtime - $colibriv2->starttime;
                $event->visible = 1;
                $event->modulename = 'colibriv2';

                $calendarevent = calendar_event::load($eventid);
                $calendarevent->update($event);
            }
        }
    }

    _colibriv2_logout($_colibriv2);

    return $DB->update_record('colibriv2', $colibriv2);
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function colibriv2_delete_instance($id) {
    global $DB;

    $param = array('id' => $id);
    if (! $colibriv2 = $DB->get_record('colibriv2', $param)) {
        return false;
    }

    $result = true;

    // Remove meeting from Adobe connect server
    $param = array('instanceid' => $colibriv2->id);
    $adbmeetings = $DB->get_records('colibriv2_meeting_groups', $param);

    if (!empty($adbmeetings)) {
        $_colibriv2 = _colibriv2_login();
        foreach ($adbmeetings as $meeting) {
            // Update calendar event
            $param = array('courseid' => $colibriv2->course, 'instance' => $colibriv2->id,
                           'groupid' => $meeting->groupid, 'modulename' => 'colibriv2');
            $eventid = $DB->get_field('event', 'id', $param);

            if (!empty($eventid)) {
                $event = calendar_event::load($eventid);
                $event->delete();
            }

            _colibriv2_remove_meeting($_colibriv2, $meeting->meetingscoid);
        }

        _colibriv2_logout($_colibriv2);
    }

    $param = array('id' => $colibriv2->id);
    $result &= $DB->delete_records('colibriv2', $param);

    $param = array('instanceid' => $colibriv2->id);
    $result &= $DB->delete_records('colibriv2_meeting_groups', $param);

    return $result;
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function colibriv2_user_outline($course, $user, $mod, $colibriv2) {
    return null;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function colibriv2_user_complete($course, $user, $mod, $colibriv2) {
    return true;
}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in colibriv2 activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function colibriv2_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}


/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function colibriv2_cron () {
    return true;
}


/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of colibriv2. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $colibriv2id ID of an instance of this module
 * @return mixed boolean/array of students
 */
function colibriv2_get_participants($colibriv2id) {
    return false;
}


/**
 * This function returns if a scale is being used by one colibriv2
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $colibriv2id ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function colibriv2_scale_used($colibriv2id, $scaleid) {
    return false;
}


/**
 * Checks if scale is being used by any instance of colibriv2.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any colibriv2
 */
function colibriv2_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Meeting URLs need to start with an alpha then be alphanumeric
 * or hyphen('-')
 *
 * @param string $meeturl Incoming URL
 * @return string cleaned URL
 */
function colibriv2_clean_meet_url($meeturl) {
    $meeturl = preg_replace ('/[^a-z0-9]/i', '-', $meeturl);
    return $meeturl;
}
