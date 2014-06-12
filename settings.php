<?php
/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $PAGE;

if ($ADMIN->fulltree) {

    error_reporting(E_ALL); 
    ini_set("display_errors", 1); 

    require_once($CFG->dirroot . '/mod/colibriv2/locallib.php');
    $PAGE->requires->js_init_call('M.mod_colibriv2.init');

    $settings->add(new admin_setting_configtext('colibriv2_host', get_string('host', 'colibriv2'),
                       get_string('host_desc', 'colibriv2'), 'https://webconf-colibri.fccn.pt/api/xml', PARAM_URL));

    $settings->add(new admin_setting_configtext('colibriv2_admin_login', get_string('admin_login', 'colibriv2'),
                       get_string('admin_login_desc', 'colibriv2'), 'admin', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('colibriv2_admin_password', get_string('admin_password', 'colibriv2'),
                       get_string('admin_password_desc', 'colibriv2'), ''));

    $settings->add(new admin_setting_configtext('colibriv2_admin_prefix', get_string('admin_prefix', 'colibriv2'),
                       get_string('admin_prefix_desc', 'colibriv2'), 'prefix', PARAM_TEXT));

    $url = $CFG->wwwroot . '/mod/colibriv2/conntest.php';
    $url = htmlentities($url);
    $options = 'toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=700,height=300';
    $str = get_string('savebeforepresstestconnectionbutton', 'colibriv2') . '<center><input type="button" onclick="window.open(\''.$url.'\', \'\', \''.$options.'\');" value="'.
           get_string('testconnection', 'colibriv2') . '" /></center>';

    $settings->add(new admin_setting_heading('colibriv2_test', '', $str));

    $param = new stdClass();
    $param->image = $CFG->wwwroot.'/mod/colibriv2/pix/fct_fccn_logo.png';
    $param->url = 'http://www.fccn.pt';

    $settings->add(new admin_setting_heading('colibriv2_intro', '', get_string('settingblurb', 'colibriv2', $param)));
}

