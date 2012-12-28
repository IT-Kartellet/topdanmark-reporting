<?php

//moodleform is defined in formslib.php
require_once("$CFG->libdir/formslib.php");
 
class filter_form extends moodleform {
    //Add elements to form
    function definition() {
        global $CFG, $DB;
 
		$mform =& $this->_form;
 
        $mform->addElement('header', 'general', get_string('edit', 'local_reporting'));
		
		$mods = $DB->get_records('modules', array('visible' => 1), 'name asc');
		
		//Liste over moduler, som er skjult i oversigten. 
		$hidden_modules = array('glossary', 'lesson', 'lti', 'imscp', 'data', 'chat', 'wiki', 'folder', 'label');
		
		foreach($mods as $mod){
			if(!in_array($mod->name, $hidden_modules)){
				$mform->addElement('advcheckbox', $mod->name, get_string('pluginname', $mod->name) ,'', array('group' => 1));
			}
		}
		
		$defaults = array('quiz', 'assignment', 'scorm');
		
		foreach($defaults as $mod_default){
				$mform->setDefault($mod_default, 1);
		}
		
		$this->add_checkbox_controller(1,"","");
		
		$mform->addElement('html', '<div class="qheader">');

		$mform->addElement('advcheckbox', 'my_results', 'Only see my own results' ,'', array('group' => 2));
		
		$this->add_action_buttons(false, 'Submit');
		
		return $mform;
	}
	//Custom validation should be added here
    function validation($data, $files) {
        return array();
    }	
}
?>
