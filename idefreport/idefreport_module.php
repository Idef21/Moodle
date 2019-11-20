<?php
// This file is part of Moodle - http://moodle.org/ortopantomografia
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * ireport report
 *
 * @package    report_idefreport
 * @copyright  2019 by Idef
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/report/idefreport/lib.php');

require_login();

$id = required_param('id', PARAM_INT); // Course ID.
$userid = required_param('userid', PARAM_INT); // User ID.
$nmodule = required_param('nmodule', PARAM_TEXT); // Module.

$PAGE->set_url('/report/idefreport/idefreport_module.php', array('id' => $id, 'userid' => $userid, 'nmodule' => $nmodule));
$PAGE->set_pagelayout('report');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_capability('report/idefreport:view', $context);

$stradministration = get_string('administration');
$strreports = get_string('reports');

$PAGE->set_title(get_string('pluginname', 'report_idefreport'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$mypix = "";
$mypix = "<img src=\"" . $OUTPUT->image_url('logo', 'report_idefreport') . "\" class=\"\" alt=\"\" />";

echo $OUTPUT->heading('<i class="fa fa-clock-o" aria-hidden="true"></i> '.userdate(time()).' '.$mypix.strtoupper($nmodule)." -  "
.$course->fullname);

echo report_idefreport_modulereport($course->id, $userid, $nmodule);
echo $OUTPUT->continue_button(new moodle_url('/report/idefreport/idefreport.php', array('id' => $course->id)));


echo $OUTPUT->footer();