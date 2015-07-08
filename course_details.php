<?php
error_reporting(-1);
include('../../config.php');
include('locallib.php');
include('../../course/lib.php');
require_once($CFG->libdir . '/gradelib.php');

global $CFG, $USER, $PAGE, $DB, $OUTPUT;

require_login();

// get_context_instance to be removed (?), so fixed with non-deprecated call
$context = context_system::instance(0);
//$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->requires->js('/local/reporting/jquery.min.js', true); // Load in head of page to prevent "$ is not a function" errors
$PAGE->set_context($context);
$PAGE->set_url('/course_details');
$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('/course_details');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);
$PAGE->add_body_class("course_details");

echo $OUTPUT->header();

echo $OUTPUT->heading('Aktiviteter', 3, 'title', 'detailstitle');

// Get GET (pun intended)

$selected_user_id = '';
if (isset($_GET['userid'])) {
    $selected_user_id = $_GET['userid'];
}

$selected_course_id = '';
if (isset($_GET['courseid'])) {
    $selected_course_id = $_GET['courseid'];
}

// TODO: userid/courseid validation

// Find activities for this course
$course_activities = get_array_of_activities($selected_course_id);

// Define headers and calculate table column width
$headers = array('Ikon', 'Aktivitet', 'Navn', 'Status', 'Score', 'Rapport');
$width = floor(100 / count($headers));

echo $OUTPUT->box_start();

// Begin table HTML
$table_html = '';
$table_html .= html_writer::start_tag('table', array('id' => 'top_course_view'));
$table_html .= html_writer::start_tag('thead');
$table_html .= html_writer::start_tag('tr');

// Add the headers to the table
foreach ($headers as $header) {
    $table_html .= html_writer::tag('th', $header, array('style' => 'width: ' . $width . '%'));
}

$table_html .= html_writer::end_tag('tr');
$table_html .= html_writer::end_tag('thead');

$table_html .= html_writer::start_tag('tbody');

//var_dump($course_activities);
// Go through each activity and add it to the table body
$counter = 0;
foreach ($course_activities as $activity) {
    $id = $activity->cm;
    $icon_src = $CFG->wwwroot . "/mod/" . $activity->mod . "/pix/icon.png";
    $activity_type = $activity->mod;
    $activity_name = $activity->name;

    // Get course completion data
    // But first, let's fake the $course object so we don't have to waste CPU/IO time on retrieving all the unnecessary stuff from the DB
    $course = new stdClass();
    $course->id = $selected_course_id;
    $info = new completion_info($course);

    // Load criteria to display
    $completions = $info->get_completions($selected_user_id);

    //Get the users completion status, and not just the one from the entire ourse
    //Returns array, which can be found in locallib.php
    $user_completion = get_user_course_completion($completions);

    // See if the activity has been completed, is tracked, or is in progress
    if (@isset($user_completion['activities'][$id])) {
        $status = "Done";
    } elseif (@isset($user_completion['tracked_activities'][$id])) {
        $status = "In Progress";
    } else {
        $status = "Not Tracked";
    }

    // If the current user hasn't got permission to view reports, don't let them
    if(!has_capability('moodle/site:viewreports', $context)){
        $report_link = 'Ingen adgang';
    } else {
        // Determine which kind of report is needed, and check if it exists. If it exists, we link to it
        if (file_exists($CFG->dirroot . "/mod/" . $activity_type . "/report.php")) {
            $report_link = "<a href='$CFG->wwwroot/mod/$activity_type/report.php?id=$id'>Se rapport</a>";
        } else {
            $report_link = 'Ingen rapport';
        }
    }

    //If the module supports grades, get the grades so we can display them
    if ($activity_type == 'quiz' || $activity_type == 'scorm' || $activity_type == 'assign') {
        $grades = grade_get_grades($selected_course_id, 'mod', $activity_type, $activity->id, $selected_user_id);

        if (!empty($grades->items[0]->grades)) {
            $grade = reset($grades->items[0]->grades);
            $score = $grade->str_long_grade;

            if($score === '-') $score = 'Intet';
        }
    } else {
        $score = " - ";
    }

    $table_html .= html_writer::start_tag('tr', array("class" => ($counter & 1) ? "odd" : "even"));

    $table_html .= html_writer::tag('td', html_writer::img($icon_src, 'Aktivitetsikon'));
    $table_html .= html_writer::tag('td', ucfirst($activity_type));
    $table_html .= html_writer::tag('td', $activity_name);
    $table_html .= html_writer::tag('td', $status);
    $table_html .= html_writer::tag('td', $score);
    $table_html .= html_writer::tag('td', $report_link);

    $table_html .= html_writer::end_tag('tr');

    $counter++;
}

$table_html .= html_writer::end_tag('tbody');
$table_html .= html_writer::end_tag('table');

echo $table_html;

echo $OUTPUT->box_end();

echo $OUTPUT->footer();