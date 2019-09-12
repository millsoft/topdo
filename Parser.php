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
use PhpParser\Node\Expr\ArrayDimFetch;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class Parser {

    public static $lines = [];

    public static function init(){

    }

    public static function parseConcat($node){

        
        //$class = get_class($node);
        //print_r("CCAT = " . $class);
        

        if(
        $node instanceof Concat
    ){

        //print_r($node->right);


        $left = self::parseConcatSide($node, 'left');
        $right = self::parseConcatSide($node, 'right');

        return $left . $right;

        //$name = $node->expr->name->toString();  
        
        //return NodeTraverser::DONT_TRAVERSE_CHILDREN;

    }


    }

    public static function parseConcatSide($node, $side){

        $s = $node->{$side};

        //Concat...
        if($node->{$side} instanceof Concat){
            $sideVal = self::parseConcat($s);
        }
        
        //String
        elseif($s instanceof String_){
            $sideVal = $s->value;
        }
        
        //Variable
        elseif($s instanceof Variable){
            $sideVal = ':' . $s->name;
        }
        
        //Array:
        elseif($s instanceof ArrayDimFetch){
            //$sideVal = ':' . $s->dim->value;
            $sideVal = ':' . self::parseArrayName($s);


            //$sideVal = ':' . $node->{$side}->name;
        }
        

        return $sideVal;
    }

    public static function parseArrayName($a){
        
        print_r($a);
        if($a->var instanceof ArrayDimFetch ){
            $name = self::parseArrayName($a->var);
        }
        elseif($a->var instanceof Variable){
            //$name = $a->var->name;
            $name = $a->dim->value;

        }

        return $name;

    }


    
}