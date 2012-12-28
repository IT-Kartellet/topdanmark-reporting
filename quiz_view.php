<?php
include('../../config.php');
include('locallib.php');
include('../../course/lib.php');
require_once($CFG->libdir.'/gradelib.php');
require('filter_form.php');
require_once($CFG->libdir.'/completionlib.php');

global $CFG, $USER, $PAGE, $DB, $OUTPUT;

require_login();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->requires->js('/local/reporting/jquery.min.js');
$PAGE->requires->js('/local/reporting/toggle.js');
$PAGE->set_context($context);
$PAGE->set_url('/reporting');
$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('/reporting');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);
$PAGE->add_body_class("reporting");

$q = "1844";
$cm = get_coursemodule_from_id('quiz', $q);

$quiz = $DB->get_record('quiz', array('id' => $cm->instance));

var_dump($quiz);
die();
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('title', 'local_reporting'), 1, 'title', 'reportingtitle');

echo $OUTPUT->box_start();

echo get_string('description', 'local_reporting')."<br /><br />";

$mform_filter = new filter_form();
$mods = array();
$report = array();
$sort = "username";
$mode = "asc";

if(@isset($_GET['username'])){
	$username = $_GET['username'];
}
if(@isset($_GET['sort'])){
	$sort = $_GET['sort'];
}
if(@isset($_GET['mode'])){
	$mode = $_GET['mode'];
}

echo $OUTPUT->box_end();

$data = array();
$headers = array();
$clean = array();

if(@$form_data->my_results == 1){
	$users[] = $USER->username;
}
elseif(@isset($username)){
	$users[] = $username;
}else{
	$users = get_relevant_users($USER);
}

$headers[] = "Spørgsmål";
//Run though each of these users to get context per course
foreach($users as $account){	
	//Get the account of the user, which is needed since we are using id from the user and tons of other stoff along the way. 
	$account = $DB->get_record('user', array('username' => $account), '*', MUST_EXIST);
	$headers[] = $account->username;
}

$clean = orderBy($clean, $sort, $mode);

//echo $OUTPUT->box_start();

echo "<table id='top_course_view'>";
echo "<thead><tr>";

foreach($headers as $header){
	$header = strtolower($header);
	echo "<th style='width: 13%'>".$header."</th>";
}
echo "<th style='width: 13%'>Gennemsnit per spørgsmål</th>";
echo "</tr></thead>";
echo "<tbody>";

//Make a Counter to control the color of each table row. 
$counter = 0;

/*
//Go through the array and print everything out!
foreach($clean as $course){
		//Color every second row by setting a class to either even or odd
		$class = ($counter & 1)	? "odd" : "even";
		$counter++;
		
		//Now echo the hidden table, with all the course detail for the active user. 
		echo "<tr class='$class'>";
			echo "<td class='username'><a href='$CFG->wwwroot/local/reporting/index.php?username=".$course['username']."'>".$course['username']."</a></td>";
			echo "<td class='firstname'>".$course['firstname']."</td>";
			echo "<td class='lastname'>".$course['lastname']."</td>";
			echo "<td class='department'>".$course['department']."</td>";
			echo "<td class='shortname'><a href='$CFG->wwwroot/course/view.php?id=".$course['courseid']."'>".$course['shortname']."</a></td>";
			echo "<td class='status'>".$course['course_status']."</td>";
			echo "<td class='last_access'>".date("d.m.Y", $course['last_access'])."</td>";
			echo "<td class='details'>";
			echo "<a class='toggle_".$course['courseid']."-".$course['userid']."' onclick=openRow(details_".$course['courseid']."_".$course['userid'].",'toggle_".$course['courseid']."-".$course['userid']."')>Show Details</a>";
			echo "<a class='toggle_".$course['courseid']."-".$course['userid']."' onclick=openRow(details_".$course['courseid']."_".$course['userid'].",'toggle_".$course['courseid']."-".$course['userid']."') style='display: none;'>Hide Details</a>";
			echo "</td>";
		echo "</tr>";
		echo "<tr class='$class'>";
			echo "<td class='empty'></td>";
			echo "<td colspan='7'>";
			echo "<table class='course_details' id='details_".$course['courseid']."_".$course['userid']."' style='width: 100%; display: none;'>";
			echo "<thead><tr><th>Ikon</th><th>Aktivitet</th><th>Name</th><th>Status</th><th>Score</th><th>Report</th><th>Details</th></tr></thead>";
		echo "<tbody>";
		
		$detail_count = 0;
		foreach($course['activities'] as $activity){
			$detail_class = ($detail_count & 1)	? "d_odd" : "d_even";
			$detail_count++;
			
			//Echo the details of the course
			echo "<tr class='$detail_class'>";
				echo "<td class='icon'><img src='$activity->icon_src' /></td>";
				echo "<td class='mod'>$activity->mod</td>";
				echo "<td class='name'><a href='$CFG->wwwroot/mod/$activity->mod/view.php?id=$activity->cm'>$activity->name</a></td>";
				echo "<td class='status'>$activity->activity_status</td>";
				echo "<td class='score'>$activity->score</td>";
				echo "<td class='report'>$activity->report</td>";								
				echo "<td class='details'>$activity->details</td>";
			echo "</tr>";
		}
		
		echo "</tbody>";
		echo "</table>";
		echo "</td>";			
		echo "</tr>";		
}
*/
echo "</tbody>";
echo "</table>";
//echo $OUTPUT->box_end();
echo $OUTPUT->footer();
?>
