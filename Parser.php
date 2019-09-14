<?php

/**
 * Parser for converting old SQL Methods to PDO
 * For Planet ITservices Projects, mainly TWM
 * By Mike
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
    Ternary
};



class Parser
{

    public static $lines     = [];
    public static $sqlParams = [];
    public static $curVar = '';

    //Nummer für anonyme Platzhalter (wird inkrementiert)
    public static $countVar = 0;
    private static $sqlParamsVarName = '$sql_params';

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
            $wVar = '$' .  self::$curVar . ' = ';
        }


        $code = self::cleanUp($code);
        self::$lines[] = $wVar . $code . ';';
    }

    private static function decamelize($string) {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
    }

    private static function getVariableNameFromMethodName($methodName){
        $methodName = str_ireplace('get', '', $methodName);
        $methodName = self::decamelize($methodName);
        return $methodName;
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
                $isDbMethod = true;
                //kein Core, sondern DB
                $fn[] = 'DB::';
            } else {
                $isDbMethod = false;
                $fn[] = '$' . $node->var->name . '->';
                
                $realVar = self::getVariableNameFromMethodName($node->name->name);
                //$fn[] = $varName;

                //Lets ovcerwrite the var
                //$realVar = $varName;

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
            //$newParams[2] = self::createArrayString(self::$sqlParams);  //PDO Params
            $newParams[2] = self::$sqlParamsVarName;

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
            //$newParams[1] = self::createArrayString(self::$sqlParams);
            $newParams[1] = self::$sqlParamsVarName;

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
            //self::addLine("FICK!", false);
            $params = self::createArrayString(self::$sqlParams);
            self::addLine(self::$sqlParamsVarName . ' = ' . $params, false);

        }

        if(isset($realVar)){
            self::$sqlParams[$realVar] = $val;
            $val = $realVar;
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

    public static function parseConcat($node)
    {

        if ($node instanceof Concat) {
            $left = self::parseConcatSide($node, 'left');
            $right = self::parseConcatSide($node, 'right');
            return $left . $right;
        }
    }



    public static function parseEncapsed($node)
    {


        $out = [];
        //print_r($node->parts);
        //die();

        foreach ($node->parts as $part) {

            if ($part instanceof EncapsedStringPart) {
                $out[] = self::parseEncapsedStringPart($part);
            } elseif ($part instanceof Variable) {
                $out[] = self::parseVariable($part);
            } elseif ($part instanceof PropertyFetch) {
                $out[] = self::parsePropertyFetch($part);
            } elseif ($part instanceof ArrayDimFetch) {
                $out[] = ':' . self::parseArrayName($part);
            } else {
                die("not parsed: " . get_class($part));
            }
        }

        return implode('', $out);
    }

    public static function  parseEncapsedStringPart($s)
    {
        //die("HERE!");
        return $s->value;
    }

    public static function  parseVariable(Variable $s)
    {
        self::$sqlParams[$s->name] = '$' . $s->name;
        return ':' .  $s->name;
    }


    //  $SomeObject->someProperty
    public static function  parsePropertyFetch($s)
    {
        $sideVal =  ':' .  $s->name->name;
        self::$sqlParams[$s->name->name] = '$' .   $s->var->name . '->' . $s->name->name;
        return $sideVal;
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

        //Concat...
        if ($node->{$side} instanceof Concat) {
            $sideVal = self::parseConcat($s);
        } //String
        elseif ($s instanceof String_) {
            $sideVal = $s->value;
        } //Variable
        elseif ($s instanceof Variable) {
            $sideVal = ':' . $s->name;
            $valName = $s->name;
            $valVal = '$' . $s->name;

            self::$sqlParams[$valName] = $valVal;
        } //Array:
        elseif ($s instanceof ArrayDimFetch) {
            $sideVal = ':' . self::parseArrayName($s);
            $valName = self::parseArrayName($s);

            
        } // $object->someproperty:
        elseif ($s instanceof  PropertyFetch) {
            $sideVal = self::parsePropertyFetch($s);

        } // normal "$variables in string"
        elseif ($s instanceof Encapsed) {
            $sideVal = '';
            foreach ($s->parts as $part) {
                if ($part instanceof EncapsedStringPart) {
                    $sideVal .= $part->value;
                }

                if ($part instanceof Variable) {
                    $sideVal .= ':' . $part->name;
                    self::$sqlParams[$part->name] = '$' . $part->name;
                }
            }

        } // $object->methodCall()
        elseif ($s instanceof MethodCall) {
            $sideVal = ':' .  self::parseMethodCall($s);

        } // Ternary operator ($x == 1 ? 1 : 2) and functions()
        elseif ($s instanceof Ternary || $s instanceof FuncCall) {

            $prettyPrinter = new PrettyPrinter\Standard;
            $valName = self::getAnonymePlaceholderName();
            $sideVal = ':' .  $valName;
            self::$sqlParams[$valName] = $prettyPrinter->prettyPrintExpr($s);
                
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
     * @return void
     */
    public static function parseArrayName($a, $isRecursive = false)
    {


        if ($a->var instanceof ArrayDimFetch) {
            $name = self::parseArrayName($a->var, true);
        } elseif ($a->var instanceof Variable) {
            //$name = $a->var->name;
            $name = $a->dim->value;
        }

        //print_r($a);

        $prettyPrinter = new PrettyPrinter\Standard;
        //self::$sqlParams[$valName] = $prettyPrinter->prettyPrintExpr($s);
        $code = $prettyPrinter->prettyPrintExpr($a);


        $re = '/\$(?P<var>.+?)(?P<dims>\[[\'\"].+\])+/';
        preg_match_all($re, $code, $matches, PREG_SET_ORDER, 0);

        $var = $matches[0]['var'];
        $dims = substr($code, strlen($var) + 1);


        $re2 = '/([\'\"](?<key>.+?)[\'\"])+/';
        preg_match_all($re2, $code, $matches2, PREG_SET_ORDER, 0);


        if (!$isRecursive) {
            $lastKeyName = $matches2[count($matches2) - 1]['key'];

            self::$sqlParams[$lastKeyName] = $code;
            return $lastKeyName;
        }


        //return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        return $name;
    }

    
    private static function cleanUp($code){
        
        //replace ':placeholder' with :placeholder
        $re = '/[\"\']\:(.+?)[\"\']/i';
        $code = preg_replace($re, ':$1', $code);

        return $code;
    }

}