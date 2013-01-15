<?php

include('../../../config.php');
include('../../../course/lib.php');
require_once('../../../lib/datalib.php');
require_once("../locallib.php");
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$cmid = optional_param('id', 0, PARAM_INT); // course_module ID

global $CFG, $USER, $PAGE, $DB, $OUTPUT;

require_login();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->requires->js('/local/reporting/jquery.min.js');
$PAGE->set_context($context);
$PAGE->set_url('/reporting');
$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('/reporting');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);
$PAGE->add_body_class("reporting");
$PAGE->add_body_class("quiz");

echo $OUTPUT->header();
echo $OUTPUT->heading( "Quiz Report", 1, 'title', 'reportingtitle');
echo $OUTPUT->box_start();

$tester = $DB->get_record("user", array('username' => 'tester') );

// get usernames of subordinate employees
$relevant_usernames = get_relevant_users( $USER );

$users = array();
foreach($relevant_usernames as $uname){
  $users[] = $DB->get_record( "user" , array( "username" => $uname ) );
}

// get the quiz object
$cm = get_coursemodule_from_id("quiz",$cmid);
$quiz = $DB->get_record( "quiz" , array( "id" => $cm->instance ) );


// get questions answered by each user
$questions = array();
foreach($users as $user){
  $user_questions = $DB->get_records_sql("SELECT TOP 1 {question}.id, {user}.username, {question}.questiontext, {quiz_question_instances}.grade
											FROM 
												{user}, 
												{quiz_attempts}, 
												{quiz},
												{quiz_question_instances},
												{question}
											WHERE {quiz_attempts}.quiz = ".$quiz->id."
											AND {quiz}.id = {quiz_attempts}.quiz
											AND {user}.username = '".$user->username."'
											AND {user}.id = {quiz_attempts}.userid
											AND {quiz}.id = {quiz_question_instances}.quiz
											AND {quiz_question_instances}.question = {question}.id
											ORDER BY {quiz_attempts}.attempt DESC");
  $user->questions = $user_questions;
  
  foreach($user_questions as $question){
    // append answered questions to user record
    $questions[$question->id] = $question->questiontext;
  }
}

// Create scores table
$table = new html_table();
$table->head = array("");

// add a header field for each user
foreach($relevant_usernames as $username){
  $table->head[] = $username;
}

$table->head[] = "Question Mean Score";
$table->data = array();

foreach( $questions as $qid => $q ){
  //add result column for each user
  $user_scores = get_scores($qid, $users);
  // calculate mean score for question
  $qms =  question_mean_score($quiz->id, $qid);
  $question_mean = (float)round($qms,3);
  $score_count = array_sum( array_map( function($score){return ( is_numeric($score) ) ? 1 :0 ;} , $user_scores ) );
  $score_sum = array_sum( array_map( function($score){return ( is_numeric($score) ) ? $score :0 ;} , $user_scores ) );
  $table->data[] = array_merge( array($q) , $user_scores , array( $question_mean ) );
}
// insert last row ( user mean scores )
$table->data[] = array_merge( array("User Mean"), user_mean_scores($users) );

// output table
echo html_writer::table($table);
echo $OUTPUT->box_end();
echo $OUTPUT->footer();

/*
 *  Retrieves the users' scores for a given question
 */
function get_scores($qid,$users){
  $user_scores = array();
  foreach($users as $user){
    $user_scores[] = (array_key_exists($qid,$user->questions) ) ? round($user->questions[$qid]->grade, 3) : "-";
  }
  return $user_scores;
}

/*
 *  Calculate each user's mean score
 */
function user_mean_scores($users){
  $scores = array();
  foreach($users as $user){
    $score_count = 0;
    $score_sum = 0;
    foreach($user->questions as $question){
      $score_count = $score_count + 1;
      $score_sum += $question->grade; 
    }
    // calculate mean 
    $scores[] = ($score_count == 0) ? "-" : $score_sum / $score_count ;
  }
  return $scores;
}

/*
 *  Calculate mean score for this question
 */
function question_mean_score($quiz_id, $question_id){
  global $DB;
  $query=  " SELECT
              qst.question, AVG(grade) avg
              FROM
              {quiz_question_instances} qst
              WHERE
              qst.question = $question_id
              AND qst.quiz = $quiz_id
              GROUP BY
              qst.question";

  $result = $DB->get_record_sql($query);
  return $result->avg;
}

/*
 *  Get the id of the latest attempt made by a user in a quiz
 */
function latest_attempt($quiz_id, $username){
  global $DB;
  var_dump($quiz_id);
  $result = $DB->get_record_sql("SELECT MAX({question_states}.attempt)
                                          FROM {question}, {question_states}, {quiz_question_instances}, {user}, {quiz_attempts}
                                          WHERE {question}.id = {question_states}.question
                                          AND {question}.id = {quiz_question_instances}.question
                                          AND {quiz_question_instances}.quiz = {$quiz_id}
                                          AND {question_states}.event IN (3,6,9)
                                          AND {user}.username = '{$username}'
                                          AND {user}.id = {quiz_attempts}.userid
                                          AND {quiz_attempts}.uniqueid = {question_states}.attempt
                                          ");
										  
  return (int)$result->max;
}
?>
