
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

{literal}
	This is some very literal text that I am writing right here.....
	{if $something}
		what is $going on here
	{else}
{end}

{foreach $posts as $post}
	I really like this $post
{end}

{$variable.something|hello.world + 23}

{* this is a comment *}

