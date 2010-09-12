* investigate nested macros
* variable prefix
* importing



alpha
<?php if ($hello->world == 4): ?>
	Where is this data?. Well, looks like it is <?php echo $here; ?>
<?php elseif (43434): ?>
	This is the elseif
<?php else: ?>
	This is the else caluse, what do you think?
<?php endif; ?>
beta

hello world this is the father

this is $literal

<?php foreach ($posts as $post): ?>
	I really like this <?php echo $post; ?>
<?php endforeach; ?>

<?php foreach ($list as $key=>$value): ?>
	Here is the <?php echo $key; ?> and the <?php echo $value; ?>.
<?php endforeach; ?>

hello world this is the father

{<?php echo $variable->something['hello']->world; ?> + 12}

<?php echo filter($variable); ?>
<?php echo filter($variable); ?>

This is the first arg-filter: <?php echo filter($variable,"hello world","test"); ?>
This <?php echo filter($variable,1,2,3); ?> will have a filter with it

this is a function: <?php echo link("test","three"); ?>

function goes here: 
<?php echo func(1,2,3); ?> 

test