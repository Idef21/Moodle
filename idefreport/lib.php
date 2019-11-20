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
 * This file contains functions used by the idefreport reports
 *
 * @package   report_idefreport
 * @copyright 2019 by IDEF
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_idefreport_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/idefreport:view', $context)) {
        $url = new moodle_url('/report/idefreport/index.php', array('id' => $course->id));
        $navigation->add(get_string('pluginname', 'report_idefreport'), $url, navigation_node::TYPE_SETTING, null, null,
        new pix_icon('i/report', ''));
    }
}


/**
 * Returns the total sum of dedication time of the user to the system
 * This function returns a estimated dedication time which is configurable by appreciated times between registered logs
 * This function has been developed & updated thereupon Blocks:Course dedication lib @author Aday Talavera
 * The query is only for 2.7 & upper ver
 *
 * @param int $u userid
 * @param optional int $c courseid to get access time in course otherwise returns access time in system
 * @param optional int $limit seconds between logs access otherwise 7200 seconds
 * @param optional int $f return in timestamp or human readable format
 * @param optional int $iss time in seconds to ignore session, otherwise 59 seconds
 * @return dedication time */

function report_idefreport_myusertime($u, $c = 0, $limit = 7200, $f = 1, $iss = 59) {
    global $DB;

    // Ignore sessions with a duration less than defined value in seconds.
    $ignoress = $iss;

    $userid = $u;
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

    $mintime = "100000000"; // From time access dated on 1973-03-03.
    $maxtime = time();  // Now server time.

    if ($c > 1) {
        $where = 'userid = :userid AND courseid = :courseid AND timecreated >= :mintime AND timecreated <= :maxtime';
    } else {
        $where = 'userid = :userid AND timecreated >= :mintime AND timecreated <= :maxtime';
    }

    $params = array(
    'userid' => $user->id,
    'courseid' => $c,
    'mintime' => $mintime,
    'maxtime' => $maxtime
    );
    // Query for 2.7 & upper, the selected table is logstore_standard_log despite of classic log.
    $logs = $DB->get_records_select('logstore_standard_log', $where, $params, 'timecreated ASC', 'id,timecreated,ip');
    $rows = array();
    if ($logs) {
        $previouslog = array_shift($logs);
        $previouslogtime = $previouslog->timecreated;

        $sessionstart = $previouslogtime;
        $ips = array($previouslog->ip => true);

        foreach ($logs as $log) {
            if (($log->timecreated - $previouslogtime) > $limit) {
                $dedication = $previouslogtime - $sessionstart;
                // Ignore sessions with a really short duration.
                if ($dedication > $ignoress) {
                    $rows[] = (object) array('start_date' => $sessionstart, 'dedicationtime' => $dedication);
                    $ips = array();
                }
                $sessionstart = $log->timecreated;
            }
            $previouslogtime = $log->timecreated;
            $ips[$log->ip] = true;
        }
        $dedication = $previouslogtime - $sessionstart;
        // Ignore sessions with a really short duration.
        if ($dedication > $ignoress) {
            $rows[] = (object) array('start_date' => $sessionstart, 'dedicationtime' => $dedication);
        }
    }

    $totaldedication = 0;
    $timededication = 0;

    foreach ($rows as $index => $row) {
        $totaldedication += $row->dedicationtime;
    }

    $timededication = 0;

    $timededication = report_idefreport_myformat_dedication($totaldedication);
    if ($f == 1) {
        return $timededication;
    } else {
        return $totaldedication;
    }

}

/**
 * Returns in human readable format time a specific number of seconds
 *
 * @param int $totalsecs seconds to get
 * @return time {hours mins secs}  */
