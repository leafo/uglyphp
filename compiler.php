<?php
/**
 * LEAF CORCORAN 2009
 * Compiles template into php code
 *
 * don't forget iterators and macros
 * GET RID OF EXCEPTIONS
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
				if ($this->variable($var))
					echo $this->c->write($var);
				else { // skip it
					$this->count++;
					echo '$';
				}
				break;
			case '{':
				if ($this->block($b)) {
					echo $this->c->block($b);
				} else {
					$this->count++;
					echo '{';
				}
			break;
		}


		$this->text();
		return true;
	}

	// a block is 
	// { expression|funcall }
	function block(&$b) {
		$outside = $this->seek();
		$this->inBlock = true;

		if (!$this->literal('{')) return false;
		$this->inBlock = true;

		// try a value
		if ($this->args($a)) { 
			$b = implode(',', $a);
			goto pass;
		}
		
		/*
		if ($this->keyword($name) and $this->function($name, $func)) {
			// found a complete function			
		}
		*/

		goto fail;

		pass:
		dump("going to pass");
		$this->inBlock = false;
		if ($this->literal('}')) return true;

		fail:
		$this->seek($outside);
		return false;


/*
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
*/
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
		if (!$this->expression($args[])) return false;


		$s = $this->seek();
		while ($this->literal(',') and $this->expression($args[]))
			$s = $this->seek();

		$this->seek($s);

		$fargs = $args;
		return true;
	}

	// expression operator expression ...
	function expression(&$exp) {	
		// try to find left expression
		$s = $this->seek();
		if ($this->literal('(') and $this->expression($left) and $this->literal(')')) {
			$left = '('.$left.')';
		} else {
			$this->seek($s);
			if (!$this->value($left)) return false;
		}

		$s = $this->seek();
		if ($this->operator($o) and $this->expression($right)) {
			$exp = $left.$o.$right;
			return true;
		}

		$this->seek($s);
		$exp = $left;
		return true;
	}


	function operator(&$o) {
		if ($this->match('('.$this->operator_pattern.')', $m)) {
			$o = $m[1];
			return true;
		}
		
		return false;
	}

	// a value is..
	// a variable, a number 
	function value(&$v) {
		// variable 
		if ($this->variable($v)) return true;

		// match a number (keep it simple for now)
		if ($this->match('(-?[0-9]*(\.[0-9]*)?)', $m)) {
			$v = $m[1];
			return true;
		}

		// STRINGS GO HERE

		return false;
	}

	// attempt to read variable
	function variable(&$var) {
		$var = array('chain' => array());

		$inBlock = $this->inBlock;
		$this->inBlock = false;

		$s = $this->seek();
		if (!$this->literal('$') or !$this->keyword($var['name'])) {
			$this->seek($s);
			$this->inBlock = $inBlock;
			return false;
		}

		while (true) {
			$ss = $this->seek();
			if ($this->literal('.') and $this->keyword($name)) {
				$var['chain'][] = array('type' =>'class', 'name' => $name);
				continue;
			} else $this->seek($ss);

			$ss = $this->seek();
			if ($this->literal('|') and $this->keyword($name)) {
				$var['chain'][] = array('type' => 'array', 'name' => $name);
				continue;
			} else $this->seek($ss);

			break;
		}

		$var = $this->c->variable($var);
		$this->inBlock = $inBlock;
		if ($inBlock) $this->whitespace();
		return true;
	}
	
	// match a keyword
	function keyword(&$word) {
		if ($this->match("([a-z_][\w]*)", $m))  {
			$word = $m[1];
			return true;
		}

		return false;
	}

	function literal($what, $eatWhitespace = null) {
		if ($eatWhitespace === null) $eatWhitespace = $this->inBlock;	

		// shortcut on single letter
		if (!$eatWhitespace and strlen($what) == 1) {
			if ($this->buffer{$this->count} == $what) {
				$this->count++;
				return true;
			}
			else return false;
		}

		return $this->match($this->preg_quote($what), $m, $eatWhitespace);
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

	function whitespace() {
		$this->match('', $_, true);
		return true;
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

	// seek to a spot in the buffer
	function seek($where = null) {
		if (!$where) return $this->count;
		else $this->count = $where;
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
