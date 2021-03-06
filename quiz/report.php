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
?>
<input id="printbutton" type="button"
       onClick="window.print()"
       value="Print this report"/>
<?php

// get the quiz object
$cm = get_coursemodule_from_id("quiz",$cmid);
$quiz = $DB->get_record( "quiz" , array( "id" => $cm->instance ) );

$relevant_usernames = get_relevant_users($USER);


// The next ~20 lines might not be a good way to do it, but it works...
//single_select($url, $name, array $options, $selected = '', $nothing = array('' => 'choosedots')
$user_select = new single_select(new moodle_url($_SERVER['SCRIPT_NAME'],array('id'=>$cmid,'select_cate'=>isset($_GET['select_cate']) ? $_GET['select_cate'] : 'All')),'select_user',array_merge(array('All'=>'All'), $relevant_usernames));
echo "<br><br>";
echo get_string('choose', 'local_reporting')." ".get_string('user', 'local_reporting').":";
echo $OUTPUT->render($user_select);

if (isset($_GET['select_user']) && $_GET['select_user']!='All'){
    $relevant_usernames = array($_GET['select_user']);
}

$relevant_categories_sql = $DB->get_records_sql(
    "SELECT mdl_question.category FROM ("
    . "mdl_quiz_question_instances INNER JOIN mdl_question "
    . "ON mdl_quiz_question_instances.question=mdl_question.id) "
    . "WHERE mdl_quiz_question_instances.quiz=".$quiz->id." "
    . "GROUP BY category");

$category_names_sql = $DB->get_records_sql("SELECT id,name FROM mdl_question_categories");

// converting obj->obj->value to array of id=>value
$relevant_categories = array('All'=>'All');
foreach ($relevant_categories_sql as $qc){
    $relevant_categories[$qc->category] = $category_names_sql[$qc->category]->name;
}

$category_select = new single_select(new moodle_url($_SERVER['SCRIPT_NAME'],array('id'=>$cmid,'select_user'=>isset($_GET['select_user']) ? $_GET['select_user'] : 'All')),'select_cate',$relevant_categories);
echo get_string('choose', 'local_reporting')." ".get_string('category', 'local_reporting').":";
echo $OUTPUT->render($category_select);


echo $OUTPUT->box_start();

echo get_string('quiz-frontpage-description', 'local_reporting');

echo $OUTPUT->box_end();
echo $OUTPUT->box_start('generalbox', 'reportingbox');

$tester = $DB->get_record("user", array('username' => 'tester') );


$users = array();
foreach($relevant_usernames as $uname){
  $users[] = $DB->get_record( "user" , array( "username" => $uname ));
}




