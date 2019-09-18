<?php

/**
 * Parser for converting old SQL Methods to PDO
 * For Planet ITservices Projects, mainly TWM
 * By Michael Milawski
 * 14.09.2019
 */


use PhpParser\{
    Error,
    NodeDumper,
    ParserFactory,
    NodeTraverser,
    NodeVisitorAbstract,
    PrettyPrinter
};

use PhpParser\Node;

use PhpParser\Node\{
    Name,
    Identifier,
    Stmt\Function_,
    Stmt\Expression,
    Scalar\String_,
    Scalar\EncapsedStringPart,
    Scalar\Encapsed
};

use PhpParser\Node\Expr\{
    Variable,
    FuncCall,
    MethodCall,
    ArrayDimFetch,
    ConstFetch,
    PropertyFetch,
    BinaryOp\Concat,
    Assign,
    Ternary,
    Cast\Int_
};



class Parser
{

    public static $lines     = [];
    public static $sqlParams = [];

    public static $curVar = '';
    public static $quoteChar = '"';

    //Nummer für anonyme Platzhalter (wird inkrementiert)
    public static $countVar = 0;
    private static $sqlParamsVarName = '$sql_params';

    //Did the input had the fromDatabase / toDatabase function? If not, we will print the extracted parameters of the $sql in the last line.
    public static $hadDatabaseFunction = false;
    private static $paramsLineAdded = false;

    //if test, only test code will be returned.
    //this will be set in the parse() method
    private static $isTest = false;

    /**
     * Add new parsed code line
     *
     * @param string $code
     * @param boolean $withVar - if a variable was recognized, prepend to code
     * @return void
     */
    public static function addLine($code, $withVar = true)
    {
        $wVar = '';
        if ($withVar) {

            if(stripos($code, 'todatabase') !== false){
                //toDatabase no need for var:
            }else{
                $wVar = '$' .  self::$curVar . ' = ';
            }


        }


        $code = self::cleanUp($code);
        self::$lines[] = $wVar . $code . ';';
    }

    private static function decamelize($string)
    {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
    }

    private static function getVariableNameFromMethodName($methodName)
    {
        $methodName = str_ireplace('get', '', $methodName);
        $methodName = self::decamelize($methodName);
        return $methodName;
    }


    /**
     * Return the variable to sql params or an empty array
     * @return [type] [description]
     */
    public static function getSqlParamsVar(){
        //self::$sqlParamsVarName
        if(count(self::$sqlParams)){
            return self::$sqlParamsVarName;
        }else{
            return '[]';
        }
    }

