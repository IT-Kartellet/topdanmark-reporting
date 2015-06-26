<?php
error_reporting(-1);
include('../../config.php');
include('locallib.php');
include('../../course/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require('filter_form.php');
require_once($CFG->libdir . '/completionlib.php');

global $CFG, $USER, $PAGE, $DB, $OUTPUT;

require_login();

// get_context_instance to be removed (?), so fixed with non-deprecated call
$context = context_system::instance(0);
//$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->requires->js('/local/reporting/jquery.min.js', true); // Load in head of page to prevent "$ is not a function errors"
$PAGE->requires->js('/local/reporting/toggle.js');
$PAGE->set_context($context);
$PAGE->set_url('/reporting');
$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('/reporting');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);
$PAGE->add_body_class("reporting");

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('title', 'local_reporting'), 1, 'title', 'reportingtitle');

echo $OUTPUT->box_start();

echo get_string('description', 'local_reporting') . "<br /><br />";

$mform_filter = new filter_form();
$mods = array();
$report = array();
$sort = "username";
$mode = "asc";

if (@isset($_GET['username'])) {
    $username = $_GET['username'];
}
if (@isset($_GET['sort'])) {
    $sort = $_GET['sort'];
}
if (@isset($_GET['mode'])) {
    $mode = $_GET['mode'];
}

//Get data from the URL if we have chosen to have the input sorted. Otherwise see if the form has been filled and finally add som defaults
if (@isset($SESSION->report_form)) {
    $form_data = unserialize($SESSION->report_form);

    foreach ($form_data as $module => $mvalue) {
        if ($mvalue == 1) {
            $mods[] = $module;
        }
    }
} else {
    if ($form_data = $mform_filter->get_data()) {
        $SESSION->report_form = serialize($form_data);
        foreach ($form_data as $module => $mvalue) {
            if ($mvalue == 1) {
                $mods[] = $module;
            }
        }
    } else {
        $mods = array('quiz', 'scorm', 'assignment');
    }
}

// Search variable
$search = '';
if (@isset($_GET['search'])) {
    $search = $_GET['search'];
}

// $mform_filter->display();

echo get_string('frontpage-description', 'local_reporting');

echo $OUTPUT->box_end();

$data = array();
$headers = array('Username', 'Firstname', 'Lastname', 'Department', 'Course', 'Status', 'lastaccess', 'Details');
$clean = array();

if (@$form_data->my_results == 1) {
    $users[] = $USER->username;
} elseif (@isset($username)) {
    $users[] = $username;
} else {
    $users = get_relevant_users($USER);
}

