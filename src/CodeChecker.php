<?php

/**
 * Prüft den generierten quellcode nach Fehlern
 * Führt die SQL mit default Params aus (explain)
 */


class CodeChecker{
	public static function check($sourcecode){


		$testcode = <<<code
require_once(__DIR__ . '/../vendor/autoload.php');

$sourcecode
code;


	if(ob_get_level()){
	
		//print testcode (will be removed if everything is ok)
		echo "**** ERROR ****\nFollowing with the Testcode that was executed. The error message is on the bottom.\n\n";
		print_r($testcode);
		echo "\n\n:**** ERROR OUTPUT: ****\n";
	}


try {
		//execute the test code:
		eval($testcode);
} catch (Exception $e) {
	
		//There are some errors in the generated code
		print_r($e->getMessage());
		return false;
}

	
	if(ob_get_level()){
		//Code seems to be ok, remove testcode from output:
		ob_clean();		
	}


		return true;

	}
}