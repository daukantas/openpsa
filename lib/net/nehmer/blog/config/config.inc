// Schema database to use
'schemadb' => 'file:/net/nehmer/blog/config/schemadb_default.inc',
'schemadbs'      => array
(
    'file:/net/nehmer/blog/config/schemadb_default.inc' => 'Default Schema Database',
),
'schemadb_link' => 'file:/net/nehmer/static/config/schemadb_link.inc',
'schemadb_config' => 'file:/net/nehmer/blog/config/schemadb_config.inc',

// Use the content from another topic instead of the current one.
// Do not overwrite this except on a per-topic level!!!
'symlink_topic' => null,
'list_from_folders'     => null,   // List of the event topics displayed in this topic
// Comma-separated list of categories to limit the listing (if empty, all will be listed)
'list_from_folders_categories' => '',

'view_in_url' => false,
'enable_scheduled_publishing' => false,

// Specifies how many entries should be shown in the index page.
'index_entries' => 8,

'index_fulltext' => false,

// RSS export
'rss_enable' => true,  // enable RSS export on rss/latest.xml
'rss_count' => 20, // show that many items by default (if called 'rss.xml' without any count)
'rss_title' => null,  // RSS feed title, leave null to use the topic title
'rss_description' => '',
'rss_webmaster' => '',
'rss_language' => '',
'rss_use_content' => true, // Set this to false to use only the abstract field instead of the content field.
'rss_use_image' => false, // Set this to true to include the image field into RSS content
'feeds_in_navigation' => false,

// Archive status
'archive_enable' => true,
'archive_item_order' => 'ASC',
'archive_in_navigation' => true,
'archive_years_in_navigation' => false,
'archive_years_enable' => true,

'comments_enable' => false,
'ajax_comments_enable' => false,
'comments_topic' => '',

// Navigation
'show_latest_in_navigation' => false,  // Set this to true if you want to show latest news in navigation
'show_navigation_pseudo_leaves' => true,   // Set this to false if you want to hide archive and feeds navigation items

// Set this to true if you want to enable on-site AJAX editing
'enable_ajax_editing' => midcom::get()->config->get('enable_ajax_editing'),

// Comma-separated list of categories enabled in this blog
'categories' => '',
'categories_custom_enable' => true,
'categories_in_navigation' => false,

'rss_subscription_enable' => false,
'link_to_external_url' => false,

// Page linking system
'enable_article_links' => false,
'enable_editing_of_linked_articles' => false,
'include_linked_articles_to_feeds' => true,

// Creation callback snippet for defining site-specific function
'callback_snippet' => null,

// Callback function will be called after creation e.g. to provide interface for page linking system
// $callback_function($article, $topic)
'callback_function' => null,

'language' => null,

'disable_permalinks' => false,
'disable_indexing' => false,

'simple_name_handling' => true,
