<?php
/**
 * the UGLYPHP templating language
 * LEAF CORCORAN 2010
 *
 * don't forget iterators and macros
 * if block fails then we need to unpush it
 */

// [x] the compiler should control the output, not the parser
// [ ] the compiler should be smarter about what it is compiling (stronger types)
// [x] the compiler should not be aware of the parser at all (why is it now?)

class Parser {
	private $buffer; // the buffer never needs to change
	private $count = 0; 
	private $inBlock = false; // curently in block, controls eat_whitespace
	private $macros = array();

	private $macroStack = array(); 
	private $expanding = null;

	static public $log = array();
	static public function log($msg) {
		self::$log[] = print_r($msg, 1);
	}
	static public function printLog() {
		echo implode(array_map(function($i) { 
			return '-- '.$i."\n";
		}, self::$log));
	}

	public function __construct($compiler = null) {
		$this->c = $compiler ? $compiler : new CompilerX();
		// $this->c->parser = $this; // don't need this

		// don't forget unary ops
		$this->operator_pattern = implode('|',
			array_map(array($this, 'preg_quote'), 
			array('+', '-', '/', '%', '==', 	
				'===', '<', '<=', '>', '>=')));

		$this->builtins = array();
		$methods = get_class_methods($this);
		foreach ($methods as $m) {
			if (preg_match('/^block_(\w+)$/', $m, $match)) {
				$this->builtins[] = $match[1];
			}
		}
	}

	// read text up until next variable or block
	function text() {
		if ($head = end($this->macroStack)) {
			$macroDelim = $head->delim;
		} else $macroDelim = false;

		$inMacroDefine = $head && $head->type == 'macro-define';
			
		if (!$this->match('(.*?)(\{\*|\$|\{|%'.($macroDelim ? '|'.$macroDelim: '').')', $m, false)) {
			$this->c->text(substr($this->buffer, $this->count));
			if ($inMacroDefine) while ($this->popMacro());
			return true;  // all done
		}
		
		$this->c->text($m[1]);
		$token = $m[2];
		$this->count -= strlen($token); // give back the starting character

		switch(trim($token)) {
			// macro
			case '%':
				if ($this->macroDefinition($new)) {
					$this->pushMacro($new);
				} elseif ($this->macroExpansion($key)) {
					if (is_null($key->delim))
						$this->renderMacro($key);
					else
						$this->pushMacro($key);
				} else {
					$this->pass($token);
				}
				break;
			// ending macro
			case $macroDelim: 
				$this->popMacro();
				$this->count += strlen($token);
				break;
			// comment
			case '{*':
				$this->to('*}', $m, false, true);
				break;
			// variable
			case '$':
				if ($this->variable($var)) {
					// see if it is part of macro
					if ($inMacroDefine && in_array($var['name'], $head->args)) {
						$this->log('macro var: '.$var['name']);

						$head->text[] = $this->c->popBuffer();
						$head->text[] = $var;
						$this->c->pushBuffer();
					} else {
						$this->c->compileChunk($var);
					}
				} else { // skip it
					$this->pass($token);
				}
				break;
			// expression/block
			case '{':
				if ($this->block($b)) {
					/*
					if ($inMacroDefine) {
						$head->text[] = ob_get_clean();
						$head->text[] = $b;
						ob_start();
					} else {
						// free to output directly
						if (is_object($b)) echo $this->c->block($b);
						elseif ($b) echo $this->c->write($b);
					}
					 */
				} else { // skip
					$this->pass($token);
				}
			break;
		}

		$this->text();
		return true;
	}

	// check if a variable needs to be mixed for macro
	public function subVariable($v) {
		if ($key = $this->expanding) {
			if (isset($key->args[$v['name']])) {
				$v = $this->mixVariables($key->args[$v['name']], $v);
			}
		}
		return $v;
	}

	public function mixVariables($left, $right) {
		return array(
			'name' => $left['name'],
			'chain' => array_merge($left['chain'], $right['chain'])
		);
	}

