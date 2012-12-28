<?php
error_reporting(-1);
include('../../config.php');
include('locallib.php');
include('../../course/lib.php');
require_once($CFG->libdir.'/gradelib.php');

global $CFG, $USER, $PAGE, $DB, $OUTPUT;

require_login();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_url('/reporting');
$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('/reporting');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();

echo $OUTPUT->heading("TEST");


$excluded = array('admin', 'guest');

$managers = array();

$organisation = get_top_org("tej", array(), false);

echo "<pre>";
var_dump($organisation);

echo $OUTPUT->footer();
?>
