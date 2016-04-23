<?php

require_once('common.php');

/**
* Helper class for kissanime's obfuscated javascript challenge.
*/
class JSFuck
{
	/** The default javascript to number replaces */
	const VALUES = array('!![]' => 1, '![]' => 0, '!+[]' => 1);

	/**
	* Translates obfuscated javascript to a number.
	*
	* @param $input The javascript obfuscated input.
	*/
	public static function unfuckCode($input) {
		if ($input == NULL || trim($input) == '')
			return 0;
			
		$output = $input;

		foreach (self::VALUES as $key => $value) {
			$output = str_replace($key, $value, $output);
		}

		if ($output[0] == '+' && $output[1] == '(') {
			$output = substr($output, 2); // remove +(
			$output = substr($output, 0, strlen($output) - 1);  // remove ending )
		}
		
		if (strpos($output, '[]') !== false) {
			$paracount = 1;
			$i = 1;
			
			for (; $i < strlen($output) && $paracount > 0; $i++) {
				if ($output[$i] == '(')
					$paracount++;

				if ($output[$i] == ')')
					$paracount--;
			}
			
			$p1 = substr($output, 0, $i);
			$p2 = $i + 1 > strlen($output) ? '' : substr($output, $i + 1);
			
			if (endsWith($p1, '+[]')) {
				$p1 = substr($p1, 0, strlen($p1) - strlen('+[]'));
			}

			if (endsWith($p1, '+[])')) {
				$p1 = substr($p1, 0, strlen($p1) - strlen('+[])')) . ')';
			}

			if (endsWith($p2, '+[]')) {
				$p2 = substr($p2, 0, strlen($p2) - strlen('+[]'));
			}

			if (endsWith($p2, '+[])')) {
				$p2 = substr($p2, 0, strlen($p2) - strlen('+[])')) . ')';
			}
			
			if (trim($p2) == '') {
				return self::unfuckCode($p1);
			}
			else {
				return intval(self::unfuckCode($p1) . '' . self::unfuckCode($p2));
			}
		}
		else {
			$numbers = explode('+', str_replace(')', '', str_replace('(', '', $output)));
			$total = 0;
			
			for ($i = 0; $i < count ($numbers); $i++) {
				$total += intval($numbers[$i]);
			}
			
			return $total;
		}
	}
}
?>