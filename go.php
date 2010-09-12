<?php

require 'compiler.php';
$fname = isset($argv[1]) ? $argv[1] : 'file.tpl';

class SimpleLoader {
	public function getFile($name) {
		return $name;
	}
}

$c = new Parser(new CompilerX('$this'), new SimpleLoader());

try {
	$out = $c->parse($fname)."\n";
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
