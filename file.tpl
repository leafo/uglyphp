
#include "something.tpl"
{include "something.tpl"}

%call = ($s) literal {
	<div class="bold">{html "function", $s|e|x}</div>
}

%call($what)%


