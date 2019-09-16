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

	
try {
		//execute the test code:
		eval($testcode);
} catch (Exception $e) {
		//There are some errors in the generated code
		print_r($e->getMessage());
		return false;
}
		
		return true;

	}
}