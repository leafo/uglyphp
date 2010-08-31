<?php

require 'compiler.php';
$input = isset($argv[1]) ? $argv[1] : 'file.tpl';
$document = file_get_contents($input);
//print_r($document);
//$c = new Parser(new JSCompiler('env'));
$c = new Parser(new CompilerX('$this'));

try {
	$out = $c->parse($document)."\n";
	$c->printLog();
} catch (exception $e) {
	echo $c->printLog();
	echo chr(27)."[1;31mException:".chr(27)."[0m ".$e->getMessage()."\n";
	exit();
}


/*
$config = array( 
	'clean' => true, 
	'drop-proprietary-attributes' => true, 
	'output-html' => true, 
	'word-2000' => true, 
	'wrap' => 0,
	'indent' => true,
); 
$tidy = new tidy;
$tidy->parseString($out, $config, 'utf8');
$tidy->cleanRepair();
 */

// Output
echo "======================\n";
echo $out."\n";

?>
