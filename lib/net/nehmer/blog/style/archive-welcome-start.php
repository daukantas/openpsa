<?php
$summary = sprintf($data['l10n']->get('there is a total of %d posts.'), $data['total_count']);

if ($data['first_post'])
{
    $summary .= ' ' . sprintf($data['l10n']->get('first post was made on %s.'),
    $data['first_post']->format($data['l10n_midcom']->get('short date')));
}
?>

<h1><?php echo $data['topic']->extra; ?>: <?php $data['l10n']->show('archive'); ?></h1>

<p>&(summary);</p>