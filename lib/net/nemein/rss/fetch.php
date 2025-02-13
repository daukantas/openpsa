<?php
/**
 * @package net.nemein.rss
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * RSS and Atom feed fetching class. Caches the fetched items as articles
 * in net.nehmer.blog or events in net.nemein.calendar
 *
 * @package net.nemein.rss
 */
class net_nemein_rss_fetch extends midcom_baseclasses_components_purecode
{
    /**
     * The last error reported by SimplePie, if any
     */
    public $lasterror;

    /**
     * The feed object we're fetching
     */
    private $_feed;

    /**
     * Property of midcom_db_article we're using for storing the feed item GUIDs
     */
    private $_guid_property = 'extra2';

    /**
     * Current node we're importing to
     *
     * @var midcom_db_topic
     */
    private $_node = null;

    /**
     * Initializes the class with a given feed
     */
    public function __construct(net_nemein_rss_feed_dba $feed)
    {
        $this->_feed = $feed;

        $this->_node = new midcom_db_topic($this->_feed->node);

        parent::__construct();
    }

    /**
     * @return SimplePie
     */
    public static function get_parser()
    {
        $parser = new SimplePie;
        $parser->get_registry()->register('Item', 'net_nemein_rss_parser_item');
        $parser->set_output_encoding(midcom::get()->i18n->get_current_charset());
        $parser->set_cache_location(midcom::get()->config->get('midcom_tempdir'));
        $parser->enable_cache(false); //enabling cache leads to segfaults for some reason
        if (version_compare(PHP_VERSION, '5.4', '>='))
        {
            /**
             * Keep parser instances around until shutdown,
             * if they are deleted before, this triggers a segfault under PHP 5.4
             * @see https://github.com/simplepie/simplepie/issues/284
             */
            static $parsers = array();
            $parsers[] = $parser;
        }
        return $parser;
    }

    /**
     * Static method for actually fetching a feed
     *
     * @param string $url The URL to fetch
     * @return SimplePie
     */
    public static function raw_fetch($url)
    {
        $parser = self::get_parser();
        $parser->set_feed_url($url);
        $parser->init();
        return $parser;
    }

    /**
     * Fetch given RSS or Atom feed
     *
     * @param array Array of normalized feed items
     */
    function fetch()
    {
        $parser = self::raw_fetch($this->_feed->url);
        if ($parser->error())
        {
            $this->lasterror = $parser->error();
            return array();
        }
        if (!empty($parser->data['headers']['etag']))
        {
            // Etag checking
            $etag = trim($parser->data['headers']['etag']);

            $feed_etag = $this->_feed->get_parameter('net.nemein.rss', 'etag');
            if (   !empty($feed_etag)
                && $feed_etag == $etag)
            {
                // Feed hasn't changed, skip updating
                debug_add("Feed {$this->_feed->url} has not changed since " . date('c', $this->_feed->latestfetch), MIDCOM_LOG_WARN);
                return array();
            }

            $this->_feed->set_parameter('net.nemein.rss', 'etag', $etag);
        }

        $this->_feed->latestfetch = time();
        $this->_feed->_use_activitystream = false;
        $this->_feed->_use_rcs = false;
        $this->_feed->update();

        return $parser->get_items();
    }

