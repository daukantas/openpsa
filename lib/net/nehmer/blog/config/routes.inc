// Index
'index' => Array
(
    'handler' => array('net_nehmer_blog_handler_index', 'index'),
),
'latest' => Array
(
    'handler' => array('net_nehmer_blog_handler_index', 'index'),
    'fixed_args' => 'latest',
    'variable_args' => 1,
),

// Handler for /ajax/latest/<number>
'ajax-latest' => Array
(
    'handler' => array('net_nehmer_blog_handler_index', 'index'),
    'fixed_args' => array('ajax', 'latest'),
    'variable_args' => 1,
),

// Handler for /category/<category>
'index-category' => Array
(
    'handler' => array('net_nehmer_blog_handler_index', 'index'),
    'fixed_args' => 'category',
    'variable_args' => 1,
),
// Handler for /category/latest/<category>/<number>
'latest-category' => Array
(
    'handler' => array('net_nehmer_blog_handler_index', 'index'),
    'fixed_args' => array('category', 'latest'),
    'variable_args' => 2,
),

// Various Feeds and their index page
'feed-index' => Array
(
    'handler' => array('net_nehmer_blog_handler_feed', 'index'),
    'fixed_args' => 'feeds',
),
'feed-category-rss2' => Array
(
    'handler' => array('net_nehmer_blog_handler_feed', 'feed'),
    'fixed_args' => array('feeds', 'category'),
    'variable_args' => 1,
),
'feed-rss2' => Array
(
    'handler' => array('net_nehmer_blog_handler_feed', 'feed'),
    'fixed_args' => 'rss.xml',
),
'feed-rss1' => Array
(
    'handler' => array('net_nehmer_blog_handler_feed', 'feed'),
    'fixed_args' => 'rss1.xml',
),
'feed-rss091' => Array
(
    'handler' => array('net_nehmer_blog_handler_feed', 'feed'),
    'fixed_args' => 'rss091.xml',
),
'feed-atom' => Array
(
    'handler' => array('net_nehmer_blog_handler_feed', 'feed'),
    'fixed_args' => 'atom.xml',
),

// The Archive
'archive-welcome' => Array
(
    'handler' => array('net_nehmer_blog_handler_archive', 'welcome'),
    'fixed_args' => 'archive',
),
'archive-year' => Array
(
    'handler' => array('net_nehmer_blog_handler_archive', 'list'),
    'fixed_args' => array('archive', 'year'),
    'variable_args' => 1,
),
'archive-year-category' => Array
(
    'handler' => array('net_nehmer_blog_handler_archive', 'list'),
    'fixed_args' => array('archive', 'year'),
    'variable_args' => 2,
),
'archive-month' => Array
(
    'handler' => array('net_nehmer_blog_handler_archive', 'list'),
    'fixed_args' => array('archive', 'month'),
    'variable_args' => 2,
),

// Administrative stuff
'edit' => Array
(
    'handler' => array('net_nehmer_blog_handler_admin', 'edit'),
    'fixed_args' => 'edit',
    'variable_args' => 1,
),
'delete' => Array
(
    'handler' => array('net_nehmer_blog_handler_admin', 'delete'),
    'fixed_args' => 'delete',
    'variable_args' => 1,
),
'delete_link' => array
(
    'handler' => array('net_nehmer_blog_handler_link', 'delete'),
    'fixed_args' => array('delete', 'link'),
    'variable_args' => 1,
),
'create_link' => Array
(
    'handler' => array('net_nehmer_blog_handler_link', 'create'),
    'fixed_args' => array('create', 'link'),
),

'create' => Array
(
    'handler' => array('net_nehmer_blog_handler_create', 'create'),
    'fixed_args' => 'create',
    'variable_args' => 1,
),

'config' => Array
(
    'handler' => array('net_nehmer_blog_handler_configuration', 'config'),
    'fixed_args' => 'config',
),

'config_recreate' => Array
(
    'handler' => array('net_nehmer_blog_handler_configuration', 'recreate'),
    'fixed_args' => array('config', 'recreate'),
),

// View article (might be changed by config setting view_in_url)
'view-raw' => Array
(
    'handler' => array('net_nehmer_blog_handler_view', 'view'),
    'fixed_args' => 'raw',
    'variable_args' => 1,
),
'view' => Array
(
    'handler' => array('net_nehmer_blog_handler_view', 'view'),
    'variable_args' => 1,
),
