<?php

/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// Not sure if this page is needed anymore


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT);   // course

global $USER, $DB;

$params = array('id' => $id);
if (! $course = $DB->get_record('course', $params)) {
    error('Course ID is incorrect');
}

$PAGE->set_pagelayout('incourse');

add_to_log($course->id, 'colibriv2', 'view all', "index.php?id=$course->id", '');


/// Get all required strings

$strcolibriv2s   = get_string('modulenameplural', 'colibriv2');
$strcolibriv2    = get_string('modulename', 'colibriv2');
$strsectionname     = get_string('sectionname', 'format_'.$course->format);
$strname            = get_string('name');
$strintro           = get_string('moduleintro');


$PAGE->set_url('/mod/colibriv2/index.php', array('id' => $course->id));
$PAGE->set_title($course->shortname.': '.$strcolibriv2s);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strcolibriv2s);
echo $OUTPUT->header();

if (! $colibriv2s = get_all_instances_in_course('colibriv2', $course)) {
    notice(get_string('noinstances', 'colibriv2'), "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $sections = get_all_sections($course->id);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head  = array ($strsectionname, $strname, $strintro);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strlastmodified, $strname, $strintro);
    $table->align = array ('left', 'left', 'left');
}

foreach ($colibriv2s as $colibriv2) {
    $linkparams = array('id' => $colibriv2->coursemodule);
    $linkoptions = array();

    $modviewurl = new moodle_url('/mod/colibriv2/view.php', $linkparams);

    if (!$colibriv2->visible) {
        $linkoptions['class'] = 'dimmed';
    }

    $link = html_writer::link($modviewurl, format_string($colibriv2->name), $linkoptions);
    $intro = $colibriv2->intro;

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = array ($colibriv2->section, $link, $intro);
    } else {
        $table->data[] = array ($link, $intro);
    }
}

echo html_writer::table($table);

echo $OUTPUT->footer();