    /**
     * Fetches and imports items in the feed
     */
    function import()
    {
        if (!$this->_node->component)
        {
            return array();
        }

        $items = $this->fetch();

        if (count($items) == 0)
        {
            // This feed didn't return any items, skip
            return array();
        }

        // Reverse items so that creation times remain in correct order even for feeds without timestamps
        $items = array_reverse($items);

        foreach ($items as $item)
        {
            if ($guid = $this->import_item($item))
            {
                $item->set_local_guid($guid);
                debug_add("Imported item " . $item->get_id() . ' as ' . $guid, MIDCOM_LOG_INFO);
            }
            else
            {
                debug_add("Failed to import item " . $item->get_id() . ': ' . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            }
        }

        $this->clean($items);

        return array_reverse($items);
    }

    /**
     * Imports a feed item into the database
     *
     * @param net_nemein_rss_parser_item $item Feed item as provided by SimplePie
     */
    function import_item(net_nemein_rss_parser_item $item)
    {
        switch ($this->_node->component)
        {
            case 'net.nehmer.blog':
                return $this->import_article($item);

            case 'net.nemein.calendar':
                //return $this->import_event($item);
                throw new midcom_error('Event importing has to be re-implemented with SimplePie API');

            default:
                /**
                 * This will totally break cron if someone made something stupid (like changed folder component)
                 * on folder that had subscriptions
                 *
                throw new midcom_error("RSS fetching for component {$this->_node->component} is unsupported");
                 */
                debug_add("RSS fetching for component {$this->_node->component} is unsupported", MIDCOM_LOG_ERROR);
                return false;
        }
    }

    /**
     * Imports an item as a news article
     */
    private function import_article(net_nemein_rss_parser_item $item)
    {
        $guid = $item->get_id();
        $title = $item->get_title();

        if (   (   empty($title)
                || trim($title) == '...')
            && empty($guid))
        {
            // Something wrong with this entry, skip it
            return false;
        }

        $guid_property = $this->_guid_property;
        $qb = midcom_db_article::new_query_builder();
        $qb->add_constraint('topic', '=', $this->_feed->node);
        // TODO: Move this to a parameter in Midgard 1.8
        $qb->add_constraint($guid_property, '=', substr($guid, 0, 255));
        $articles = $qb->execute();
        if (count($articles) > 0)
        {
            // This item has been imported already earlier. Update
            $article = $articles[0];
        }
        else
        {
            // Check against duplicate hits that may come from different feeds
            if ($item->get_link())
            {
                $qb = midcom_db_article::new_query_builder();
                $qb->add_constraint('topic', '=', $this->_feed->node);
                $qb->add_constraint('url', '=', $item->get_link());
                $hits = $qb->count();
                if ($hits > 0)
                {
                    // Dupe, skip
                    return false;
                }
            }

            // This is a new item
            $article = new midcom_db_article();
            $article->topic = $this->_feed->node;
        }
        $article->allow_name_catenate = true;

        $updated = false;

        // Copy properties
        if ($article->title != $title)
        {
            $article->title = $title;
            $updated = true;
        }

        // FIXME: This breaks with URLs longer than 255 chars
        if ($article->$guid_property != $guid)
        {
            $article->$guid_property = $guid;
            $updated = true;
        }

        if ($article->content != $item->get_content())
        {
            $article->content = $item->get_content();
            $updated = true;
        }

        if ($article->url != $item->get_link())
        {
            $article->url = $item->get_link();
            $updated = true;
        }

        $feed_category = 'feed:' . md5($this->_feed->url);
        $orig_extra1 = $article->extra1;
        $article->extra1 = "|{$feed_category}|";

        $article->_activitystream_verb = 'http://community-equity.org/schema/1.0/clone';
        $article->_rcs_message = sprintf(midcom::get()->i18n->get_string('%s was imported from %s', 'net.nemein.rss'), $article->title, $this->_feed->title);

        $categories = $item->get_categories();
        if (is_array($categories))
        {
            // Handle categories provided in the feed
            foreach ($categories as $category)
            {
                // Clean up the categories and save
                $category = str_replace('|', '_', trim($category->get_term()));
                $article->extra1 .= "{$category}|";
            }
        }

        if ($orig_extra1 != $article->extra1)
        {
            $updated = true;
        }

        // Try to figure out item author
        if (   $this->_feed->forceauthor
            && $this->_feed->defaultauthor)
        {
            // Feed has a "default author" set, use it
            $article_author = new midcom_db_person($this->_feed->defaultauthor);
        }
        else
        {
            $article_author = $this->match_item_author($item);
            $fallback_person_id = 1;
            if (   !$article_author
                || $article_author->id == $fallback_person_id)
            {
                if ($this->_feed->defaultauthor)
                {
                    // Feed has a "default author" set, use it
                    $article_author = new midcom_db_person($this->_feed->defaultauthor);
                }
                else
                {
                    // Fall back to "Midgard Admin" just in case
                    $fallback_author = new midcom_db_person($fallback_person_id);
                    $article_author = $fallback_author;
                }
            }
        }

        if (!empty($article_author->guid))
        {
            if ($article->metadata->authors != "|{$article_author->guid}|")
            {
                $article->metadata->set('authors', "|{$article_author->guid}|");
                $updated = true;
            }
        }

        // Try to figure out item publication date
        $article_date = $item->get_date('U');

        $article_data_tweaked = false;
        if (!$article_date)
        {
            $article_date = time();
            $article_data_tweaked = true;
        }

        if ($article_date > $this->_feed->latestupdate)
        {
            // Cache "latest updated" time to feed
            $this->_feed->latestupdate = $article_date;
            $this->_feed->_use_activitystream = false;
            $this->_feed->_use_rcs = false;
            $this->_feed->update();
        }

        // Safety, make sure we have sane name (the allow_catenate was set earlier, so this will not clash
        if (empty($article->name))
        {
            $generator = midcom::get()->serviceloader->load('midcom_core_service_urlgenerator');
            $article->name = $generator->from_string($article->title);
            $updated = true;
        }
        if ($article->id)
        {
            if (   $article->metadata->published != $article_date
                && !$article_data_tweaked)
            {
                $article->metadata->published = $article_date;
                $updated = true;
            }

            if ($updated)
            {
                $article->allow_name_catenate = true;
                if (!$article->update())
                {
                    return false;
                }
            }
        }
        else
        {
            // This is a new item
            $node = new midcom_db_topic($this->_feed->node);
            $node_lang_code = $node->get_parameter('net.nehmer.blog', 'language');
            if ($node->get_parameter('net.nehmer.blog', 'symlink_topic') != '')
            {
                try
                {
                    $symlink_topic = new midcom_db_topic($node->get_parameter('net.nehmer.blog', 'symlink_topic'));
                    $article->topic = $symlink_topic->id;
                }
                catch (midcom_error $e)
                {
                    $e->log();
                }
            }
            if ($node_lang_code != '')
            {
                $lang_id = midcom::get()->i18n->code_to_id($node_lang_code);
                $article->lang = $lang_id;
            }
            $article->allow_name_catenate = true;
            if (!$article->create())
            {
                return false;
            }
        }

        if ($this->_feed->autoapprove)
        {
            $article->metadata->approve();
        }

        $this->_parse_tags($article);
        $this->_parse_parameters($article, $item);

        // store <link rel="replies"> url in parameter
        if ($item->get_link(0, 'replies'))
        {
            $article->set_parameter('net.nemein.rss', 'replies_url', $item->get_link(0, 'replies'));
        }

        return $article->guid;
    }

    /**
     * Cleans up old, removed items from feeds
     *
     * @param array $items Feed item as provided by SimplePie
     */
    function clean($items)
    {
        if ($this->_feed->keepremoved)
        {
            // This feed is set up so that we retain items removed from array
            return false;
        }

        // Create array of item GUIDs
        $item_guids = array();
        foreach ($items as $item)
        {
            $item_guids[] = $item->get_id();
        }

        // Find articles resulting from this feed
        $qb = midcom_db_article::new_query_builder();
        $feed_category = md5($this->_feed->url);
        $qb->add_constraint('extra1', 'LIKE', "%|feed:{$feed_category}|%");
        $qb->add_constraint($this->_guid_property, 'NOT IN', $item_guids);
        $local_items = $qb->execute_unchecked();
        $purge_guids = array();
        foreach ($local_items as $item)
        {
            if (   midcom::get()->componentloader->is_installed('net.nemein.favourites')
                && midcom::get()->componentloader->load_graceful('net.nemein.favourites'))
            {
                // If it has been favorited keep it
                $qb = net_nemein_favourites_favourite_dba::new_query_builder();
                $qb->add_constraint('objectGuid', '=', $item->guid);
                if ($qb->count_unchecked() > 0)
                {
                    continue;
                    // Skip deleting this one
                }
            }

            $purge_guids[] = $item->guid;
            $item->delete();
        }

        midcom_baseclasses_core_dbobject::purge($purge_guids, 'midgard_article');
    }

    /**
     * Parses author formats used by different feed standards and
     * and returns the information
     *
     * @param net_nemein_rss_parser_item $item Feed item as provided by SimplePie
     * @return Array Information found
     */
    function parse_item_author(net_nemein_rss_parser_item $item)
    {
        $author_info = array();

        $author = $item->get_author();

        // First try dig up any information about the author possible
        if (!empty($author))
        {
            $name = $author->get_name();
            $email = $author->get_email();
            if (!empty($name))
            {
                $name = html_entity_decode($name, ENT_QUOTES, midcom::get()->i18n->get_current_charset());
                // Atom feed, the value can be either full name or username
                $author_info['user_or_full'] = $name;
            }
            else
            {
                $name = html_entity_decode($email, ENT_QUOTES, midcom::get()->i18n->get_current_charset());
            }

            if (!preg_match('/(<|\()/', $name))
            {
                $author_info['user_or_full'] = $name;
            }
            else
            {
                if (strstr($name, '<'))
                {
                    // The classic "Full Name <email>" format
                    $regex = '/(?<fullname>.+) <?(?<email>[a-zA-Z0-9_.-]+?@[a-zA-Z0-9_.-]+)>?[ ,]?/';
                }
                else
                {
                    // The classic "email (Full Name)" format
                    $regex = '/^(?<email>[a-zA-Z0-9_.-]+?@[a-zA-Z0-9_.-]+) \((?<fullname>.+)\)$/';
                }
                if (preg_match($regex, $name, $matches))
                {
                    $author_info['email'] = $matches['email'];
                    $author_info['user_or_full'] = $matches['fullname'];
                }
            }
        }

        if (isset($author_info['user_or_full']))
        {
            if (strstr($author_info['user_or_full'], ' '))
            {
                // This value has a space in it, assuming full name
                $author_info['full_name'] = $author_info['user_or_full'];
            }
            else
            {
                $author_info['username'] = $author_info['user_or_full'];
            }
            unset($author_info['user_or_full']);
        }

        return $author_info;
    }

    /**
     * Parses author formats used by different feed standards and
     * tries to match to persons in database.
     *
     * @param net_nemein_rss_parser_item $item Feed item as provided by SimplePie
     * @return midcom_db_person Person object matched, or null
     */
    function match_item_author(net_nemein_rss_parser_item $item)
    {
        // Parse the item for author information
        $author_info = $this->parse_item_author($item);

        if (!empty($author_info['email']))
        {
            // Email is a pretty good identifier, start with it
            $person_qb = midcom_db_person::new_query_builder();
            $person_qb->add_constraint('email', '=', $author_info['email']);
            $persons = $person_qb->execute();
            if (count($persons) > 0)
            {
                return $persons[0];
            }
        }

        if (!empty($author_info['username']))
        {
            if ($person = midcom::get()->auth->get_user_by_name($author_info['username']))
            {
                return $person->get_storage();
            }
        }

        if (!empty($author_info['full_name']))
        {
            $name_parts = explode(' ', $author_info['full_name']);
            if (count($name_parts) > 1)
            {
                // We assume the western format Firstname Lastname
                $firstname = $name_parts[0];
                $lastname = $name_parts[1];

                $person_qb = midcom_db_person::new_query_builder();
                $person_qb->add_constraint('firstname', '=', $firstname);
                $person_qb->add_constraint('lastname', '=', $lastname);
                $persons = $person_qb->execute();
                if (count($persons) > 0)
                {
                    return $persons[0];
                }
            }
        }

        return null;
    }

    /**
     * Parses additional metadata in RSS item and sets parameters accordingly
     *
     * @param midcom_core_dbaobject $article Imported article
     * @param net_nemein_rss_parser_item $item Feed item as provided by SimplePie
     */
    private function _parse_parameters(midcom_core_dbaobject $article, net_nemein_rss_parser_item $item)
    {
        foreach ($item->get_enclosures() as $enclosure)
        {
            $article->set_parameter('net.nemein.rss:enclosure', 'url', $enclosure->get_link());
            $article->set_parameter('net.nemein.rss:enclosure', 'duration', $enclosure->get_duration());
            $article->set_parameter('net.nemein.rss:enclosure', 'mimetype', $enclosure->get_type());
        }
    }

    /**
     * Parses rel-tag links in article content and tags the object based on them
     *
     * @param midgard_article $article Imported article
     */
    private function _parse_tags($article, $field = 'content')
    {
        $html_tags = org_openpsa_httplib_helpers::get_anchor_values($article->$field, 'tag');
        $tags = array();

        if (count($html_tags) > 0)
        {
            foreach ($html_tags as $html_tag)
            {
                if (!$html_tag['value'])
                {
                    // No actual tag specified, skip
                    continue;
                }

                $tag = strtolower(strip_tags($html_tag['value']));
                $tags[$tag] = $html_tag['href'];
            }

            return net_nemein_tag_handler::tag_object($article, $tags);
        }
    }
}
