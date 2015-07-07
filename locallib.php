<?php


//Returns all relevant users to be shown in a report
function get_relevant_users($user){
	$users = array();

    if($user->username == 'admin'){
        global $DB;
        foreach($DB->get_records('user') as $uobj) {
            $users[$uobj->username] = array('id' => $uobj->id, 'username' => $uobj->username);
        }
    }

//    if($user->username == 'xag'){
//        $users = get_top_org('kaj', array(), false);
//    }
//    else{
//        $users = get_top_org($user->username, array(), false);
//    }
    return $users;
}

/**
 * Sorts an array of objects by comparing the desired field. Ascending or descending direction.
 *
 * Return the sorted array
 *
 * @param array $params array of items to sort
 * @param string $field which field to sort by
 * @param string $mode 'asc' or 'desc' (optional, default is 'asc')
 * @return the $data parameter, sorted
 */
function sort_array_of_objects($data, $field, $mode = 'asc') {
    $func = function($a, $b) use ($field) {
        return strcmp($a->$field, $b->$field);
    };

    usort($data, $func);

    return ($mode === 'asc') ? $data : array_reverse($data);
}

function orderBy($data, $field, $mode){
	if($field == "lastaccess"){
		$field = "last_access";
	}
	if($field == "status"){
		$field = "course_status";
	}
	if($mode == "asc"){
		$code = "return strnatcmp(\$a['$field'], \$b['$field']);";
		uasort($data, create_function('$a, $b', $code));
	}else{
		$code = "return strnatcmp(\$b['$field'], \$a['$field']);";
		uasort($data, create_function('$a, $b', $code));
	}
	return $data;
}

// Recursive function, which returns the managers for a particular user. 
// The function gets the manager field from user_info_data and puts this information into an array
// This continues until the top is reach, which is indicated by an empty field. 
function get_top_managers($username, $account, $layer, $managers){
	global $DB;

	// Get the manager information if any exists. If nothing exists return the result.
	if($manager = $DB->get_record('user_info_data', array('fieldid' => 2, 'userid' => (String)$account->id))){
		//If we are at the top our field will be empty, otherwise continue to populate the array.
		if($manager->data != ""){
			$managers[$username][] = $manager->data;
			$manager_account = $DB->get_record('user', array('username' => (String)$manager->data));
			//Return the result so far and continue the recursive function.
			return get_top_managers($username, $manager_account, $layer + 1, $managers);
		}else{
			return $managers;
		}
	}
	else{
		return $managers;
	}
}

//Recursive function, which generates two different arrays. 
//The first array is an assoc-array, which displays the organisation seen from a specified user ($username). 
//The function runs through the user_info_data to find that persons employees, which will be done for each of the employees as well. 
//The second view is a flat array, that contains all the employees underneeth a specified user ($username).
//$username = string, $organisation = array, $structure = boolean. 
//Structure defines which os the two arrays should be created. If scructure == false the flat array will be returned. 
function get_top_org($username, $organisation, $structure=true){
	global $DB;

	//Get all employees for a specified user
	$employees = $DB->get_records_select('user_info_data', "fieldid = 2 AND data LIKE '$username'");

	//Check if the user has any employees
	//If the user has employees continue to the
	if(count($employees) != 0){
		//Define an array to used within the foreach. It collects the recursive result and resturns it after the the loop is finished.
		$return_array = array();
		foreach($employees as $emp){
			//Get the user account instead of only having the user id.
			if($emp = $DB->get_record('user', array('id' => $emp->userid))){

				//If we need a structured array we use get_top_managers and run through that array.
				if($structure){
					$managers = array();
					$last = "";

					$managers = get_top_managers($emp->username, $emp, 0, $managers);

					$managers = $managers[$emp->username];

					//Create the array structure by running through the managers in the row they appear.
					//The managers will be from bottom to top in the organisational diagram.
					foreach($managers as $manager){
						$merger = array();
						if($last != ""){
							$merger[$manager] = $last;
							$last = $merger;
						}
					}
					//Merge the array and call the function again to get the next level.
					$organisation = array_merge_recursive($organisation, $merger);
					$return_array = array_merge_recursive($return_array, (array) get_top_org($emp->username, $organisation, $structure));

				//If  we do not need a structured array.
				}else{
					$organisation[$emp->username] = $emp->username;
					$return_array = array_merge($return_array, (array) get_top_org($emp->username, $organisation, $structure));
				}
			}
		}
		//Return the array with all the managers and employees.
		return $return_array;
	}else{
		//Get the user record of the employee, who do not have any employees
		if($non_manager = $DB->get_record('user', array('username' => $username))){

			//If we need a structured array we use get_top_managers and run through that array.
			if($structure){
				$managers = array();
				$last = "";

				$managers = get_top_managers($non_manager->username, $non_manager, 0, $managers);

				$managers = $managers[$non_manager->username];

				//Create the array structure by running through the managers in the row they appear.
				//The managers will be from bottom to top in the organisational diagram.
				foreach($managers as $manager){
					$merger = array();
					if($last != ""){
						$merger[$manager] = $last;
						$last = $merger;
					}else{
						$last = array($manager => $non_manager->username);
					}
				}
				//Merge the array and prepare it to be returned for further use.
				$organisation = array_merge_recursive($organisation, $merger);
			}else{
				$organisation[$non_manager->username] = $non_manager->username;
			}
			return $organisation;
		}
	}
	return $organisation;
}

function get_user_course_completion($completions){
	// For aggregating activity completion
	$activities = array();
	$activities_complete = 0;

	$activities_tacked = array();

	// For aggregating course prerequisites
	$prerequisites = array();
	$prerequisites_complete = 0;

	// Flag to set if current completion data is inconsistent with
	// what is stored in the database
	$pending_update = false;

	// Loop through course criteria
	foreach ($completions as $completion) {



		$criteria = $completion->get_criteria();
		$complete = $completion->is_complete();

		$activities_tacked[$criteria->moduleinstance] = true;

		if (!$pending_update && $criteria->is_pending($completion)) {
			$pending_update = true;
		}

		// Activities are a special case, so cache them and leave them till last
		if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {

			if ($complete) {
				$activities[$criteria->moduleinstance] = $complete;
				$activities_complete++;
			}

			continue;
		}

		// Prerequisites are also a special case, so cache them and leave them till last
		if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_COURSE) {
			$prerequisites[$criteria->courseinstance] = $complete;

			if ($complete) {
				$prerequisites_complete++;
			}
			continue;
		}
	}
	$result = array("activities" => $activities, "activities_completed" => $activities_complete, "tracked_activities" => $activities_tacked);

	return $result;
}
?>
