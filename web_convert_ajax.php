<?php


/**
 * Ein Script mit dem man non PDO Scripte ins PDO umwandeln kann.
 * Es schaut ob in einer Query Variablen drin sind und ersetzt diese durch Platzhalter
 */
ob_start();
require_once __DIR__ . "/vendor/autoload.php";

//Prepare the code for php parser:

$code = '<?php' . "\n" . $_POST['input'];

//Generate the code with test parameters so we can test the whole code:
$testCode = Parser::parse($code, true);

//Check the generated sourcecode for errors:
$codeOk = CodeChecker::check($testCode);
if($codeOk){

	$finalCode = Parser::parse($code, false);
	if(ob_get_level()){
		ob_clean();
	}

	Collector::$items['parsed_code'] = $finalCode;
	//echo $finalCode;
}else{
	//echo "// ***************** TESTCODE:\n";
	//echo $testCode;
}

//output a json:
header('Content-Type: application/json');
echo json_encode(Collector::$items);