    public static function parseMethodCall($node)
    {

        $fn = [];
        $params = [];


        $isDbMethod = false;

        if ($node->var instanceof Variable || $node->var instanceof PropertyFetch) {

            if (
                in_array($node->name->name, [
                    'fromDatabase',
                    'toDatabase'
                ])
            ) {

                self::$hadDatabaseFunction  = true;
                $isDbMethod = true;
                //kein Core, sondern DB
                $fn[] = 'DB::';
            } else {

                $isDbMethod = false;
                $fn[] = '$' . $node->var->name . '->';

                $realVar = self::getVariableNameFromMethodName($node->name->name);

            }
        } elseif ($node->var instanceof PropertyFetch) {
            // $fn[] = '$' . $node->var->var->name;
        }
        if ($node->name instanceof Identifier) {
            $fn[] = $node->name->name . '(';
        }

        /**
         * Funktionsparameter:
         */

        foreach ($node->args as $arg) {

            if ($arg->value instanceof Variable) {
                $params[] = '$' . $arg->value->name;
            } elseif ($arg->value instanceof ArrayDimFetch) {
                $params[] = self::parseArrayName($arg->value);
            } elseif ($arg->value instanceof String_) {
                $params[] = "'" . $arg->value->value . "'";
            } elseif ($arg->value instanceof ConstFetch) {
                //Konstanten, z.B. true, false, etc...
                foreach ($arg->value->name->parts as $part) {
                    $params[] = $part;
                }
            } else {
                $className = get_class($arg->value);
                die("CLASS not recognized: $className");
            }
        }



        $newParams = [];
        $isDatabaseFunction = false;

        if ($node->name->name == 'fromDatabase') {

            /**
             * alte fromDatabase Parameter umschreiben
             */

            $newParams[0] = $params[0];  //sql
            $newParams[1] = $params[1] ?? 'FIELD';  //field
            $newParams[2] = self::getSqlParamsVar();

            //$html
            if (isset($params[2])) {
                $newParams[3] = $params[2];
            }

            //$groupby
            if (isset($params[3])) {
                $newParams[4] = $params[3];
            }

            //$withNumRows
            if (isset($params[4])) {
                $newParams[5] = $params[4];
            }

            //$doNotCheckDataStatus
            if (isset($params[5])) {
                $newParams[6] = $params[5];
            }

            //$rewriteForViews
            if (isset($params[6])) {
                $newParams[7] = $params[6];
            }

            $params = $newParams;

            $isDatabaseFunction = true;
        } elseif ($node->name->name == 'toDatabase') {
            /**
             * alte fromDatabase Parameter umschreiben
             */

            $newParams[0] = $params[0];
            $newParams[1] = self::getSqlParamsVar();

            //$withLog
            if (isset($params[1])) {
                $newParams[2] = $params[1];
            }

            //$allowForbiddenAction
            if (isset($params[2])) {
                $newParams[3] = $params[2];
            }

            //$allowForbiddenAction
            if (isset($params[3])) {
                $newParams[4] = $params[3];
            }

            if (isset($params[4])) {
                //POST_NEW_ID. gibt es nicht mehr in der neuen DB Funktion !!!!:
                $newParams[5] = 'POST_NEW_ID_NOT_IMPLEMENTED';
            }

            $isDatabaseFunction = true;

            $params = $newParams;
        } else {
            //Andere Funktionen, kein Umschreiben der Parameter nötig...
        }

        $fn[] = implode(', ', $params);


        //Funktion Args schließen:
        $fn[] = ')';


        $val = implode('', $fn);

        if ($isDatabaseFunction == true) {
            //create an array before database functions:

            self::addParamsLine();
        }

        if (isset($realVar)) {
            $val = self::addSqlParam($realVar, $val);
        }

        return $val;
    }

    //Erstellt ein String-Array von Array
    public static function createArrayString($array)
    {
        $output = [];
        foreach ($array as $key => $value) {
            $output[] = "\t'{$key}' => $value";
        }

        $arrayString = implode(",\n", $output);
        $out = "[\n" . $arrayString . "\n]";

        return $out;
    }


    //Parse the whole string that is splitted, eg: "string1" . $var . "string2"
    public static function parseConcat($node)
    {
        if ($node instanceof Concat) {
            $left = self::parseConcatSide($node, 'left');
            $right = self::parseConcatSide($node, 'right');
            return $left . $right;
        }
    }



    //parse variables that are inside a string
    public static function parseEncapsed($node)
    {


        $out = [];

        foreach ($node->parts as $part) {
            $out[] = self::parsePart($part);
        }

        return implode('', $out);
    }

    public static function parsePart($part){

            if ($part instanceof EncapsedStringPart) {
                $out = self::parseEncapsedStringPart($part);
            } elseif ($part instanceof Variable) {
                $out = self::parseVariable($part);
            } elseif ($part instanceof PropertyFetch) {
                $out = self::parsePropertyFetch($part);
            } elseif ($part instanceof ArrayDimFetch) {
                $out = ':' . self::parseArrayName($part);
            } else {
                die("not parsed: " . get_class($part));
            }

            return $out;
    }

    public static function  parseEncapsedStringPart($s)
    {
        return $s->value;
    }

    public static function  parseVariable(Variable $s)
    {
        $val = self::addSqlParam($s->name, '$' . $s->name);
        return ':' .  $val;
    }


    //  $SomeObject->someProperty
    public static function  parsePropertyFetch($s)
    {

        $var = self::getCodeFromNode($s);
        $val = self::addSqlParam( $s->name->name, $var);

        return ':' . $val;
    }



