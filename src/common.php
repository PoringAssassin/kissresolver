<?php

// sauce: http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php

/**
* Checks if a string starts with another.
*
* @param $haystack The string to search in.
* @param $needle The string to search for.
*/
function startsWith($haystack, $needle) {
	// search backwards starting from haystack length characters from the end
	return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

/**
* Checks if a string ends with another.
*
* @param $haystack The string to search in.
* @param $needle The string to search for.
*/
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

?>