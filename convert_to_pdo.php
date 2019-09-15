<?php

/**
 * Ein Script mit dem man non PDO Scripte ins PDO umwandeln kann.
 * Es schaut ob in einer Query Variablen drin sind und ersetzt diese durch Platzhalter
 */

require_once __DIR__ . "/vendor/autoload.php";
//require_once __DIR__ . "/src/Parser.php";


$inputFile = __DIR__ . "/data/input.txt";
//$outputFile = __DIR__ . "/data/output.txt";

$code = '<?php' . "\n" . file_get_contents($inputFile);

$output = Parser::parse($code);

echo $output;



