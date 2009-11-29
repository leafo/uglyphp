<?php

$debug = "";
function dump($n) {
	global $debug;
	$debug.= print_r($n, 1)."\n";
}

require 'compiler.php';

$document = file_get_contents('file.tpl');
//print_r($document);

$c = new Parser();

$out = $c->parse($document)."\n";
echo $debug;
echo "\n\nOut:\n".$out;

?>
