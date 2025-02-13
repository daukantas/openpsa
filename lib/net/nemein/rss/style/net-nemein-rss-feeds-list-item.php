<?php
$topic_prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
$prefix = $topic_prefix . '__feeds/rss/';

echo "<li><a href=\"{$data['feed']->url}\"><img src=\"" . MIDCOM_STATIC_URL . "/net.nemein.rss/feed-icon-14x14.png\" alt=\"{$data['feed']->url}\" title=\"{$data['feed']->url}\" /></a>";
if ($data['feed']->can_do('midgard:update'))
{
    echo "<a href=\"{$prefix}edit/{$data['feed']->guid}/\">{$data['feed']->title}</a>\n";
}
else
{
    echo "{$data['feed']->title}\n";
}
echo "    <ul class=\"details\">\n";

switch ($data['topic']->component)
{
    case 'net.nehmer.blog':
        $qb = midcom_db_article::new_query_builder();
        $qb->add_constraint('topic', '=', $data['topic']->id);
        $qb->add_constraint('extra1', 'LIKE', "%|{$data['feed_category']}|%");
        $data['feed_items'] = $qb->count_unchecked();
        echo "        <li><a href=\"{$topic_prefix}category/{$data['feed_category']}/\">" . sprintf($data['l10n']->get('%s items'), $data['feed_items']) . "</a></li>\n";
        break;
}
$formatter = $data['l10n']->get_formatter();
if ($data['feed']->latestupdate)
{
    echo "        <li>" . sprintf($data['l10n']->get('latest item from %s'), $formatter->datetime($data['feed']->latestupdate)) . "</li>\n";
}
if ($data['feed']->latestfetch)
{
    echo "        <li>" . sprintf($data['l10n']->get('latest fetch %s'), $formatter->datetime($data['feed']->latestfetch)) . "</li>\n";
}
echo "    </ul>\n";
echo $data['feed_toolbar']->render();
echo "</li>\n";
?>