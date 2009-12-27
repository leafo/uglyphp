<?php
/**
 * LEAF CORCORAN 2009
 * Compiles template into php code
 *
 * don't forget iterators and macros
 */

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

	public function write($value) { return "<?php echo ".$value."; ?>"; }
	public function block($b) { return "<< $b >>"; }
	public function if_block($cond) { return 'if ('.$cond.') {'; }

	public function else_block($tag = null) {
		switch ($tag) {
			default: return '} else {';
		}
	}

	public function funcall($name, $args) {
		return $name.'('.implode(', ',$args).')';
	}

	public function end_block($tag = null) { return '}'; }
}

class Parser {
	private $buffer; // the buffer never needs to change
	private $marks = array();
	private $count = 0; 
	private $inBlock = false; // curently in block, controls eat_whitespace

	// tag stack
	private $stack = array();
	private function push($data) { $this->stack[] = $data; }
	private function pop() { return array_pop($this->stack); }
	private function peek() { return end($this->stack); }

	public function __construct() {
		$this->c = new Compiler();
		// don't forget unary ops
		$this->operator_pattern = implode('|',
			array_map(function($s) { return preg_quote($s, '/'); }, 
			array('+', '-', '/', '%', '==', 	
				'===', '<', '<=', '>', '>=')));
	}

	// read text up until next variable or block
	function text() {
		if (!$this->match('(.*?)(\$|\{)', $m)) {
			echo substr($this->buffer, $this->count);
			return true;  // all done
		}
		
		echo $m[1];
		$this->count--; // give back the starting character
		$this->marks = array();

		switch($m[2]) {
			case '$':
				try {
					$this->variable($var);
					echo $this->c->write($var);
				} catch (exception $e) { 
					$this->count++; echo '$'; 
				}
				break;
			default:
				try {
					$this->block($b);
					echo $this->c->block($b);
				} catch (exception $e) { $this->count++; echo '{'; }
		}


		$this->text();
		return true;

		if ($this->buffer{0} == '$') {
			try {
				$this->m()->variable($var)->advance();
				echo $this->c->write($var);
			} catch (exception $e) { 
				$this->reset(); 
				echo $this->advance(1); // skip the $ 
			}
		} else {
			dump('found start of block');
			dump($this->count);
			dump($this->buffer);

			try {
				$this->literal('{');
			} catch(exception $e) {
				dump($e->getMessage());
				return;
			}

			/*
			try {
				$this->m()->block($b)->advance();
				echo $this->c->block($b);	
			} catch (exception $e) {
				$this->reset();
				echo $this->advance(1); // skip the {
			}
			*/

		}

		$this->text();
		return true;
	}

	// a block is 
	// { expression|funcall }
	function block(&$b) {
		try { 
			$this->m()->literal('{', true);
			$this->inBlock = true;

			// try all possible contents
			// try a function
			try {
				$this->m()->keyword($name)->func($name, $out);

				$b = $out;

			} catch (exception $e) { 
				$this->reset(); 
				// else, it is an expression
				$this->expression($b);	
			}

			$this->inBlock = false;
			$this->literal('}');
			return $this;
		} catch (exception $ex) { $this->reset(); }

		throw new exception('failed to capture block');
	}
	
	function func($name, &$out) {
		switch ($name) {
			case 'if':
				$this->expression($exp);
				$out = $this->c->if_block($exp);
				$this->push($name);
				break;
			case 'else':
				if (count($this->stack) == 0)
					throw new exception('unexpected else');

				$out = $this->c->else_block($this->peek());
				break;
			case 'end':
				if (count($this->stack) == 0)
					throw new exception('unexpected end');

				$out = $this->c->end_block($this->pop());
				break;
			default:
				// no reserved words, must be special function
				$this->args($args);
				$out = $this->c->funcall($name, $args);
				break;
		}
	}

	// consume argument list
	function args(&$fargs) {
		$args = array();
		$this->expression($args[]);
		try {
			while (true) 
				$this->m()->literal(',')->expression($args[]);
		} catch (exception $ex) { $this->reset(); }

		$fargs = $args;
		return $this;
	}

	// expression operator expression ...
	function expression(&$exp) {	
		// try to find left
		try {
			$this->m()->literal('(')->expression($left)->literal(')');
			$left = '('.$left.')';
		} catch (exception $e) { 
			$this->reset(); 
			// not wrapped in (), must be plain value
			$this->value($left);
		}

		// see if there is a right
		try {
			$this->m()->operator($o)->expression($right);
			$left = $left.$o.$right;
		} catch (exception $e) { $this->reset(); }

		$exp = $left;
		return $this;
	}


	function operator(&$o) {
		if ($this->match('('.$this->operator_pattern.')', $m)) {
			$o = $m[1];
			return $this;
		}

		throw new exception('failed to find operator');
	}

	// a value is..
	// a variable, a number 
	function value(&$v) {
		// variable 
		try { 
			$this->variable($v);
			return $this;
		} catch (exception $e) { }

		// match a number (keep it simple for now)
		if ($this->match('(-?[0-9]*(\.[0-9]*)?)', $m)) {
			$v = $m[1];
			return $this;
		}

		// STRINGS
		/*
		try {
			$this->m('"')->pattern()
		} catch (exception $e) {
			
		}
		*/


		throw new exception('failed to find value');
	}


	// attempt to read variable
	function variable(&$var) {
		$var = array('chain' => array());
		try {
			$this->m()->literal('$')->keyword($var['name']);
		} catch (exception $e) {
			$this->reset();	
			throw new exception('failed to find variable');
		}

		while (true) {
			try {
				$this->m()->literal('.')->keyword($name);
				$var['chain'][] = array('type' =>'class', 'name' => $name);
				continue;
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

		$var = $this->c->variable($var);
		return $this;
	}
	
	// match a keyword
	function keyword(&$word) {
		if (!$this->match("([a-z_][\w]*)", $m)) 
			throw new exception('failed to grab keyword');

		$word = $m[1];
		return $this;
	}

	private function literal($what, $eatWhitespace = null) {
		// if $what is one char we can speed things up
		if ((!$eatWhitespace && strlen($what) == 1 && $this->count < strlen($this->buffer) && $what != $this->buffer{$this->count}) ||
			!$this->match($this->preg_quote($what), $m, $eatWhitespace))
		{
			throw new
				Exception('failed to grab literal '.$what);
		}
		return $this;
	}
	
	// try to match something on head of buffer
	function match($regex, &$out, $eatWhitespace = null) {
		if ($eatWhitespace === null)
			$eatWhitespace = $this->inBlock;	

		$r = '/'.$regex.($eatWhitespace ? '\s*' : '').'/Ais';
		if (preg_match($r, $this->buffer, $out, null, $this->count)) {
			$this->count += strlen($out[0]);
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

	// remove all the comments
	function clear_comments($str) {
		return preg_replace('/^\s*\/\/.*(\n|$)/m', '', $str);
	}

	function parse($str) {
		$this->buffer = $this->clear_comments($str);
		$this->count = 0;
		ob_start();
		$this->text();
		$out = ob_get_clean();

		return $out;
	}
}

?>
