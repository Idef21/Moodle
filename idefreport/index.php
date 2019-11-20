<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PstareportULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * idefreport report
 *
 * @package    report
 * @subpackage idefreport
 * @copyright  2019 by FFP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/report/idefreport/lib.php');

require_login();

$id = optional_param('id', 0, PARAM_INT);// Course ID.
$params['id'] = $id;

$url = new moodle_url("/report/idefreport/index.php", $params);

$PAGE->set_url('/report/idefreport/index.php', array('id' => $id));
$PAGE->set_pagelayout('report');

$context = context_system::instance();
require_capability('report/idefreport:view', $context);

$PAGE->set_context($context);
$stradministration = get_string('administration');
$strreports = get_string('reports');

$PAGE->set_title(get_string('pluginname', 'report_idefreport'));
$mypix = "";
$mypix = "<img src=\"" . $OUTPUT->image_url('logo', 'report_idefreport') . "\" class=\"\" alt=\"\" />";
$PAGE->set_heading(get_string('pluginname', 'report_idefreport'));

echo $OUTPUT->header();

echo $OUTPUT->heading(' <i class="fa fa-clock-o" aria-hidden="true"></i> '.userdate(time()));
echo report_idefreport_selectcourse($mypix);

echo $OUTPUT->footer();
