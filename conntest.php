<?php
/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
    //defined('MOODLE_INTERNAL') || die;

    require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
    require_once(dirname(__FILE__) . '/locallib.php');
    require_once(dirname(dirname(dirname(__FILE__))) . '/lib/accesslib.php');

    require_login(SITEID, false);

    global $USER, $CFG, $DB, $OUTPUT;

    $checkifempty = true; // Check for uninitialized variable

    $url = new moodle_url('/mod/colibriv2/conntest.php');
    $PAGE->set_url($url);

    $admins = explode(',', $CFG->siteadmins);

    if (false === array_search($USER->id, $admins)) {
        print_error('error1', 'colibriv2', $CFG->wwwroot);
    }

    $ac = new stdClass();

    $param = array('name' => 'colibriv2_admin_login');
    $ac->login      = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'colibriv2_admin_password');
    $ac->pass       = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'colibriv2_host');
    $ac->urlapi     = $DB->get_field('config', 'value', $param);

    $param = array('name' => 'colibriv2_admin_prefix');
    $ac->prefix     = $DB->get_field('config', 'value', $param);

    foreach ($ac as $propertyname => $propertyvalue) {
        // If this property is empty
        if ($checkifempty and empty($propertyvalue)) {
            print_error('error2', 'colibriv2', '', $propertyname);
            die();
        }
    }

    $strtitle = get_string('connectiontesttitle', 'colibriv2');

    $systemcontext = get_context_instance(CONTEXT_SYSTEM);
    $PAGE->set_context($systemcontext);
    $PAGE->set_title($strtitle);

    echo $OUTPUT->header();
    echo $OUTPUT->box_start('center');

    $param = new stdClass();
    $param->url = 'http://www.fccn.pt';
    print_string('conntestintro', 'colibriv2', $param);

    colibriv2_connection_test($ac->urlapi, $ac->login, $ac->pass, $ac->prefix);

    echo '<center>'. "\n";
    echo '<input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" />';
    echo '</center>';

    echo $OUTPUT->box_end();

    echo $OUTPUT->footer();