// get questions answered by each user
$questions = array();
foreach($users as $user){
    //Niclas
  /*$user_questions = $DB->get_records_sql("SELECT {question}.id, {user}.username, {question}.questiontext, {quiz_question_instances}.grade
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
											ORDER BY {quiz_attempts}.attempt DESC");*/
  
  //Get the id of the latest finished attempt on the quiz by the user
  //$sub_query_for_latest_attempt = "SELECT sub_attempt.id from {quiz_attempts} sub_attempt WHERE sub_attempt.userid = attempt.userid AND sub_attempt.quiz = attempt.quiz AND sub_attempt.state = 'finished' ORDER BY sub_attempt.attempt DESC LIMIT 1";

    //(fraction * mark as grade)
    //select * from mdl_question_attempt_steps where fraction is not null and userid = 2;
  /*$user_questions = $DB->get_records_sql("SELECT {question}.id, {user}.username, {question}.questiontext
											FROM
												{user},
												{quiz_attempts} attempt,
												{quiz},
												{quiz_question_instances},
												{question}
											WHERE attempt.quiz = ".$quiz->id."
											AND {quiz}.id = attempt.quiz
											AND attempt.state = 'finished'
											AND {user}.username = '".$user->username."'
											AND {user}.id = attempt.userid
											AND {quiz}.id = {quiz_question_instances}.quiz
											AND {quiz_question_instances}.question = {question}.id
											AND attempt.id = ($sub_query_for_latest_attempt)");*/

  /*
   * Selecting attributes regarding the latest graded attempt at a question from the quiz in question, where the user was the one who initiated the attempt.
   * The somewhat confusing check of user is because the question_attempt_steps table records the user responsible for each little step in the attempt ->
   * That is, the teacher userid will be listed for a manual grading. Thus we will need to check against the 'to do' step as this holds the original initiator of the attempt.
   */
  $questions_attempts = $DB->get_records_sql(
      "SELECT
                attempt_step.id as uniqueid, 
                {question}.id, 
                {user}.username, 
                {question}.questiontext, 
                attempt.maxmark, 
                attempt.minfraction, 
                attempt_step.fraction
        FROM
                {quiz},
                {user},
                {quiz_question_instances},
                {question},
                {question_attempts} attempt,
                {question_attempt_steps} attempt_step
        WHERE {quiz}.id = ".$quiz->id."
        AND {user}.username = '".$user->username."'
        AND {quiz}.id = {quiz_question_instances}.quiz
        AND {quiz_question_instances}.question = {question}.id
        AND attempt.questionid = {question}.id
        AND attempt_step.questionattemptid = attempt.id
        AND {user}.id = (SELECT userid from {question_attempt_steps} WHERE {question_attempt_steps}.questionattemptid = attempt_step.questionattemptid AND state = 'todo')
        AND attempt_step.fraction IS NOT NULL
        ORDER BY attempt_step.timecreated DESC");
//  AND {user}.id = (SELECT TOP 1 userid from {question_attempt_steps} WHERE {question_attempt_steps}.questionattemptid = attempt_step.questionattemptid AND state = 'todo')
	
  $user->questions = array();
  foreach($questions_attempts as $question){
      $questions[$question->id] = $question->questiontext;

      if(!array_key_exists($question->id, $user->questions)) { //if it was not already added (result sorted with newest attempt first)
          //set the grade of the question
          $fraction = ($question->fraction < $question->minfraction)? $question->minfraction :  $question->fraction;
          $question->grade = $question->maxmark * $fraction;
          unset($question->fraction);
          unset($question->minfraction);
          unset($question->maxmark);
          $user->questions[$question->id] = $question;
      }
  }
}

// Create scores table
$table = new html_table();
$table->head = array(get_string('question_category', 'local_reporting'));
$table->head[] = "";

// add a header field for each user
foreach($relevant_usernames as $username){
  $table->head[] = $username;
}

$table->head[] = "Question Mean Score";
//$table->head[] = "Question Category";
$table->data = array();

// sort questions according to the ordering that was given when the questions where created
$questionorder = explode(',', $quiz->questions);

$question_texts = $questions;
$question_ids = array_keys($questions);

// INSERT CATEGORY HERE

usort($question_ids, function ($one, $two) use ($questionorder) {
	return array_search($one, $questionorder) > array_search($two, $questionorder) ? 1 : -1;
});

$temp_sql_categorie = $DB->get_records_sql(
    "SELECT mdl_question.id,mdl_question_categories.name FROM "
    . "(mdl_question_categories INNER JOIN mdl_question "
    . "ON mdl_question_categories.id=mdl_question.category)");

// converting obj->obj->value to array of id=>value
$question_id_categories = array();
foreach ($temp_sql_categorie as $qic){
    $question_id_categories[$qic->id] = $qic->name;
}

$questionid_to_categoryid = $DB->get_records_sql("SELECT id,category FROM mdl_question;");

foreach( $question_ids as $qid ){
    if (isset($_GET['select_cate']) && $_GET['select_cate']!='All' && $_GET['select_cate']!=$questionid_to_categoryid[$qid]->category){
        continue;
    }
    
    $q = $question_texts[$qid];
    //add result column for each user
    $user_scores = get_scores($qid, $users);
    // calculate mean score for question
    $qms =  question_mean_score($quiz->id, $qid);

    //$question_mean = calculate_average($user_scores);//(float)round($qms,3);
    $score_count = array_sum( array_map( function($score){return ( is_numeric($score) ) ? 1 :0 ;} , $user_scores ) );
    $score_sum = array_sum( array_map( function($score){return ( is_numeric($score) ) ? $score :0 ;} , $user_scores ) );
    $question_mean = ($score_count == 0) ? "-" : round($score_sum / $score_count, 3) ;
    
    $table->data[] = array_merge(array($question_id_categories[$qid]), array($q) , $user_scores , array( $question_mean ));
}
// insert last row ( user mean scores )
list($user_scores, $total_score) = user_mean_scores($users);
$table->data[] = array_merge( array("","User Mean Percentage, individual"), $user_scores);
$table->data[] = array_merge( array("","User Mean Percentage, all"), array_fill(0, count($user_scores), ''), array(number_format($total_score, 2) . '%'));


$table_xls_data = " ";
foreach ($table->head as $header_name){
    $table_xls_data.=$header_name."\t";
}
$table_xls_data.="\n";
foreach ($table->data as $row){
    foreach ($row as $cell){
        $table_xls_data.=str_replace(',','',strip_tags($cell))."\t";
//        $table_xls_data.="hej\t";
    }
    $table_xls_data.="\n";
}

echo html_writer::link(new moodle_url('/local/reporting/outputxls.php', array('data'=>$table_xls_data)), get_string('export_to_excel', 'local_reporting'));

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

function get_average($arr) {
    $total = 0;
    $count = 0;
    foreach ($arr as $value) {
        if(is_nu)
        $total = $total + $value; // total value of array numbers
    }
    $average = ($total/$count); // get average value
    return $average;
}

/*
 *  Calculate each user's mean score
 */
function user_mean_scores($users){
  $scores = array();
  $total_score = 0;

  foreach($users as $user){
    $score_count = 0;
    $score_sum = 0;
    foreach($user->questions as $question){
      $score_count = $score_count + 1;
      $score_sum += $question->grade; 
    }
    // calculate mean 
    $scores[] = ($score_count == 0) ? "-" : round($score_sum / $score_count, 3) * 100 . '%';

    $total_score += ($score_count == 0) ? 0 : round($score_sum / $score_count, 3) * 100;
  }

  return array($scores, $total_score / count($scores));
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
