<?php
/**
 * LEAF CORCORAN 2009
 * Compiles template into php code
 *
 * don't forget iterators and macros
 */

$debug = "";
function dump($n) {
	global $debug;
	$debug.= print_r($n, 1)."\n";
}


class Compiler {
	public function variable($v) {
		$out = "$$v[name]";
		foreach($v['chain'] as $a) {
			if ($a['type'] == 'array')
				$out .= "['".$a['name']."']";
			else
				$out .= "->".$a['name'];
		}
		return $out;
	}

	public function write($value) {
		return "<?php echo ".$this->variable($value)."; ?>";
	}
}

class Parser {
	private $buffer;
	private $marks = array();
	private $count = 0; // temporary stack

	public function __construct() {
		$this->c = new Compiler();
	}

	// read text up until next variable or block
	function text() {
		if (!preg_match('/(.*?)(\$|\{)/is', $this->buffer, $m)) {
			echo $this->buffer;
			return false;
		}
		echo $this->advance(strlen($m[1]));

		try {
			$this->m()->variable($var)->advance();
			echo $this->c->write($var);
		} catch (exception $e) { 
			$this->reset(); 
			echo $this->advance(1); // skip the $ 
		}

		$this->text();
		return true;
	}

	// attempt to read variable
	function variable(&$var) {
		$var = array('chain' => array());
		$this->literal('$')->keyword($var['name']);

		while (true) {
			try {
				$this->m()->literal('.')->keyword($name);
				$var['chain'][] = array('type' =>'class', 'name' => $name);
			} catch (exception $e) {
				$this->reset();
			}

			try {
				$this->m()->literal('|')->keyword($name);
				$var['chain'][] = array('type' => 'array', 'name' => $name);
			} catch (exception $e) {
				$this->reset();
				break;
			}
		}

		return $this;
	}
	
	// match a keyword
	function keyword(&$word) {
		if (!$this->match("([\w_][\w_0-9]*)", $m)) 
			throw new exception('failed to grab keyword');

		$word = $m[1];
		return $this;
	}

	private function literal($what) {
		// if $what is one char we can speed things up
		if ((strlen($what) == 1 && $this->count < strlen($this->buffer) && $what != $this->buffer{$this->count}) ||
			!$this->match($this->preg_quote($what), $m))
		{
			throw new
				Exception('parse error: failed to prase literal '.$what);
		}
		return $this;
	}
	
	// try to match something on head of buffer
	function match($regex, &$out, $eatWhitespace = false) {
		$r = '/^.{'.$this->count.'}'.$regex.($eatWhitespace ? '\s*' : '').'/is';
		if (preg_match($r, $this->buffer, $out)) {
			$this->count = strlen($out[0]);
			return true;
		}
		return false;
	}

	private function preg_quote($what) {
		return preg_quote($what, '/');
	}

	// write a mark
	function m() {
		$this->marks[] = $this->count;
		return $this;
	}

	// pop mark off stack and set count
	function reset() {
		if (!empty($this->marks))
			$this->count = array_pop($this->marks);
	}

	function advance($n = null) {
		if (is_int($n)) $this->count += $n;

		$eat = substr($this->buffer, 0, $this->count);
		$this->buffer = substr($this->buffer, $this->count);
		$this->count = 0;
		$this->marks = array();

		return $eat;
	}

	function parse($str) {
		$this->buffer = $str;
		$this->count = 0;
		ob_start();
		$this->text();
		$out = ob_get_clean();

		return $out;
	}
}

?>