//Run though each of these users to get context per course
foreach ($users as $account) {

    //Get the account of the user, which is needed since we are using id from the user and tons of other stuff along the way.
    $account = $DB->get_record('user', array('username' => $account), '*', MUST_EXIST);
    $account->courses = array();

    //Get the users courses and run through each of these.
    //The user has to have an active enrolment in each of the courses
    $account_courses = enrol_get_users_courses($account->id, true);

    foreach ($account_courses as $course) {
        $key = $account->id . "_" . $course->id;
        $clean[$key] = array("username" => $account->username,
            "firstname" => $account->firstname,
            "lastname" => $account->lastname,
            "department" => $account->department,
            "course" => $course->shortname,
            "activities" => array(),
            "courseid" => $course->id,
            "userid" => $account->id,
            "shortname" => $course->shortname,
        );

        // Get course completion data
        $info = new completion_info($course);

        // Load criteria to display
        $completions = $info->get_completions($account->id);

        //Get the users completion status, and not just the one from the entire ourse
        //Returns array, which can be found in locallib.php
        $user_completion = get_user_course_completion($completions);


        //Get Last access for the user to be displayed in the table
        $last_record = $DB->get_record('user_lastaccess', array('userid' => $account->id, 'courseid' => $course->id));
        $last_access = ($last_record == false) ? 0 : $last_record->timeaccess;

        //Look of the course is being tracked or not.
        if (count($user_completion['tracked_activities']) == 0) {
            $course_status = "Not Tracked";
        } //If the course is tracked, we see if all activities has been completed.
        elseif (count($user_completion['tracked_activities']) == count($user_completion['activities'])) {
            $course_status = "Finished";
            //Since the course is being tracked, but not all activities has been completed we show the number of activities vs. the number that have been completed.
        } else {
            $course_status = count($user_completion['activities']) . " / " . count($user_completion['tracked_activities']);
        }
        $clean[$account->id . "_" . $course->id]['last_access'] = $last_access;
        $clean[$account->id . "_" . $course->id]['course_status'] = $course_status;
        $course->last_access = $last_access;
        $course->course_status = $course_status;
        $course->activities = array();

        //Get course activities so that we are able to go through them one by one.
        $course_activities = get_array_of_activities($course->id);

        foreach ($course_activities as $activity) {
            $status = " - ";

            $icon_src = "";

            //Get the correct icon for the module. We look in our new plugins, the old and finally shows a "not-found" icon if none of those exists.
            if (file_exists($CFG->dirroot . "/mod/" . $activity->mod . "/pix/icon.png")) {
                $icon_src = $CFG->wwwroot . "/mod/" . $activity->mod . "/pix/icon.png";
            } elseif ($icon_src == "" && file_exists($CFG->dirroot . "/theme/topdanmark/pix_old/pix_core/mod/" . $activity->mod . "/icon.gif")) {
                $icon_src = $CFG->wwwroot . "/theme/topdanmark/pix_old/pix_core/mod/" . $activity->mod . "/icon.gif";
            } elseif ($icon_src == "") {
                $icon_src = $CFG->wwwroot . "/theme/topdanmark/unknown.png";
            }
            if (file_exists($CFG->dirroot . "/mod/" . $activity->mod . "/report.php")) {
                $report = "<a href='$CFG->wwwroot/mod/$activity->mod/report.php?id=$activity->cm'>Report</a>";
            } else {
                $report = " - ";
            }

            //Set details, which should have a link to their own report
            if ($activity->mod == 'quiz') {
                $details = "<a href='$CFG->wwwroot/local/reporting/$activity->mod/report.php?id=$activity->cm'>Details</a>";
            } else {
                $details = " - ";
            }
            //Se if the activity has been completed, is tracked or is in progress
            if (@isset($user_completion['activities'][$activity->cm])) {
                $status = "Done";
            } elseif (@isset($user_completion['tracked_activities'][$activity->cm])) {
                $status = "In Progress";
            } else {
                $status = "Not Tracked";
            }
            //If the module supports grades, we will collect the grades and show them.
            if ($activity->mod == 'quiz' || $activity->mod == 'scorm' || $activity->mod == 'assign') {
                $grades = grade_get_grades($course->id, 'mod', $activity->mod, $activity->id, $account->id);

                if (!empty($grades->items[0]->grades)) {
                    $grade = reset($grades->items[0]->grades);
                    //var_dump($grade);
                    $score = $grade->str_long_grade;
                }

            } else {
                $score = " - ";
            }
            //Save activity within the course
            $activity->icon_src = $icon_src;
            $activity->activity_status = $status;
            $activity->score = $score;
            $activity->report = $report;
            $activity->details = $details;

            //Save the actuall activity
            $course->activities[$activity->id] = $activity;
            //$clean[$account->id."_".$course->id]['activities'][$activity->id] = $activity;
            $clean[$account->id . "_" . $course->id]['activities'][] = $activity;
        }
        $account->courses[$course->id] = $course;
    }
    $result[$account->id] = $account;
}

// Filter $clean for search term if it has a value
if (!empty($search)) {
    $clean_filtered = array();

    // Iterate through each element, and each property of each element, and see if any property matches the search term
    foreach ($clean as $c) {
        foreach ($c as $key => $value) {
            $match = false;
            if (!is_array($value)) {
                $match = ($value === $search);

                if ($match == true) {
                    $clean_filtered[] = $c;
                    break; // No need to iterate over the rest of the properties if one was matched
                }
            }
        }
    }

    // Replace the original array with the filtered array
    $clean = $clean_filtered;
}

$clean = orderBy($clean, $sort, $mode);

//echo $OUTPUT->box_start();

$outer_table_html = '';

$outer_table_html .= html_writer::start_tag('table', array('id' => 'top_course_view'));
$outer_table_html .= html_writer::start_tag('thead');
$outer_table_html .= html_writer::start_tag('tr');

echo $outer_table_html;

//echo "<table id='top_course_view'>";
//echo "<thead><tr>";

