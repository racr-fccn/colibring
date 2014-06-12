<?php
/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

set_time_limit(200);
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/colibriv2/locallib.php');

class mod_colibriv2_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE, $CFG;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
    /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('colibriv2name', 'colibriv2'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

    /// Adding the required "intro" field to hold the description of the instance
        $this->add_intro_editor(false, get_string('colibriv2intro', 'colibriv2'));

//        $mform->addElement('htmleditor', 'intro', get_string('colibriv2intro', 'colibriv2'));
//        $mform->setType('intro', PARAM_RAW);
//        $mform->addRule('intro', get_string('required'), 'required', null, 'client');
//        $mform->setHelpButton('intro', array('writing', 'richtext'), false, 'editorhelpbutton');

    /// Adding "introformat" field
//        $mform->addElement('format', 'introformat', get_string('format'));

//-------------------------------------------------------------------------------
    /// Adding the COLIBRI settings, spreeading all them into this fieldset
    /// or adding more fieldsets ('header' elements) if needed for better logic

    if ($CFG->colibri_mode) {

        $mform->addElement('header', 'colibrifieldset', get_string('colibrifieldset', 'colibriv2'));

        // Start and end date selectors
        $time       = time();
        $starttime  = usertime($time);
        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'colibriv2'));
        // Meeting Duration
        $durations = array();
        $durations = $this->get_durations();
        ksort($durations);
        $mform->addElement('select', 'duration', get_string('duration', 'colibriv2'), $durations);
        $mform->addElement('hidden', 'meeturl');

     } else {

//-------------------------------------------------------------------------------
    /// Adding the rest of colibriv2 settings, spreeading all them into this fieldset
    /// or adding more fieldsets ('header' elements) if needed for better logic

        $mform->addElement('header', 'colibriv2fieldset', get_string('colibriv2fieldset', 'colibriv2'));

        // Meeting URL
        $attributes=array('size'=>'20');
        $mform->addElement('text', 'meeturl', get_string('meeturl', 'colibriv2'), $attributes);
        $mform->setType('meeturl', PARAM_PATH);
        $mform->addHelpButton('meeturl', 'meeturl', 'colibriv2');
//        $mform->addHelpButton('meeturl', array('meeturl', get_string('meeturl', 'colibriv2'), 'colibriv2'));
        $mform->disabledIf('meeturl', 'tempenable', 'eq', 0);

        // Public or private meeting
        $meetingpublic = array(1 => get_string('public', 'colibriv2'), 0 => get_string('private', 'colibriv2'));
        $mform->addElement('select', 'meetingpublic', get_string('meetingtype', 'colibriv2'), $meetingpublic);
        $mform->addHelpButton('meetingpublic', 'meetingtype', 'colibriv2');
//        $mform->addHelpButton('meetingpublic', array('meetingtype', get_string('meetingtype', 'colibriv2'), 'colibriv2'));

        // Meeting Template
        $templates = array();
        $templates = $this->get_templates();
        ksort($templates);
        $mform->addElement('select', 'templatescoid', get_string('meettemplates', 'colibriv2'), $templates);
        $mform->addHelpButton('templatescoid', 'meettemplates', 'colibriv2');
//        $mform->addHelpButton('templatescoid', array('templatescoid', get_string('meettemplates', 'colibriv2'), 'colibriv2'));
        $mform->disabledIf('templatescoid', 'tempenable', 'eq', 0);


        $mform->addElement('hidden', 'tempenable');
        $mform->setType('type', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('type', PARAM_INT);

        // Start and end date selectors
        $time       = time();
        $starttime  = usertime($time);
        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'colibriv2'));
        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'colibriv2'));
        $mform->setDefault('endtime', strtotime('+2 hours'));

     }

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements(array('groups' => true));

        // Disabled the group mode if the meeting has already been created
        $mform->disabledIf('groupmode', 'tempenable', 'eq', 0);
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }

    function data_preprocessing(&$default_values) {
        global $CFG, $DB;

        if ($CFG->colibri_mode) {
          $default_values['meeturl'] = $CFG->colibriv2_admin_httpauth . "-" . md5(time());
        }
 
        if (array_key_exists('update', $default_values)) {

            $params = array('instanceid' => $default_values['id']);
            $sql = "SELECT id FROM {colibriv2_meeting_groups} WHERE ".
                   "instanceid = :instanceid";

            if ($DB->record_exists_sql($sql, $params)) {
                $default_values['tempenable'] = 0;
            }
        }
    }

    function validation($data, $files) {
        global $CFG, $DB, $USER, $COURSE;

        $errors = parent::validation($data, $files);

        $username     = _colibriv2_set_username($USER->username, $USER->email);
        $usr_fldscoid = '';
        $_colibriv2     = _colibriv2_login();

        // Search for a Meeting with the same starting name.  It will cause a duplicate
        // meeting name (and error) when the user begins to add participants to the meeting
        $meetfldscoid = _colibriv2_get_folder($_colibriv2, 'meetings');
        $filter = array('filter-name' => $data['name']);
        $namematches = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);        
        
        /// Search the user's adobe connect folder
        $usrfldscoid = _colibriv2_get_user_folder_sco_id($_colibriv2, $username);

	if (!empty($usrfldscoid)) {
        	$namematches = $namematches + _colibriv2_meeting_exists($_colibriv2, $usrfldscoid, $filter);
        }
        
        if (empty($namematches)) {
            $namematches = array();
        }

        if($CFG->colibri_mode) {
          $data['meeturl'] = $CFG->colibriv2_admin_httpauth . "-" . md5(time());
        }

        // Now search for existing meeting room URLs
        $url = $data['meeturl'];
        $url = $data['meeturl'] = colibriv2_clean_meet_url($data['meeturl']);

        // Check to see if there are any trailing slashes or additional parts to the url
        // ex. mymeeting/mysecondmeeting/  Only the 'mymeeting' part is valid
        if ((0 != substr_count($url, '/')) and (false !== strpos($url, '/', 1))) {
            $errors['meeturl'] = get_string('invalidadobemeeturl', 'colibriv2');
        }

        $filter = array('filter-url-path' => $url);
        $urlmatches = _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter);
        
        /// Search the user's adobe connect folder
        if (!empty($usrfldscoid)) {
            $urlmatches = $urlmatches + _colibriv2_meeting_exists($_colibriv2, $usrfldscoid, $filter);
        }

        if (empty($urlmatches)) {
            $urlmatches = array();
        } else {

            // format url for comparison
            if ((false === strpos($url, '/')) or (0 != strpos($url, '/'))) {
                $url = '/' . $url;
            }

        }

        // Check URL for correct length and format
        if (strlen($data['meeturl']) > 60) {
            $errors['meeturl'] = get_string('longurl', 'colibriv2');
        } elseif (empty($data['meeturl'])) {
            // Do nothing
        } elseif (!preg_match('/^[a-z][a-z\-]*/i', $data['meeturl'])) {
            $errors['meeturl'] = get_string('invalidurl', 'colibriv2');
        }

        // Check for available groups if groupmode is selected
        if ($data['groupmode'] > 0) {
            $crsgroups = groups_get_all_groups($COURSE->id);
            if (empty($crsgroups)) {
                $errors['groupmode'] = get_string('missingexpectedgroups', 'colibriv2');
            }
        }

        // Adding activity
        if (empty($data['update'])) {

            if ($CFG->colibri_mode) {
              $data['endtime'] = $data['starttime'] + $data['duration'] * 60;
            }

            if ($data['starttime'] == $data['endtime']) {
                $errors['starttime'] = get_string('samemeettime', 'colibriv2');
                $errors['endtime'] = get_string('samemeettime', 'colibriv2');
            } elseif ($data['endtime'] < $data['starttime']) {
                $errors['starttime'] = get_string('greaterstarttime', 'colibriv2');
            }

            // Check for local activities with the same name
            $params = array('name' => $data['name']);
            if ($DB->record_exists('colibriv2', $params)) {
                $errors['name'] = get_string('duplicatemeetingname', 'colibriv2');
                return $errors;
            }

            // Check Adobe connect server for duplicated names
            foreach($namematches as $matchkey => $match) {
                if (0 == substr_compare($match->name, $data['name'] . '_', 0, strlen($data['name'] . '_'), false)) {
                    $errors['name'] = get_string('duplicatemeetingname', 'colibriv2');
                }
            }

            foreach($urlmatches as $matchkey => $match) {
                $matchurl = rtrim($match->url, '/');
                if (0 == substr_compare($matchurl, $url . '_', 0, strlen($url . '_'), false)) {
                    $errors['meeturl'] = get_string('duplicateurl', 'colibriv2');
                }
            }

        } else {
            // Updating activity
            // Look for existing meeting names, excluding this activity's group meeting(s)
            $params = array('instanceid' => $data['instance']);
            $sql = "SELECT meetingscoid, groupid FROM {colibriv2_meeting_groups} ".
                   " WHERE instanceid = :instanceid";

            $grpmeetings = $DB->get_records_sql($sql, $params);

            if (empty($grpmeetings)) {
                $grpmeetings = array();
            }

            foreach($namematches as $matchkey => $match) {
                if (!array_key_exists($match->scoid, $grpmeetings)) {
                    if (0 == substr_compare($match->name, $data['name'] . '_', 0, strlen($data['name'] . '_'), false)) {
                        $errors['name'] = get_string('duplicatemeetingname', 'colibriv2');
                    }
                }
            }

            foreach($urlmatches as $matchkey => $match) {
                if (!array_key_exists($match->scoid, $grpmeetings)) {
                    if (0 == substr_compare($match->url, $url . '_', 0, strlen($url . '_'), false)) {
                        $errors['meeturl'] = get_string('duplicateurl', 'colibriv2');
                    }
                }
            }

            // Validate start and end times
            if ($data['starttime'] == $data['endtime']) {
                $errors['starttime'] = get_string('samemeettime', 'colibriv2');
                $errors['endtime'] = get_string('samemeettime', 'colibriv2');
            } elseif ($data['endtime'] < $data['starttime']) {
                $errors['starttime'] = get_string('greaterstarttime', 'colibriv2');
            }
        }

        _colibriv2_logout($_colibriv2);

        return $errors;
    }

    function get_templates() {
        $_colibriv2 = _colibriv2_login();

        $templates_meetings = _colibriv2_get_templates_meetings($_colibriv2);
        _colibriv2_logout($_colibriv2);
        return $templates_meetings;
    }

    function get_durations() {
    
        return array(
           60 => "1 Hour", 
          120 => "2 Hours",
          180 => "3 Hours",
          240 => "4 Hours",
        );

    }

}
