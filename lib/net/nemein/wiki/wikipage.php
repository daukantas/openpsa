<?php
/**
 * @package net.nemein.wiki
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Wiki note helper class to be used by other components
 *
 * @package net.nemein.wiki
 */
class net_nemein_wiki_wikipage extends midcom_db_article
{
    public $autodelete_dependents = array
    (
        'net_nemein_wiki_link_dba' => 'frompage'
    );

    public function _on_loaded()
    {
        // Backwards compatibility
        if ($this->name == '')
        {
            $generator = midcom::get()->serviceloader->load('midcom_core_service_urlgenerator');
            $this->name = $generator->from_string($this->title);
            $this->update();
        }
    }

    public function _on_creating()
    {
        if (   $this->title == ''
            || !$this->topic)
        {
            // We must have wikiword and topic at this stage
            return false;
        }

        // Check for duplicates
        $qb = net_nemein_wiki_wikipage::new_query_builder();
        $qb->add_constraint('topic', '=', $this->topic);
        $qb->add_constraint('title', '=', $this->title);
        if ($qb->count() > 0)
        {
            midcom_connection::set_error(MGD_ERR_OBJECT_NAME_EXISTS);
            return false;
        }

        // Generate URL-clean name
        if ($this->name != 'index')
        {
            $generator = midcom::get()->serviceloader->load('midcom_core_service_urlgenerator');
            $this->name = $generator->from_string($this->title);
        }
        return true;
    }

    public function _on_updating()
    {
        if (midcom::get()->auth->user)
        {
            // Place current user in the page authors list
            $authors = explode('|', substr($this->metadata->authors, 1, -1));
            if (!in_array(midcom::get()->auth->user->guid, $authors))
            {
                $authors[] = midcom::get()->auth->user->guid;
                $this->metadata->authors = '|' . implode('|', $authors) . '|';
            }
        }
        return parent::_on_updating();
    }

    public function _on_updated()
    {
        parent::_on_updated();
        $this->_update_watchers();
        $this->_update_link_cache();
    }

    /**
     * Caches links in the wiki page into database for faster "what links here" queries
     */
    private function _update_link_cache()
    {
        $parser = new net_nemein_wiki_parser($this);
        $links_in_content = $parser->find_links_in_content();

        $qb = net_nemein_wiki_link_dba::new_query_builder();
        $qb->add_constraint('frompage', '=', $this->id);
        $links_in_db = $qb->execute();

        // Check links in DB versus links in content to see what needs to be removed
        foreach ($links_in_db as $link)
        {
            if (!array_key_exists($link->topage, $links_in_content))
            {
                // This link is not any more in content, remove
                $link->delete();
                continue;
            }
            //no change for this link
            unset($links_in_content[$link->topage]);
        }

        // What is still left needs to be added
        $links_in_content = array_keys($links_in_content);
        foreach ($links_in_content as $wikilink)
        {
            $link = new net_nemein_wiki_link_dba();
            $link->frompage = $this->id;
            $link->topage = $wikilink;
            debug_add("Creating net_nemein_wiki_link_dba: from page #{$link->frompage}, to page: '$link->topage'");
            $link->create();
        }
    }

    function list_watchers()
    {
        $topic = new midcom_db_topic($this->topic);
        // Get list of people watching this page
        $watchers = array();
        $qb = new midgard_query_builder('midgard_parameter');
        $qb->add_constraint('domain', '=', 'net.nemein.wiki:watch');
        $qb->begin_group('OR');
            // List people watching the whole wiki
            $qb->add_constraint('parentguid', '=', $topic->guid);
            // List people watching this particular page
            $qb->add_constraint('parentguid', '=', $this->guid);
        $qb->end_group();
        $watcher_params = $qb->execute();

        foreach ($watcher_params as $parameter)
        {
            if (in_array($parameter->name, $watchers))
            {
                // We found this one already, skip
                continue;
            }

            $watchers[] = $parameter->name;
        }
        return $watchers;
    }

    private function _update_watchers()
    {
        $watchers = $this->list_watchers();
        if (empty($watchers))
        {
            return;
        }

        $diff = $this->_get_diff();
        if (empty($diff))
        {
            // No sense to email empty diffs
            return;
        }

        // Construct the message
        $message = array();
        $user_string = midcom::get()->i18n->get_string('anonymous', 'net.nemein.wiki');
        if (midcom::get()->auth->user)
        {
            $user = midcom::get()->auth->user->get_storage();
            $user_string = $user->name;
        }
        // Title for long notifications
        $message['title'] = sprintf(midcom::get()->i18n->get_string('page %s has been updated by %s', 'net.nemein.wiki'), $this->title, $user_string);
        // Content for long notifications
        $message['content']  = "{$message['title']}\n\n";

        // TODO: Get RCS diff here
        $message['content'] .= midcom::get()->i18n->get_string('page modifications', 'net.nemein.wiki') . ":\n";
        $message['content'] .= "\n{$diff}\n\n";

        $message['content'] .= midcom::get()->i18n->get_string('link to page', 'net.nemein.wiki') . ":\n";
        $message['content'] .= midcom::get()->permalinks->create_permalink($this->guid);

        // Content for short notifications
        $topic = new midcom_db_topic($this->topic);
        $message['abstract'] = sprintf(midcom::get()->i18n->get_string('page %s has been updated by %s in wiki %s', 'net.nemein.wiki'), $this->title, $user_string, $topic->extra);

        debug_add("Processing list of Wiki subscribers");

        // Send the message out
        foreach ($watchers as $recipient)
        {
            debug_add("Notifying {$recipient}...");
            org_openpsa_notifications::notify('net.nemein.wiki:page_updated', $recipient, $message);
        }
    }

    private function _get_diff($field = 'content')
    {
        // Load the RCS handler
        $rcs = midcom::get()->rcs;
        $rcs_handler = $rcs->load_handler($this);
        if (!$rcs_handler)
        {
            return null;
        }

        // Find out what versions to diff
        $history = $rcs_handler->list_history_numeric();
        if (count($history) < 2)
        {
            return '';
        }
        $this_version = $history[0];
        $prev_version = $history[1];

        try
        {
            $diff_fields = $rcs_handler->get_diff($prev_version, $this_version, 'unified');
        }
        catch (midcom_error $e)
        {
            $e->log();
            return '';
        }

        if (!array_key_exists('diff', $diff_fields[$field]))
        {
            // No differences
            return '';
        }

        return $diff_fields[$field]['diff'];
    }
}