foreach ($headers as $header) {
    $header = strtolower($header);
    if (@isset($username) && $username != "") {
        $username = "&username=$username";
    } else {
        $username = "";
    }
    if ($sort == $header) {
        $link = "<a href='$CFG->wwwroot/local/reporting/index.php?sort=$header&mode=desc$username'>";
    } else {
        $link = "<a href='$CFG->wwwroot/local/reporting/index.php?sort=$header&mode=asc$username'>";
    }
    if ($header == "details") {
        echo "<th style='width: 13%'>" . get_string($header, 'local_reporting') . "</th>";
    } else {
        echo "<th style='width: 13%'>" . get_string($header, 'local_reporting') . " $link<img src='$CFG->wwwroot/local/reporting/sort_up.png' /></a></th>";
    }
}

$outer_table_html = '';

$outer_table_html .= html_writer::end_tag('tr');
$outer_table_html .= html_writer::end_tag('thead');
$outer_table_html .= html_writer::start_tag('tbody');

echo $outer_table_html;

//echo "</tr></thead>";
//echo "<tbody>";

//Make a Counter to control the color of each table row.
$counter = 0;

//Go through the array and print everything out!
foreach ($clean as $course) {
    //Color every second row by setting a class to either even or odd
    $class = ($counter & 1) ? "odd" : "even";
    $counter++;

    $course_html = '';

    //Now echo the hidden table, with all the course detail for the active user.
    $course_html .= html_writer::start_tag('tr', array('class' => $class));
    $course_html .= html_writer::start_tag('td', array('class' => 'username'));
    $course_html .= html_writer::tag('a', $course['username'], array('href' => $CFG->wwwroot . '/local/reporting/index.php?username="' . $course['username'] . '"'));
    $course_html .= html_writer::tag('td', $course['firstname'], array('class' => 'firstname'));
    $course_html .= html_writer::tag('td', $course['lastname'], array('class' => 'lastname'));
    $course_html .= html_writer::tag('td', $course['department'], array('class' => 'department'));
    $course_html .= html_writer::tag('td', $course['shortname'], array('class' => 'shortname'));
    $course_html .= html_writer::tag('td', $course['course_status'], array('class' => 'status'));
    $course_html .= html_writer::tag('td', date("d.m.Y", $course['last_access']), array('class' => 'last_access'));
    $course_html .= html_writer::start_tag('td', array('class' => 'details'));
    $course_html .= html_writer::tag('a', 'Show details', array('class' => 'toggle_' . $course['courseid'] . '-' . $course['userid'], 'onclick' => 'openRow(details_' . $course['courseid'] . '_' . $course['userid'] . "," . '"toggle_' . $course['courseid'] . '-' . $course['userid'] . '")'));
    $course_html .= html_writer::tag('a', 'Hide details', array('class' => 'toggle_' . $course['courseid'] . '-' . $course['userid'], 'onclick' => 'openRow(details_' . $course['courseid'] . '_' . $course['userid'] . "," . '"toggle_' . $course['courseid'] . '-' . $course['userid'] . '")', 'style' => 'display:none'));
    $course_html .= html_writer::end_tag('td');
    $course_html .= html_writer::end_tag('tr');
    $course_html .= html_writer::start_tag('tr', array('class' => $class));
    $course_html .= html_writer::tag('td', '', array('class' => 'empty'));
    $course_html .= html_writer::start_tag('td', array('colspan' => '7'));
    $course_html .= html_writer::start_tag('table', array('class' => 'course_details', 'id' => 'details_' . $course['courseid'] . '_' . $course['userid'], 'style' => 'width: 100%; display: none'));
    $course_html .= html_writer::start_tag('thead');
    $course_html .= html_writer::start_tag('tr');
    $course_html .= html_writer::tag('th', 'Ikon');
    $course_html .= html_writer::tag('th', 'Aktivitet');
    $course_html .= html_writer::tag('th', 'Name');
    $course_html .= html_writer::tag('th', 'Status');
    $course_html .= html_writer::tag('th', 'Score');
    $course_html .= html_writer::tag('th', 'Report');
    $course_html .= html_writer::tag('th', 'Details');
    $course_html .= html_writer::end_tag('tr');
    $course_html .= html_writer::end_tag('thead');
    $course_html .= html_writer::end_tag('tbody');

    echo $course_html;

    $detail_count = 0;
    foreach ($course['activities'] as $activity) {
        $details_html = '';

        $detail_class = ($detail_count & 1) ? "d_odd" : "d_even";
        $detail_count++;

        // Echo the details of the course
        $details_html .= html_writer::start_tag('tr', array('class' => $detail_class));
        $details_html .= html_writer::start_tag('td', array('class' => 'icon'));
        $details_html .= html_writer::img($activity->icon_src, 'details icon');
        $details_html .= html_writer::end_tag('td');
        $details_html .= html_writer::tag('td', $activity->mod, array('class' => 'mod'));
        $details_html .= html_writer::start_tag('td', array('class' => 'name'));
        $details_html .= html_writer::link($CFG->wwwroot . '/local/reporting/index.php?username=' . $course['username'], $course['username']);
        $details_html .= html_writer::end_tag('td');
        $details_html .= html_writer::tag('td', $activity->activity_status, array('class' => 'status'));
        $details_html .= html_writer::tag('td', $activity->score, array('class' => 'score'));
        $details_html .= html_writer::tag('td', $activity->report, array('class' => 'report'));
        $details_html .= html_writer::tag('td', $activity->details, array('class' => 'details'));
        $details_html .= html_writer::end_tag('tr');

        echo $details_html;
    }

    $course_html = '';

    $course_html .= html_writer::end_tag('tbody');
    $course_html .= html_writer::end_tag('table');
    $course_html .= html_writer::end_tag('td');
    $course_html .= html_writer::end_tag('tr');

    echo $course_html;

    //Now echo the hidden table, with all the course detail for the active user.
//    echo "<tr class='$class'>";
//    echo "<td class='username'><a href='$CFG->wwwroot/local/reporting/index.php?username=".$course['username']."'>".$course['username']."</a></td>";
//    echo "<td class='firstname'>".$course['firstname']."</td>";
//    echo "<td class='lastname'>".$course['lastname']."</td>";
//    echo "<td class='department'>".$course['department']."</td>";
//    echo "<td class='shortname'>".$course['shortname']."</td>";
//    echo "<td class='status'>".$course['course_status']."</td>";
//    echo "<td class='last_access'>".date("d.m.Y", $course['last_access'])."</td>";
//    echo "<td class='details'>";
//    echo "<a class='toggle_".$course['courseid']."-".$course['userid']."' onclick=openRow(details_".$course['courseid']."_".$course['userid'].",'toggle_".$course['courseid']."-".$course['userid']."')>Show Details</a>";
//    echo "<a class='toggle_".$course['courseid']."-".$course['userid']."' onclick=openRow(details_".$course['courseid']."_".$course['userid'].",'toggle_".$course['courseid']."-".$course['userid']."') style='display: none;'>Hide Details</a>";
//    echo "</td>";
//    echo "</tr>";
//    echo "<tr class='$class'>";
//    echo "<td class='empty'></td>";
//    echo "<td colspan='7'>";
//    echo "<table class='course_details' id='details_".$course['courseid']."_".$course['userid']."' style='width: 100%; display: none;'>";
//    echo "<thead><tr><th>Ikon</th><th>Aktivitet</th><th>Name</th><th>Status</th><th>Score</th><th>Report</th><th>Details</th></tr></thead>";
//    echo "<tbody>";
//
//    $detail_count = 0;
//    foreach($course['activities'] as $activity){
//        $detail_class = ($detail_count & 1)	? "d_odd" : "d_even";
//        $detail_count++;
//
//        //Echo the details of the course
//        echo "<tr class='$detail_class'>";
//        echo "<td class='icon'><img src='$activity->icon_src' /></td>";
//        echo "<td class='mod'>$activity->mod</td>";
//        echo "<td class='name'><a href='$CFG->wwwroot/mod/$activity->mod/view.php?id=$activity->cm'>$activity->name</a></td>";
//        echo "<td class='status'>$activity->activity_status</td>";
//        echo "<td class='score'>$activity->score</td>";
//        echo "<td class='report'>$activity->report</td>";
//        echo "<td class='details'>$activity->details</td>";
//        echo "</tr>";
//    }
//
//    echo "</tbody>";
//    echo "</table>";
//    echo "</td>";
//    echo "</tr>";
}

$outer_table_html = '';

$outer_table_html .= html_writer::end_tag('tbody');
$outer_table_html .= html_writer::end_tag('table');

echo $outer_table_html;

//echo "</tbody>";
//echo "</table>";
//echo $OUTPUT->box_end();
echo $OUTPUT->footer();
?>
