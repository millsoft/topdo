<?php

/**
 * Prüft den generierten quellcode nach Fehlern
 * Führt die SQL mit default Params aus (explain)
 */


class CodeChecker{
	public static function check($sourcecode){


        //is there fromDatabase / toDatabase? we need thos to be able to check the sql:



		if(!preg_match('/(from|to)Database/', $sourcecode)){
			//find the first variable in code (usually $sql):
			preg_match_all('/(\$.+) ?=/m', $sourcecode, $matches, PREG_SET_ORDER, 0);

			if(!empty($matches)){
				$firstVar =  substr($matches[0][1], 1) ;
			}else{
                Collector::$items['error'] = 'first variable (eg. $sql) could not be identified. Either this, or the input $sourcecode contained some errors.';
                Collector::$items['code_ok'] = false;
                Collector::$items['test_code'] = 'TESTCODE COULD NOT BE GENERATED!';
                return false;
			}

			$sourcecode .= "\n\n
				DB::toDatabase(\$$firstVar, \$sql_params);
			";
		}



		$testcode = <<<code
require_once(__DIR__ . '/../vendor/autoload.php');

$sourcecode
code;

	Collector::$items['test_code'] = $sourcecode;


	if(ob_get_level()){

		//print testcode (will be removed if everything is ok)
		//echo "**** ERROR ****\nFollowing with the Testcode that was executed. The error message is on the bottom.\n\n";
		//print_r($testcode);
		//echo "\n\n:**** ERROR OUTPUT: ****\n";

		ob_clean();
	}


try {
		//execute the test code:
		eval($testcode);
} catch (Exception $e) {

		//There are some errors in the generated code
		Collector::$items['error'] = $e->getMessage();
		Collector::$items['code_ok'] = false;
		//print_r($e->getMessage());
		return false;
}


	if(ob_get_level()){
		//Code seems to be ok, remove testcode from output:
		ob_clean();
	}

		Collector::$items['code_ok'] = true;
		return true;

	}
}