function report_idefreport_myformat_dedication($totalsecs) {
    $totalsecs = abs($totalsecs);

    $str = new stdClass();
    $str->hour = get_string('hour');
    $str->hours = get_string('hours');
    $str->min = get_string('min');
    $str->mins = get_string('mins');
    $str->sec = get_string('sec');
    $str->secs = get_string('secs');

    $hours = floor($totalsecs / HOURSECS);
    $remainder = $totalsecs - ($hours * HOURSECS);
    $mins = floor($remainder / MINSECS);
    $secs = $remainder - ($mins * MINSECS);

    $ss = ($secs == 1) ? $str->sec : $str->secs;
    $sm = ($mins == 1) ? $str->min : $str->mins;
    $sh = ($hours == 1) ? $str->hour : $str->hours;

    $ohours = '';
    $omins = '';
    $osecs = '';

    if ($hours) {
        $ohours = $hours . ' ' . $sh;
    }
    if ($mins) {
        $omins = $mins . ' ' . $sm;
    }
    if ($secs) {
        $osecs = $secs . ' ' . $ss;
    }
    if ($hours) {
        return trim($ohours . ' ' . $omins);
    }
    if ($mins) {
        return trim($omins . ' ' . $osecs);
    }
    if ($secs) {
        return $osecs;
        return '-';
    }
}

/**
 * Returns if a student has connected in a specific number of days
 *
 * @param int $userid id of user
 * @param int $courseid id of course
 * @param int $days days to get
 * @return boolean  */
function report_idefreport_myalert_connection($userid, $courseid, $days) {
    global $CFG, $DB;
    $connected = false;
    // Seconds in one day.
    $seconds = 86400;
    $secondsdiff = $days * $seconds;

    $hoy = time();
    $fin = $hoy;
    $inicio = $hoy - $secondsdiff;
    $userssc = 0;
    $where = 'userid = :userid AND courseid = :courseid AND timeaccess BETWEEN :mintime AND :maxtime';
    $params = array(
        'userid' => $userid,
        'courseid' => $courseid,
        'mintime' => $inicio,
        'maxtime' => $fin
    );

    // Query for 2.7 & upper, the selected table is logstore_standard_log despite of classic log.
    $logs = $DB->get_records_select('user_lastaccess', $where, $params, 'timeaccess ASC', 'id,timeaccess');
    if ($logs) {
        $connected = true;
    }
    return $connected;
}

/**
 * Output completions modules
 *
 * @param int $userid id of user
 * @return array $modules modules of course (quiz, scorm, resource)
 * @return int number completions modules
 */
function report_idefreport_count_user_module ($userid, $modules) {
    global $CFG, $DB;
    $numcompletions = 0;
    foreach ($modules as $module) {
        $completion = $DB->get_field_sql("
        SELECT
            cmc.id
        FROM
            {course_modules_completion} cmc, {user} u
        WHERE
            u.id = cmc.userid AND u.id = ? AND u.suspended <> 1 AND coursemoduleid = ? AND completionstate <> 0",
            array($userid, $module->id));
        $cad = "SELECT id.cmc FROM {course_modules_completion} cmc, {user} u WHERE u.id = cmc.userid AND u.userid = '".$userid."'
        AND u.suspended <> 1 AND coursemoduleid = '".$module->id."' AND completionstate <> 0";
        if ($completion) {
            $numcompletions++;
        }
    }
    return $numcompletions;
}

/**
 * Output scoes track
 *
 * @param int $s id of sco
 * @param int $u id of user
 * @param int $e element of scoes_track (start_time, lesson_status,...)
 * @return array $mysst scoes_track of sco
 */
function report_idefreport_getmyscorm_scoes_track($s, $u, $e) {
    global $DB;
    $mysst = $DB->get_records_sql('SELECT * FROM {scorm_scoes_track}
                            WHERE scoid = ?
                            AND userid = ?
                            AND element = ?
                            ORDER BY ? DESC',
                            array($s, $u, $e, $o));

    return $mysst;
}

/**
 * Output attempts of quizs
 *
 * @param int $q id of quiz
 * @param int $u id of user
 * @return array $myqza quiz_attempts
 */
function report_idefreport_get_myquiz_attempts($q, $u) {
    global $DB;
    $myqza = $DB->get_record_sql('SELECT * FROM {quiz_attempts}
                                  WHERE quiz = ?
                                  AND userid = ?
                                  ORDER BY sumgrades DESC LIMIT 0,1',
                                  array($q, $u));

    return $myqza;

}

/**
 * Get if the mod is in section 0
 *
 * @param array $mysections course sections
 * @param int $cmid module id
 * @return true in case
 */
function report_idefreport_isinsection($sections, $cmid) {
    foreach ($sections as $key => $value) {
        if (($key == 0) && (in_array($cmid, $value))) {
            return true;
        } else {
            return false;
        }

    }
}