	// render a macro from macro-expand object
	// should this be in the compiler?
	public function renderMacro($key) {
		if (!isset($this->macros[$key->name])) return '';
		$macro = $this->macros[$key->name];
		$this->log("rendering macro `$macro->name`");

		// give names to args
		$raw = $key->raw_args;
		foreach ($macro->args as $aname) {
			if ($avalue = array_shift($raw))
				$key->args[$aname] = $avalue;
			else break;
		}

		$e = $this->expanding;
		$this->expanding = $key;
		foreach ($macro->text as $chunk) {
			if (is_array($chunk)) { // its a variable
				$name = $chunk['name'];
				$this->log("checking $name");
				if (!isset($key->args[$name])) continue;
				$value = $key->args[$name];
				// TODO: real type checking!!
				if (is_array($value) && isset($value[0]) && $value[0] == 'string') {
					$this->c->text($value[2]);
				} elseif (is_array($value)) { // this is a value (variable, string)
					$this->c->compileChunk($this->mixVariables($value, $chunk));
				} elseif (is_object($value)) { // a macro key
					$this->renderMacro($value);
				} else {
					$this->c->text($value);
				}
			} elseif (is_object($chunk)) {
				$this->throwParseError('got an object as chunk in expand macro');
			} else {
				$this->c->text($chunk);
			}
		}
		$this->expanding = $e;
	}

	// get opening body of macro
	public function macroOpenBody(&$delim) {
		if ($this->literal('{')) {
			$delim = '}%';
		} else if ($this->literal('[[')) {
			$delim = ']]';
		} else {
			return false;
		}
		return true;
	}

	// parse an expand macro statement
	public function macroExpansion(&$m) {
		$s = $this->seek();
		$inBlock = $this->inBlock;
		$this->inBlock = false; // whitespace toggle

		// talk about dirty
		while ($this->literal('%') && ($this->inBlock = true) && $this->keyword($name)) {
			$ss = $this->seek();
			if ($this->literal('(') && ($this->args($args) || true)  && $this->literal(')')) {
				// $this->throwParseError(print_r($args, 1));
			} else {
				$this->seek($ss);
			}

			$m = (object)array(
				'name' => $name,
				'type' => 'macro-expand',
				'delim' => null,
				'raw_args' => isset($args) ? $this->flattenArguments($args) : array(),
				'args' => array(),
			);

			if ($this->macroOpenBody($delim)) {
				$m->delim = $delim;
			} elseif (!$this->literal('%')) {
				break;
			}

			$this->inBlock = $inBlock;
			return true;
		}

		$this->inBlock = $inBlock;
		$this->seek($s);
		return false;
	}

	// get rid of type information for literal values
	public function flattenArguments($args) {
		$out = array();
		foreach ($args as $v) {
			if (isset($v[0])) switch($v[0]) {
				case 'string':
					$out[] = $v[2];
					break;
				default:
					$out[] = $v[1];
			} else $out[] = $v;
		}
		return $out;
	}

	// get a complete macro definition
	public function macroDefinition(&$m) {
		$s = $this->seek();
		$inBlock = $this->inBlock;

		if ($this->literal('%') && ($this->inBlock = true) && $this->keyword($name) && $this->literal('=')) {
			// arguments?
			$args = null;
			$ss = $this->seek();
			if ($this->literal('(') && $this->argsDef($_args) && $this->literal(')')) {
				$args = $_args;
			} else {
				$this->seek($ss);
			}

			if (!$this->macroOpenBody($delim)) {
				$this->inBlock = $inBlock;
				$this->seek($s);
				return false;
			}

			$args = $args ? $args : array();
			foreach ($args as &$a) $a = $a['name'];

			$m = (object)array(
				'name' => $name,
				'type' => 'macro-define',
				'args' => $args,
				'text' => array(),
				'delim' => $delim,
			);

			$this->inBlock = $inBlock;
			return true;
		}

		$this->inBlock = $inBlock;
		$this->seek($s);
		return false;
	}

	// push a new macro on the stack
	function pushMacro($macro) {
		$this->macroStack[] = $macro;
		$this->c->pushBuffer();
	}

	// pop a macro off the stack and insert into env
	function popMacro() {
		if (count($this->macroStack) == 0) return false;

		$macro = array_pop($this->macroStack);
		$content = $this->c->popBuffer();

		if ($macro->type == 'macro-expand') {
			$macro->raw_args[] = $content;
			$this->renderMacro($macro);
		} else { // create new macro
			$macro->text[] = $content;
			$this->macros[$macro->name] = $macro;
		}

		return true;
	}

	// TODO: redo all this block stuff
	public $blockStack = array();
	// push a new block onto blockStack
	function pushBlock($b) {
		if (is_array($b)) $b = (object)$b;
		$this->log("Pushing $b->type");
		$this->blockStack[] = $b;
	}

	function popBlock() {
		return array_pop($this->blockStack);
	}

