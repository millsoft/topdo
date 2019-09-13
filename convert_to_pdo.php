<?php

/**
 * Ein Script mit dem man non PDO Scripte ins PDO umwandeln kann.
 * Es schaut ob in einer Query Variablen drin sind und ersetzt diese durch Platzhalter
 */
use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;


use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

// Regex: https://regex101.com/r/bErI90/2/



require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Parser.php";


$code = <<<'CODE'
<?php

//$sql = "SELECT * FROM bla WHERE id = " . $a['aba'] . " LIMIT 5 " . $x['yyy']['aaaa'];
//$myModelRows = $Core->fromDatabase($sql, '@simple', false, false);


//$sql = "SELECT * FROM test WHERE id = " . $user_id . " AND x = $test_id";
//$myModelRows = $Core->fromDatabase($sql, '@simple');

$sql = 'UPDATE `' . $tableName . '` SET `created`=now() WHERE `id`=' . $lastInsertId;
$Core->toDatabase($sql);

CODE;

echo "INPUT:\n*****************\n";
echo $code;
echo "\n*****************\n";
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper;
echo $dumper->dump($ast) . "\n";


$traverser = new NodeTraverser();
$traverser->addVisitor(new class extends NodeVisitorAbstract {
    public function enterNode(Node $node) {
        $class = get_class($node);
        echo "*** " . $class . "\n";

        //print_r($node);



        if($node instanceof Node\Stmt\Expression
            && $node->expr instanceof Node\Expr\Assign
        ){

            //Variablenzuweisung:
            Parser::$lines[] = '$' . $node->expr->var->name . ' = ';

            //$name = $node->expr->name->toString();


//            return NodeTraverser::DONT_TRAVERSE_CHILDREN;

        }


        if($node instanceof MethodCall){
            Parser::$lines[] = Parser::parseMethodCall($node);
        }

        if($node instanceof Concat){



        Parser::$lines[] = Parser::parseConcat($node);

        //$name = $node->expr->name->toString();

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;


    }



        echo "\n";
    }


    /*
    public function leaveNode(Node $node) {
        $class = get_class($node);

        print_r("LEAVE_NODE = " . $class . "\n");

        if ($node instanceof Function_) {
            // Clean out the function body
            $node->stmts = [];
        }
    }
    */


});


$ast = $traverser->traverse($ast);
//echo $dumper->dump($ast) . "\n";

print_r(Parser::$lines);
print_r(Parser::$sqlParams);
