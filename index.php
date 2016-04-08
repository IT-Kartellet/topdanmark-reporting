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

$PAGE->requires->js('/local/reporting/jquery.min.js', true); // Load in head of page to prevent "$ is not a function" errors
$PAGE->requires->js('/local/reporting/script.js');
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

echo $CFG->local_reporting_header_text . "<br /><br />";

//echo get_string('frontpage-description', 'local_reporting');

echo $OUTPUT->box_end();

// Check if a user id was already selected. If user id is 0, something else was selected, in which case set an error message
$error = '';
$selected_user_id = '';
if (isset($_GET['userid'])) {
    $selected_user_id = $_GET['userid'];

    if ($selected_user_id < 0) {
        $error = 'Vælg venligst en gyldig bruger';
    }
}

echo $OUTPUT->box_start();
//$user_list = get_top_org($USER->username, array(), false);
$user_list = get_relevant_users($USER);

$is_manager = $number_of_users = count($user_list) > 1; // FIXME (better way to do this)
//$is_manager = true;

// Was "Alle" selected from the dropdown?
//$actual_user_list = array();
//if($selected_user_id == 0) {
//    $actual_user_list = array_map(create_function('$o', 'return $o->id;'), $user_list);
//    var_dump($actual_user_list);
//} else {
//    array_push($actual_user_list, $selected_user_id);
//}

// If user is a manager, generate a select list from which they can select a user to display (or all users)
if ($is_manager === true) {
    // Manger form variable for html_writer
    $manager_form = '';
    $manager_form .= html_writer::start_tag('form', array('action' => 'index.php', 'method' => 'GET'));
    echo $manager_form;

    // Get the relevant information for the select list, for each user
    $sql = "SELECT id, firstname, lastname, username FROM mdl_user WHERE username IN('" . implode("','", array_column($user_list, 'username')) . "')";
    $account_list = $DB->get_records_sql($sql);

    // Sort them according to need
    $account_list = sort_array_of_objects($account_list, 'firstname', 'asc');

    // Selectlist variable for html_writer
    $selectlist = '';

    $selectlist .= html_writer::start_tag('select', array('id' => 'select-user', 'name' => 'userid'));

    $selectlist .= html_writer::tag('option', 'Vælg medarbejder', array('value' => '-1'));
    //$selectlist .= html_writer::tag('option', 'Alle', array('value' => 'all'));
    $selectlist .= html_writer::tag('option', '-------------------------', array('value' => '-1', 'disabled' => ''));

    // Generate the selectlist options
    foreach ($account_list as $u) {
        $attributes = array();
        $attributes['value'] = $u->id;
        if ($u->id == $selected_user_id) $attributes['selected'] = ''; // Have the selected user be the selected option in the selectlist

        $selectlist .= html_writer::tag('option', $u->firstname . ' ' . $u->lastname, $attributes);
    }

    $selectlist .= html_writer::end_tag('select');

    echo $selectlist;

    $manager_form = '';
    $manager_form .= html_writer::tag('input', '', array('type' => 'submit', 'id' => 'submit-btn', 'disabled' => '', 'title' => 'Vælg først en medarbejder'));
    $manager_form .= html_writer::end_tag('form');
    echo $manager_form;
} else {
    // Otherwise, just set the user id variable to the current user's id
    $selected_user_id = $USER->id;
}

// If there was an error, display it
if ($error) {
    echo html_writer::tag('p', $error, array('class' => 'error-paragraph'));
}

// Generate the courses/activities table for the selected user (selected by manager or non-manager currently logged in)
if ($selected_user_id && $selected_user_id != 0 && !$error) {
    // Get the users courses and run through each of these. The user has to have an active enrolment in each of the courses
    $account_courses = enrol_get_users_courses($selected_user_id, true);

    // Get a little information about the user in question, so we have something nice to display
    $sql = "SELECT  firstname, lastname FROM mdl_user WHERE id=$selected_user_id";
    $user = $DB->get_record_sql($sql);

    // Get course activities so that we are able to go through them one by one.
    //$course_activities = get_array_of_activities($account_courses->id);

    echo html_writer::tag('p', 'Viser aktiviteter for <b>' . $user->firstname . ' ' . $user->lastname . '</b>', array('class' => 'view-activities'));

    // Begin variable for activities table HTML, using html_writer
    $activities_table_html = '';
    $activities_table_html .= html_writer::start_tag('table', array('id' => 'top_course_view'));
    $activities_table_html .= html_writer::start_tag('thead');
    $activities_table_html .= html_writer::start_tag('tr');

    // Table headers
    //$headers = array('Username', 'Firstname', 'Lastname', 'Department', 'Course', 'Status', 'lastaccess', 'Details'); //TODO: "alle" er valgt, ellers display ikke navne
    $headers = array('Course', 'Status', 'Last access', 'Details');
    $width = floor(100 / count($headers)); // Calculate width depending on how much stuff we show
    foreach ($headers as $header) {
        $activities_table_html .= html_writer::tag('th', $header, array('style' => 'width: ' . $width . '%'));
    }

    $activities_table_html .= html_writer::end_tag('tr');
    $activities_table_html .= html_writer::end_tag('thead');

    $activities_table_html .= html_writer::start_tag('tbody');

    // Build the table rows according to the data
    $counter = 0;
    foreach ($account_courses as $course) {
        $activities_table_html .= html_writer::start_tag('tr', array("class" => ($counter & 1) ? "odd" : "even"));
        // Get course completion data
        $info = new completion_info($course);

        // Load criteria to display
        $completions = $info->get_completions($selected_user_id);

        //Get the users completion status, and not just the one from the entire course
        $user_completion = get_user_course_completion($completions);

        $last_record = $DB->get_record('user_lastaccess', array('userid' => $selected_user_id, 'courseid' => $course->id));
        $last_access = ($last_record == false) ? 0 : $last_record->timeaccess;

        if (count($user_completion['tracked_activities']) == 0) {
            $course_status = "Not Tracked";
        } //If the course is tracked, we see if all activities has been completed
        elseif (count($user_completion['tracked_activities']) == count($user_completion['activities'])) {
            $course_status = "Finished";
            //Since the course is being tracked, but not all activities has been completed we show the number of activities vs. the number that have been completed
        } else {
            $course_status = count($user_completion['activities']) . " / " . count($user_completion['tracked_activities']);
        }

        $activities_table_html .= html_writer::tag('td', $course->fullname);
        $activities_table_html .= html_writer::tag('td', $course_status);
        $activities_table_html .= html_writer::tag('td', $last_access == 0 ? 'Aldrig' : date('Y-m-d H:i', $last_access));
        $activities_table_html .= html_writer::tag('td', html_writer::tag('a', 'Vis detaljer', array('href' => $CFG->wwwroot . '/local/reporting/course_details.php?&courseid=' . $course->id . '?&userid=' . $selected_user_id, 'target' => '_blank')));

        $activities_table_html .= html_writer::end_tag('tr');

        $counter++;
    }

    $activities_table_html .= html_writer::end_tag('tbody');
    $activities_table_html .= html_writer::end_tag('table');

    echo $activities_table_html;
}

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
?>