/**
 * Output the seslect course table
 *
 * @param string img to be displayed
 * @return html to be displayed
 */

function report_idefreport_selectcourse($mypix) {

    $mycourses = get_courses();
    $output = "";
    $table = new html_table();
    $table->head = array(get_string('course', 'report_idefreport'), get_string('shortname', 'report_idefreport'),
    get_string('visible', 'report_idefreport'));
    foreach ($mycourses as $mycourse) {
        if ($mycourse->id > 1) { // Courseid = 0 is System.
            $myurl = html_writer::link(
                    new moodle_url('/report/idefreport/idefreport.php', ['id' => $mycourse->id]),
                   $mycourse->fullname);
            $table->data[] = array( '<i class="fa fa-cog fa-spin fa-2x fa-fw"></i> '.$myurl, $mycourse->shortname,
            $mycourse->visible);
        }

    }

    $output = html_writer::start_tag('div', array('class' => 'myclass'));
    $output .= html_writer::table($table);
    $output .= html_writer::end_tag('div');

    $output .= \core\notification::info($mypix.' '.get_string('selectcourse', 'report_idefreport'));

    return $output;

}

/**
 * Output the report data
 *
 * @param array context
 * @param int id
 * @return html to be displayed
 */

function report_idefreport_coursereport($context, $id) {
    global $CFG, $DB;

    $mycourses = get_courses();
    $students = get_enrolled_users($context, $withcapability = '', $groupid = 0, $userfields = 'u.*',
    $orderby = 'id', $limitfrom = 0, $limitnum = 5000);
    $output = "";

    $days = 15;
    $colquizs = 0;
    $colscorms = 0;
    $colresources = 0;
    // Modules.
    $myq = get_coursemodules_in_course('quiz', $id);
    $mys = get_coursemodules_in_course('scorm', $id);
    $myr = get_coursemodules_in_course('resource', $id);

    $table = new html_table();
    $table->head = array('<i class="fa fa-user-circle-o" aria-hidden="true"></i>',
                         get_string('firstname'),
                         get_string('lastname'),
                         '<i class="fa fa-cog fa-spin fa-fw"></i> '.get_string('time'),
                         get_string('connected', 'report_idefreport', $days),
                         get_string('completionquizs', 'report_idefreport'),
                         get_string('completionresources', 'report_idefreport'),
                         get_string('completionscorms', 'report_idefreport'));
    foreach ($students as $mystudent) {
        $connection = report_idefreport_myusertime($mystudent->id, $id, $limit = 7200, $f = 1, $iss = 59);
        $connected = report_idefreport_myalert_connection($mystudent->id, $id, $days);
        if ($connected === true) {
            $conn = '<i class="fa fa-check" aria-hidden="true"></i>';
        } else {
            $conn = '<i class="fa fa-warning" aria-hidden="true"></i>';
        }
        $completionquizs = report_idefreport_count_user_module($mystudent->id, $myq);
        $colquizs = $completionquizs." / ".count($myq);
        $completionresources = report_idefreport_count_user_module($mystudent->id, $myr);
        $colresources = $completionresources." / ".count($myr);
        $completionscorms = report_idefreport_count_user_module($mystudent->id, $mys);
        $colscorms = $completionscorms." / ".count($mys);

        $myurlquiz = html_writer::link(
            new moodle_url('/report/idefreport/idefreport_module.php', ['id' => $id, 'userid' => $mystudent->id,
            'nmodule' => 'quiz']), $colquizs);
        $myurlscorm = html_writer::link(
            new moodle_url('/report/idefreport/idefreport_module.php', ['id' => $id, 'userid' => $mystudent->id,
            'nmodule' => 'scorm']), $colscorms);

        $table->data[] = array($mystudent->id,
        $mystudent->firstname,
        $mystudent->lastname,
        $connection,
        $conn,
        $myurlquiz,
        $colresources,
        $myurlscorm);
    }

    $output = html_writer::start_tag('div', array('class' => 'myclass'));
    $output .= html_writer::table($table);
    $output .= html_writer::end_tag('div');

    return $output;

}

/**
 * Output the report data
 *
 * @param array context
 * @param int id
 * @return html to be displayed
 */

