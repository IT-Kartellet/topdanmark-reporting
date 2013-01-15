<?php

function local_reporting_extends_navigation(global_navigation $navigation) {
	$nodeHelp = $navigation->add('Rapportering', new moodle_url('/local/reporting/index.php'));

}

?>
