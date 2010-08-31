
Here are some variables:

$hello
$this.is.an.object
$here[is][an][array]

$this.combines[hello].both

Letters directly at the {$en}d

-- {$hello.world}
-- {$world[hello]}


$this.is.filtered|function

$use_args|link("hello", "world")
{$use_args|link("hello", "world")}


<b>$text</b>
<em>$test.world</em>
<u>$test[world]</u>
<u>$test[world]|filtered</u>
<u>$test[world]|filtered(1,2,3)</u>