	function block_if() {
		if (!$this->expression($exp)) return false;
		$this->pushBlock(array(
			'type' => 'if',
			'exp' => $exp,
			'case' => array(),
			'children' => array(
				'else' => function($self) {
					$self->case[] = array($self->exp, ob_get_clean());
					$self->exp = null;
					unset($self->children['elseif']);
					unset($self->children['else']);
					ob_start();
				},
				'elseif' => function($self, $parser) {
					$self->case[] = array($self->exp, ob_get_clean());
					if (!$parser->expression($self->exp)) {
						$parser->throwParseError('Failed to find elseif expression');
					}
					ob_start();
				},
				'end' => function($self) {
					$self->case[] = array($self->exp, ob_get_clean());
				}
			)
		));
		ob_start();

		return true;
	}

	function block_capture() {
		if (!$this->variable($vname)) return false;
		$this->pushBlock(array(
			'type' => 'capture',
			'var' => $vname,
			'children' => array(
				'end' => function($self) {
					$self->value = ob_get_clean();
				}
			)
		));

		ob_start();
		return true;
	}


	// a block is 
	// { expression|funcall }
	function block(&$outBlock) {
		$outside = $this->seek();
		$this->inBlock = true;

		if (!$this->literal('{')) return false;
		$this->inBlock = true;

		// try a value
		if ($this->expression($outBlock)) {
			// $this->throwParseError('found expression');
			goto pass;
		}

		if (!$this->keyword($func)) goto fail;

		/*
		// see if there is anything in block stack we need to take care of
		$foundChild = false;
		$outBlock = null;
		for ($i = count($this->blockStack) - 1; $i >= 0; $i--) {
			$b = $this->blockStack[$i];
			if (isset($b->children)) {
				foreach ($b->children as $tag=>$action) {
					if ($func == $tag) {
						// $this->throwParseError("found $tag");
						$this->log("Calling `$tag` for `$b->type`");
						call_user_func($action, $b, $this);
						$foundChild = true;
						break;
					}
				}
			}
			// should this be done here?
			if ($func == 'end' && $foundChild) {
				$outBlock = $this->popBlock();
				$this->log("Popping $b->type");
			}

			if ($foundChild) goto pass;
		}


		$b = null;
		foreach ($this->builtins as $bname) {
			if ($bname == $func) {
				$bfunc = 'block_'.$bname;
				if ($b = $this->$bfunc()) {
					if (is_object($b)) $outBlock = $b;
					goto pass;
				}
			}
		}
		*/

		// just a normal function?
		$this->log("Unknown `$func`");
		$this->args($args);

		$outBlock = (object)array(
			'type' => 'function',
			'func' => $func,
			'args' => is_array($args) ? $args : array()
		);
		goto pass;

		goto fail;
		pass:
		$this->inBlock = false;
		if ($this->literal('}')) return true;

		fail:
		$this->inBlock = false;
		$this->seek($outside);
		return false;
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

	function argsDef(&$dargs) {
		$args = array();

		while ($this->variable($vname, true)) {
			$args[] = $vname;
			if (!$this->literal(',')) break;
		}

		$dargs = $args;
		return true;
	}

	// expression operator expression ...
	// this ruins any native types..
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
		if ($this->match('(-?[0-9]+(\.[0-9]*)?)', $m)) {
			$v = array('number', $m[1]);
			return true;
		}

		// match a string
		// need a special compile type for this
		if ($this->string($str, $d)) {
			$v = array('string', $d, $str);
			return true;
		}

		// a macro expansion
		if ($this->macroExpansion($v)) {
			return true;
		}

		return false;
	}

	// attempt to read variable
	function variable(&$var, $simple = false) {
		$var = array('chain' => array());

		$inBlock = $this->inBlock;
		$this->inBlock = false;

		$s = $this->seek();
		if (!$this->literal('$') or !$this->keyword($var['name'])) {
			$this->seek($s);
			$this->inBlock = $inBlock;
			return false;
		}

		if (!$simple)
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

		// if this is a macro argument then mix it with argument
		if (($head = end($this->macroStack)) && $head->type == 'macro-define' && in_array($var['name'], $head->args)) {
			$var['delay'] = true;
		}

		// $var = $this->c->variable($var);
		$this->inBlock = $inBlock;
		if ($inBlock) $this->whitespace();
		return true;
	}