    /**
     * Parse a concated string
     * Replaces recognized variables with placeholders
     * @param $node
     * @param $side - (by concat, either 'left' or 'right')
     *
     * @return Node\Scalar\string|string
     */
    public static function parseConcatSide($node, $side)
    {

        $s = $node->{$side};

        $valName = '__DEFAULTNAME__';
        $valVal = 'VAL_VAL';



        if ($s instanceof Concat) {
            $sideVal = self::parseConcat($s);
            return $sideVal;

        } //Variable

        //String
        elseif ($s instanceof String_) {
            //print_r($s);
            $sideVal = $s->value;
            $valName = '';

        } //Variable
        elseif ($s instanceof Variable) {
            $sideVal = ':' . $s->name;
            $valName = $s->name;
            $valVal = '$' . $s->name;
            $sideVal =  ':' . self::addSqlParam($valName, $valVal);


        } //Array:
        elseif ($s instanceof ArrayDimFetch) {
            //print_r($s);
            $sideVal = ':' . self::parseArrayName($s);
            //$valName = self::parseArrayName($s);

        } // $object->someproperty:
        elseif ($s instanceof  PropertyFetch) {
            $sideVal = self::parsePropertyFetch($s);
        } // normal "$variables in string"
        elseif ($s instanceof Encapsed) {
            $sideVal = self::parseEncapsed($s);

        } // $object->methodCall()
        elseif ($s instanceof MethodCall) {
            $valName = $s->name;
            $val = self::getCodeFromNode($s);
            $sideVal = ':' . self::addSqlParam($valName, $val);


        } // Ternary operator ($x == 1 ? 1 : 2) and functions()
        elseif ($s instanceof Ternary || $s instanceof FuncCall) {

            $prettyPrinter = new PrettyPrinter\Standard;
            $valName = self::getAnonymePlaceholderName();

            $sideVal = ':' . self::addSqlParam($valName, $prettyPrinter->prettyPrintExpr($s));

        } // cast to (int)
        elseif ($s instanceof Int_) {
            //no additional parsing, just get the code:
            $valName = self::getAnonymePlaceholderName();
            $sideVal = ':' . self::addSqlParam($valName, self::getCodeFromNode($s->expr));

        } //Something unrecognized.
        else {
            $class = get_class($s);
            echo "!!! " . $class . "\n";
            die("NOT RECOGNIZED CONCAT TYPE!!!");
        }


        return $sideVal;
    }

    /**
     * Anonymen Platzhalter Namen holen
     * Wird beim Aufruf um 1 inkrementiert
     * @return string
     */
    private static function getAnonymePlaceholderName()
    {
        self::$countVar++;
        return 'val_' . self::$countVar;
    }




    /**
     * Get the last key of a array variable
     * That key will be used as sql :placeholder
     *
     * @param $a
     * @param boolean $isRecursive
     * @return string
     */
    public static function parseArrayName($a, $isRecursive = false)
    {

        /*
        if ($a->var instanceof ArrayDimFetch) {
            $name = self::parseArrayName($a->var, true);
        } elseif ($a->var instanceof Variable) {
            $name = $a->dim->value;
        }
        */

        $code = self::getCodeFromNode($a);

        $newName = $code;
        $newName = str_replace(["'", '"',  '[', ']', '$_', '$'], ['', '', ' ', ' ', '' , ''], $newName);
        $newName = trim($newName);
        $newName = str_replace([' ', '__'], '_', $newName);


        if (!$isRecursive) {
            return self::addSqlParam($newName, $code);
        }

    }



    private static function cleanUp($code)
    {


        //replace ':placeholder' with :placeholder
        $re = '/[\"\']\:(.+?)[\"\']/i';
        $code = preg_replace($re, ':$1', $code);

        //replace ':placeholder' with :placeholder

        return $code;
    }


    /**
     * Get code part as string from a expr node
     */
    public static function getCodeFromNode($node)
    {
        $prettyPrinter = new PrettyPrinter\Standard;
        $code = $prettyPrinter->prettyPrintExpr($node);
        return $code;
    }

    /**
     * Add a line with the array of parameters
     *
     * @return void
     */
    public static function addParamsLine()
    {
        if (self::$paramsLineAdded || empty(self::$sqlParams)) {
            return false;
        }


        if(self::$isTest){
            //change all values to some valid value (removes functions, vars, etc..)
            $newParams = [];
            foreach(self::$sqlParams as $key=>$val){
                $newParams[$key] = 0;
            }

            //$params = self::createArrayString($newParams);
            $finalParams = $newParams;
        }else{
            $finalParams = self::$sqlParams;
        }


        //compress the param names:
        self::updatePlaceholders($finalParams);


        //sort param array keys:
        ksort($finalParams);
        $params = self::createArrayString($finalParams);

        self::addLine("\n" . self::$sqlParamsVarName . ' = ' . $params, false);

        self::$paramsLineAdded = true;
    }


