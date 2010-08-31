Here are some variables:

<?php echo $hello; ?>
<?php echo $this->is->an->object; ?>
<?php echo $here['is']['an']['array']; ?>

<?php echo $this->combines['hello']->both; ?>

Letters directly at the <?php echo $en; ?>d

-- <?php echo $hello->world; ?>
-- <?php echo $world['hello']; ?>


<?php echo function($this->is->filtered); ?>

<?php echo link($use_args,"hello","world"); ?>
<?php echo link($use_args,"hello","world"); ?>


<b><?php echo $text; ?></b>
<em><?php echo $test->world; ?></em>
<u><?php echo $test['world']; ?></u>
<u><?php echo filtered($test['world']); ?></u>
<u><?php echo filtered($test['world'],1,2,3); ?></u>