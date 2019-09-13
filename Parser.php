<?php


use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;


use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ConstFetch;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class Parser
{

    public static $lines     = [];
    public static $sqlParams = [];
    public static $curVar = '';

    public static function addLine($code){
        self::$lines[] = '$' .  self::$curVar . ' = ' . $code . ';';
    }

    public static function parseMethodCall ($node)
    {

        /*
        if ($node instanceof MethodCall) {
            $val = self::parseMethodCall($node);
        }
        */

        $fn = [];
        $params = [];

        if ($node->var instanceof Variable) {

            if (
                $node->var->name === 'Core'
                && in_array($node->name->name, [
                    'fromDatabase',
                    'toDatabase'
                ])) {
                //kein Core, sondern DB
                $fn[] = 'DB::';
            } else {
                $fn[] = '$' . $node->var->name . '->';
            }


        }

        if ($node->name instanceof Node\Identifier) {
            //Name der Funktion
            $fn[] = $node->name->name . '(';
        }

        /**
         * Funktionsparameter:
         */

        foreach ($node->args as $arg) {

            if ($arg->value instanceof Variable) {
                $params[] = '$' . $arg->value->name;
            }

            if ($arg->value instanceof String_) {
                $params[] = "'" . $arg->value->value . "'";
            }

            //Konstanten, z.B. true, false, etc...
            if ($arg->value instanceof ConstFetch) {
                foreach ($arg->value->name->parts as $part) {
                    $params[] = $part;
                }
            }

        }

        $newParams = [];
        if ($node->name->name == 'fromDatabase') {
            //neue fromDatabase Params:
            $newParams[0] = $params[0];  //sql
            $newParams[1] = $params[1] ?? 'FIELD';  //field
            $newParams[2] = self::createArrayString(self::$sqlParams);  //PDO Params

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

        } elseif ($node->name->name == 'toDatabase') {
            //neue toDatabase Params:

            /*  NEW:
    public static function toDatabase (
        $sql,
        $__pdo__ = [],
        $allowForbiddenAction = false,
        $withLog = false,
        $logTitle = ""
    ) {

            ALT:

                public function toDatabase(
            $sql,
            $withLog = false,
            $allowForbiddenAction = false,
            $logTitle = "",
            $postNewId = true
            )

             */

            $newParams[0] = $params[0];
            $newParams[1] = self::createArrayString(self::$sqlParams);

            //$withLog
            if (isset($params[1])) {
                $newParams[3] = $params[1];
            }

            //$allowForbiddenAction
            if (isset($params[2])) {
                $newParams[2] = $params[2];
            }

            //$allowForbiddenAction
            if (isset($params[3])) {
                $newParams[4] = $params[3];
            }

            if (isset($params[4])) {
                //POST_NEW_ID. gibt es nicht mehr in der neuen DB Funktion !!!!:
                $newParams[5] = 'POST_NEW_ID_NOT_IMPLEMENTED';
            }


            $params = $newParams;

        } else {
            //Andere Funktionen, kein Umschreiben der Parameter nötig...
        }

        $fn[] = implode(', ', $params);


        //Funktion Args schließen:
        $fn[] = ')';


        $val = implode('', $fn);

        return $val;
    }

    //Erstellt ein String-Array von Array
    public static function createArrayString ($array)
    {
        $output = [];
        foreach ($array as $key => $value) {
            $output[] = "'{$key}' => $value";
        }

        $arrayString = implode(', ', $output);
        $out = '[' . $arrayString . ']';

        return $out;
    }

    public static function parseConcat ($node)
    {

        if ($node instanceof Concat) {
            $left = self::parseConcatSide($node, 'left');
            $right = self::parseConcatSide($node, 'right');
            return $left . $right;
        }


    }

    public static function parseConcatSide ($node, $side)
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
            //$sideVal = ':' . $s->dim->value;
            $sideVal = ':' . self::parseArrayName($s);
            $valName = self::parseArrayName($s);
        } elseif ($s instanceof Node\Scalar\Encapsed) {
            //Variablen direkt im String
            $sideVal = '';
            foreach ($s->parts as $part) {
                if ($part instanceof Node\Scalar\EncapsedStringPart) {
                    $sideVal .= $part->value;
                }

                if ($part instanceof Variable) {
                    $sideVal .= ':' . $part->name;
                    self::$sqlParams[$part->name] = '$' . $part->name;
                }

            }

        } else {
            $class = get_class($s);
            echo "!!! " . $class . "\n";
            die("NOT RECOGNIZED CONCAT TYPE!!!");

        }


        return $sideVal;
    }

    public static function parseArrayName ($a)
    {

        if ($a->var instanceof ArrayDimFetch) {
            $name = self::parseArrayName($a->var);
        } elseif ($a->var instanceof Variable) {
            //$name = $a->var->name;
            $name = $a->dim->value;

        }

        return $name;

    }


}