function report_idefreport_modulereport($courseid, $userid, $nmodule) {
    global $CFG, $DB;

    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        print_error('invalidcourse');
    }

    if (!$user = $DB->get_record('user', array('id' => $userid))) {
        print_error('invaliduser');
    }

    if ($nmodule == 'quiz') {
        $modules = get_coursemodules_in_course('quiz', $course->id);
    } else if ($nmodule == 'scorm') {
        $modules = get_coursemodules_in_course('scorm', $course->id);
    } else {
        print_error('invalidmodule');
    }

    // We get the sections and the idmod.
    $modinfo = get_fast_modinfo($course);
    $mysections = $modinfo->get_sections();

    $table = new html_table();

    $myheadarray = array('<i class="fa fa-user-circle-o" aria-hidden="true"></i>',
    get_string('firstname'),
    get_string('lastname'));

    foreach ($modules as $module) { // Module.
        array_push($myheadarray, '<i class="fa fa-pencil-square-o" aria-hidden="true"></i> '.$module->name);
    }

    // Head of table.
    $myarray = array($user->id, $user->firstname, $user->lastname);
    $table->head = $myheadarray;
    if ($nmodule == 'scorm') {
        foreach ($modules as $module) { // Scorms logic.
            $myscoinfo = '';
            $myscoid = $DB->get_records('scorm_scoes', array('scorm' => $module->instance, 'scormtype' => 'sco'));
            foreach ($myscoid as $mcoid) {
                $myscoinfo .= '<strong>'.$mcoid->title.'</strong>';
            }

            $mysst = report_idefreport_getmyscorm_scoes_track($mcoid->id, $user->id, 'x.start.time');
            $mysls = report_idefreport_getmyscorm_scoes_track($mcoid->id, $user->id, 'cmi.core.lesson_status');
            $mystt = report_idefreport_getmyscorm_scoes_track($mcoid->id, $user->id, 'cmi.core.total_time');
            $myssr = report_idefreport_getmyscorm_scoes_track($mcoid->id, $user->id, 'cmi.core.score.raw');

            $myscoinfo .= '<ul>';
            foreach ($mysst as $msst) {
                if (isset($msst)) {
                    $myscoinfo .= '<li>'.get_string('firstaccess').' <span class="label label-info">'.userdate($msst->value).
                    '</li>';
                } else {
                    $myscoinfo .= '<li>'.get_string('firstaccess').' <span class="label label-warning">'.
                    get_string('noaccess', 'report_idefreport').'</li>';
                }
            }

            foreach ($mysls as $msls) {
                $myscoinfo .= '<li>'.get_string('resetstatus').' '.$msls->value.'</li>';
            }

            foreach ($mystt as $mstt) {
                $myscoinfo .= '<li>'.get_string('Time', 'report_idefreport').' '.$mstt->value.'</li>';
            }

            foreach ($myssr as $mssr) {
                $myscoinfo .= '<li>'.get_string('mark', 'core_question').' '.$mssr->value.'</li>';
            }
            $myscoinfo .= '</ul>';

            array_push($myarray, '<i class="fa fa-desktop" aria-hidden="true"></i> '.$myscoinfo);
            unset($myscoinfo);
        }
    } else if ($nmodule == 'quiz') {
        $nquiz = 0; // Number of quiz.
        $npquiz = 0; // Number of passed quiz.
        foreach ($modules as $module) { // Quiz logic.
            $nquiz++;
            $mqatinfo = '';
            $myqat = report_idefreport_get_myquiz_attempts($module->instance, $user->id);
            if (!isset($myqat->timefinish)) {
                $mqatinfo .= get_string('noaccess', 'report_idefreport');
            } else {
                $mqatinfo .= '<ul>';
                $mqatinfo .= '<li> '.get_string('access', 'report_idefreport').' '.userdate($myqat->timemodified).'</li>';
                $mqatinfo .= '<li> '.get_string('resetstatus').' '.$myqat->state.'</li>';
                $mqatinfo .= '<li> '.get_string('mark', 'core_question').' '.round($myqat->sumgrades, 1).'</li>';
                $mqatinfo .= '</ul>';
            }
            array_push($myarray, $mqatinfo);
            unset($maqtinfo);
        }
    }
    $table->data[] = $myarray;
    $output = html_writer::start_tag('div', array('class' => 'myclass'));
    $output .= html_writer::table($table);
    $output .= html_writer::end_tag('div');

    return $output;
}