    /**
     * Update placeholders with smaller versions
     * @param $params
     */
    private static function updatePlaceholders($params){
        $params = self::shakeParams($params);

        //get first param so we can find the correct line with the sourcecode:
        $findParam = array_key_first($params);

        foreach(self::$lines as &$line){
            if(strpos($line,  ':' . $findParam)){
                foreach($params as $oldParam => $newParam){
                    $line = str_replace(':' . $oldParam, ':' . $newParam, $line);
                }
            }
        }

        self::$sqlParams = $params;

    }


    private static function shakeParams($params){


        $newParams = [];

        foreach($params as $param => $val){
            $ex = explode('_', $param);

            //Check if the var name end is numeric
            $re = '/\d+$/m';
            $numericEnd = preg_match_all($re, $param, $matches, PREG_SET_ORDER, 0);

            $offsetEnd = $numericEnd ? 3 : 2;




            if(count($ex) > 2){

                $ex_limited = explode('_',  $param, count($ex)- $offsetEnd );
                $newParam = $ex_limited[count($ex_limited)-1];


                $finalParam = $newParam;

            }else{
                $finalParam = $param;
            }


            //Remove prepended POST_ _GET etc..
            $newParam = preg_replace('/((POST|GET|REQUEST)\_)/m', '', $finalParam);
            $isIdOrVal = preg_match_all('/(_(?<id_or_val>id|val)_)/', $param, $matches, PREG_SET_ORDER, 0);

            if($isIdOrVal){

                $id_or_val = $matches[0]['id_or_val'];
               if(!stripos($finalParam, $id_or_val )){
                   $finalParam = $id_or_val . '_' . $finalParam;
               }
            }




            $newParams[$param] = $finalParam;



        }

        //order the keys by size, so we can replace them later (:id_example will be replaced before :id)
        $keys = array_map('strlen', array_keys($newParams));
        array_multisort($keys, SORT_DESC, $newParams);

        return $newParams;
    }


    private static function reparseParams($params){

        //$par = ArrayHelper::normalizeArray($params, '_');
        $par = ArrayHelper::expandKeys($params, '_');
        printr($par);
    }

    /**
     * Add a sql param name+value
     *
     * @param string $name - name of the placeholder / value
     * @param string $val
     * @return string the final name for the $name.
     */
    private static function addSqlParam($name, $val){

        //Replace placeholder minus with underscore
        $name = str_replace('-', '_', $name);

        //check if the key is already in params,
        //placehodler values can't be reused in PDO
        if(isset(self::$sqlParams[$name])){
            //extract the real var name without index:

            //iterate index until we can use the key:
            $acceptableIndexFound = false;

            /*
            print_r(self::$sqlParams);
            echo "\n";
            print_r($val);
            echo "\n------\n";
            */


            //start the additional found variables with this index:
            $index = 2;
            while(!$acceptableIndexFound){

                $newName = $name . '_' . $index;
                //echo "CHECK: $newName";
                if(!isset(self::$sqlParams[$newName])){
                    //found free name
                    $name = $newName ;
                    $acceptableIndexFound = true;
                }
                $index++;
            }
        }

        //add the param:
        self::$sqlParams[$name] = $val;

        //return the final name for the placeholder
        return $name;
    }


    /**
     * Reset parser (between test code and real code generation)
     * @return void
     */
    public static function resetCode(){
        self::$lines = [];
        self::$sqlParams = [];
        self::$curVar = '';
        self::$countVar = 0;
        self::$hadDatabaseFunction = false;
        self::$paramsLineAdded = false;

    }