	function string(&$string, &$d = null) {
		$s = $this->seek();
		if ($this->literal('"', false)) {
			$delim = '"';
		} else if($this->literal("'", false)) {
			$delim = "'";
		} else {
			return false;
		}

		if (!$this->to($delim, $string)) {
			$this->seek($s);
			return false;
		}
		
		$d = $delim;
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
		if ($this->count >= strlen($this->buffer)) return false; // prevent notice

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

	// pass $str from being parsed
	function pass($str) {
		echo $str;
		$this->count += strlen($str);
	}

	// match something without consuming it
	function peek($regex, &$out = null) {
		$r = '/'.$regex.'/Ais';
		$result =  preg_match($r, $this->buffer, $out, null, $this->count);
		
		return $result;
	}

	// advance counter to next occurrence of $what
	// $until - don't include $what in advance
	function to($what, &$out, $until = false, $allowNewline = true) {
		$validChars = $allowNewline ? '.' : "[^\n]";
		if (!$this->match('('.$validChars.'*?)'.$this->preg_quote($what), $m, !$until)) return false;
		if ($until) $this->count -= strlen($what); // give back $what
		$out = $m[1];
		return true;
	}

	// try to match something on head of buffer
	function match($regex, &$out, $eatWhitespace = null, $mods = '') {
		if ($eatWhitespace === null)
			$eatWhitespace = $this->inBlock;	

		$r = '/'.$regex.($eatWhitespace ? '\s*' : '').'/Ais'.$mods;
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

	// seek to a spot in the buffer
	function seek($where = null) {
		if (!$where) return $this->count;
		else $this->count = $where;
	}

	// remove all the comments
	function clear_comments($str) {
		return preg_replace('/^\s*\/\/.*(\n|$)/m', '', $str);
	}

	function parse($str) {
		$this->buffer = $this->clear_comments($str);
		$this->count = 0;
		$this->text();
		return $this->c->done();
	}

	function throwParseError($msg = 'parse error') {
		$line = 1 + substr_count(substr($this->buffer, 0, $this->count), "\n");
		if ($this->peek("(.*?)(\n|$)", $m))
			throw new exception($msg.': failed at `'.$m[1].'` line: '.$line);
	}
}

class CompilerX {
	private $inCode = false;
	private $buffer = array(array());

	public function pushBuffer() {
		$this->buffer[] = array();
	}

	public function popBuffer() {
		$this->endCode();
		if (count($this->buffer) > 1) 
			return join(array_pop($this->buffer));
		else return false;
	}

	public function compileChunk($c) {
		switch(true) {
			case is_array($c) && isset($c['chain']): // variable
				$this->_echo($this->c_variable($c));
				break;
			default: 
				$this->code('/* unknown compile chunk `'.print_r($c, 1).'`*/');
		}
	}

	public function c_variable($v) {
		$out = "$$v[name]";
		foreach($v['chain'] as $a) {
			if ($a['type'] == 'array')
				$out .= "['".$a['name']."']";
			else
				$out .= "->".$a['name'];
		}
		return $out;
	}

	public function _echo($in) {
		$this->code("echo $in;");
	}

	public function text($str) {
		$this->endCode();
		$this->write($str);
	}

	public function code($str) {
		$this->enterCode();
		$this->write($str);
	}

	public function done() {
		$this->endCode();
		return join($this->buffer[0]);
	}

	protected function enterCode() {
		if ($this->inCode) return;
		$this->write('<?php ');
		$this->inCode = true;
	}

	protected function endCode() {
		if (!$this->inCode) return;
		$this->write(' ?>');
		$this->inCode = false;
	}

	protected function write($str) {
		$this->buffer[count($this->buffer) - 1][] = $str;
	}
}


class Compiler {
	public $parser = null;

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
	// public function code($text) { return '<?php '.$text.' ? >'; }	
	public function code($text) { return ' ['.$text.'] '; }	
	
	public function block($b) {
		switch ($b->type) {
		case 'capture':
			return $this->code('ob_start();').$b->value.
				$this->code($b->var.' = ob_get_clean();');
		case 'if':
			// return $this->code('if ('.$b->exp.') { ').$b->onTrue.$this->code('}');
			return $this->if_statement($b);
		case 'function':
			return $this->funcall($b->func, $b->args);

		}

		return "<<$b->type block>>";
	}

	// value type
	public function value($v) {
		if (isset($v['chain'])) {
			// find out if we need to mix the variable
			$v = $this->parser->subVariable($v);
			return $this->variable($v);
		} else {
			switch ($v[0]) {
			case 'string':
				return $v[1].$v[2].$v[1];
			}
		}
	}

	public function funcall($name, $args) {
		// return print_r($args, 1);
		// return $this->write($name.'('.implode(', ',$args).')');
		return $this->write($name.'('.implode(', ', array_map(array($this, 'value'), $args)).')');
	}

	public function if_statement($b) {
		ob_start();
		$first = true;
		foreach ($b->case as $case) {
			if ($first) {
				$first = false;
				echo $this->code('if ('.$case[0].') {');
			} else {
				if ($case[0]) { // elseif
					echo $this->code('} elseif ('.$case[0].') {');
				} else {
					echo $this->code('} else {');
				}
			}
			echo $case[1];
		}
		echo $this->code('}');

		return ob_get_clean();
	}
}

?>
