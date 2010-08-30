
* blocks
* investigate nested macros
* importing

alpha
{if $hello.world == 4}
	Where is this data?. Well, looks like it is $here
{elseif 43434}
	This is the elseif
{else}
	This is the else caluse, what do you think?
{end}
beta

{literal}this is $literal{end}

{for $post in $posts}
	I really like this $post
{end}

{for $key,$value in $list}
	Here is the $key and the $value.
{end}

{$variable.something|hello.world + 23}

{* this is a comment *}