    /**
     * Is the first occurentce of a variabled string a " or ' ?
     * @param  [string] $code
     * @return [string]
     */
    public static function getFirstQuoteChar($code){
        //search for "
        $pos_1 = strpos($code, '"');
        $pos_2 = strpos($code, "'");

        $char = '"';
        if($pos_1 === false && $pos_2 === false){
            //return default char:
            return $char;
        }

        $pos_1 = (int) $pos_1;
        $pos_2 = (int) $pos_2;

        if($pos_1 == 0){
            //move to back
            $pos_1 = 99999999;
        }

        if($pos_2 == 0){
            //move to back
            $pos_2 = 99999999;
        }

        if(  $pos_1 > 0 && $pos_1 < $pos_2){
            $quoteChar =  '"';
        }else{
             $quoteChar =  "'";
        }

        self::$quoteChar = $quoteChar;
        return $quoteChar;

    }



    //input code prepare
    public static function prepareCode($code){

        $quote = self::getFirstQuoteChar($code);

        //prepend SQL queries with an empty string, so the parser goes through a concat parser:

        $re = '/(\$.+?)=(.+?[\'\"])(SELECT|INSERT|UPDATE|DELETE)/i';

        if($quote == "'"){
            $subst = '$1= \'\' . $2$3';
        }else{
            $subst = '$1= "" . $2$3';
        }

        $result = preg_replace($re, $subst, $code);

        return $result;
    }

    /**
     * Starting point for the parser
     *
     * @param string $code - unparsed php code
     * @param string $getTestCode - code for local testing (eval, pdo checks..)
     * @return string - parsed php code
     */
    public static function parse($code, $getTestCode = false)
    {

        self::$isTest = $getTestCode;


        //reset everything:
        self::resetCode();

        $code = self::prepareCode($code);

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        try {
            $ast = $parser->parse($code);
        } catch (Error $error) {
            echo "Parse error: {$error->getMessage()}\n";
            return;
        }


        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class extends NodeVisitorAbstract
        {
            public function enterNode(Node $node)
            {
                //DEBUG: uncomment that stuff for debugging
                $class = get_class($node);
                //print_r("*** $class\n");
                //print_r($node);
                //die();

                $var = '';

                if (
                    $node instanceof Expression
                ) {

                    if($node->expr instanceof Assign){

                    /*
                    //Variablenzuweisung:
                    $nodeCode = Parser::getCodeFromNode($node->expr);
                    $quoteChar = Parser::getFirstQuoteChar($nodeCode);

                    $var = '$' . $node->expr->var->name . ' = ';
                    Parser::$curVar = $node->expr->var->name;
                    Parser::$quoteChar = $quoteChar;
                    */
                    //$name = $node->expr->name->toString();

                    //echo $var;

                    //return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                    }




                } elseif ($node instanceof Assign) {

                    $var = '$' . $node->var->name . ' = ';
                    Parser::$curVar = $node->var->name;
                    //return NodeTraverser::DONT_TRAVERSE_CHILDREN;


                } elseif ($node instanceof Variable) {

                    //return NodeTraverser::DONT_TRAVERSE_CHILDREN;


                } elseif ($node instanceof ArrayDimFetch) {
                    //print_r($node);die();

                } elseif ($node instanceof Identifier) {
                    //print_r($node);die();


                } elseif ($node instanceof PropertyFetch) {
                    //print_r($node);die();


                } elseif ($node instanceof MethodCall) {

                    Parser::addLine(Parser::parseMethodCall($node));
                    //Parser::$lines[] = $var . Parser::parseMethodCall($node);
                } elseif ($node instanceof Concat) {

                    Parser::addLine(
                          Parser::$quoteChar
                        . Parser::parseConcat($node)
                        . Parser::$quoteChar);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                } elseif ($node instanceof Node\Scalar\Encapsed) {

                    Parser::addLine("<<<sql\n" . Parser::parseEncapsed($node) . "\nsql");
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                } else {
                    //die("NOT_RECOGNIZED:" . get_class($node));
                }


            }
        });


        $ast = $traverser->traverse($ast);


        if (!Parser::$hadDatabaseFunction) {
            //tell to append the sql parameters anyway (if available)
            Parser::addParamsLine();
        }

        $outData = implode("\n", Parser::$lines);

        //Make sure the from/todatabase are from DB class
        $re = '/= (from|to)Database/';
        $outData = preg_replace($re, '= DB::$1Database', $outData);

        return $outData;
    }
}


function printr($txt, $die = true){
    print_r($txt);
    echo "\n";
    if($die){die();};
}
