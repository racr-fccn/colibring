<?php
/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_colibriv2_uninstall() {
    global $DB;

    $result = true;

    $param = array('shortname' => 'colibriv2participant');
    if ($mrole = $DB->get_record('role', $param)) {
        $result = $result && delete_role($mrole->id);
    }

    $param = array('shortname' => 'colibriv2presenter');
    if ($mrole = $DB->get_record('role', $param)) {
        $result = $result && delete_role($mrole->id);
    }

    $param = array('shortname' => 'colibriv2host');
    if ($mrole = $DB->get_record('role', $param)) {
        $result = $result && delete_role($mrole->id);
    }

    return $result;
}