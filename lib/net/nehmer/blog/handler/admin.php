<?php
/**
 * @package net.nehmer.blog
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * n.n.blog admin page handler
 *
 * @package net.nehmer.blog
 */

class net_nehmer_blog_handler_admin extends midcom_baseclasses_components_handler
{
    /**
     * The content topic to use
     *
     * @var midcom_db_topic
     */
    private $_content_topic = null;

    /**
     * The article to operate on
     *
     * @var midcom_db_article
     */
    private $_article = null;

    /**
     * The Controller of the article used for editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     */
    private $_controller = null;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var Array
     */
    private $_schemadb = null;

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['article'] = $this->_article;
        $this->_request_data['controller'] = $this->_controller;

        // Populate the toolbar
        if ($this->_article->can_do('midgard:update'))
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "edit/{$this->_article->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
        }

        if ($this->_article->can_do('midgard:delete'))
        {
            $workflow = $this->get_workflow('delete', array('object' => $this->_article));
            $this->_view_toolbar->add_item($workflow->get_button("delete/{$this->_article->guid}/"));
        }
    }

    /**
     * Maps the content topic from the request data to local member variables.
     */
    public function _on_initialize()
    {
        $this->_content_topic = $this->_request_data['content_topic'];
    }

    /**
     * Loads and prepares the schema database.
     *
     * Special treatment is done for the name field, which is set readonly for non-admins
     * if the simple_name_handling config option is set. (using an auto-generated urlname based
     * on the title, if it is missing.)
     *
     * The operations are done on all available schemas within the DB.
     */
    private function _load_schemadb()
    {
        $this->_schemadb =& $this->_request_data['schemadb'];
        if (   $this->_config->get('simple_name_handling')
            && ! midcom::get()->auth->admin)
        {
            foreach (array_keys($this->_schemadb) as $name)
            {
                $this->_schemadb[$name]->fields['name']['readonly'] = true;
            }
        }
    }

    /**
     * Internal helper, loads the controller for the current article. Any error triggers a 500.
     */
    private function _load_controller()
    {
        $this->_load_schemadb();
        $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->set_storage($this->_article);
        if (! $this->_controller->initialize())
        {
            throw new midcom_error("Failed to initialize a DM2 controller instance for article {$this->_article->id}.");
        }
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     * @param string $handler_id
     */
    private function _update_breadcrumb_line($handler_id)
    {
        $view_url = $this->_master->get_url($this->_article);

        $this->add_breadcrumb($view_url, $this->_article->title);

        $this->add_breadcrumb("{$handler_id}/{$this->_article->guid}/", $this->_l10n_midcom->get($handler_id));
    }

    /**
     * Displays an article edit view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_edit($handler_id, array $args, array &$data)
    {
        $this->_article = new midcom_db_article($args[0]);

        // Relocate for the correct content topic, let the true content topic take care of the ACL
        if ($this->_article->topic !== $this->_content_topic->id)
        {
            $nap = new midcom_helper_nav();
            $node = $nap->get_node($this->_article->topic);

            if (!empty($node[MIDCOM_NAV_ABSOLUTEURL]))
            {
                return new midcom_response_relocate($node[MIDCOM_NAV_ABSOLUTEURL] . "edit/{$args[0]}/");
            }
            throw new midcom_error_notfound("The article with GUID {$args[0]} was not found.");
        }

        $this->_article->require_do('midgard:update');

        $this->_load_controller();

        switch ($this->_controller->process_form())
        {
            case 'save':
                // Reindex the article
                $indexer = midcom::get()->indexer;
                net_nehmer_blog_viewer::index($this->_controller->datamanager, $indexer, $this->_content_topic);
                // *** FALL-THROUGH ***

            case 'cancel':
                return new midcom_response_relocate($this->_master->get_url($this->_article));
        }

        $this->_prepare_request_data();
        $this->_view_toolbar->bind_to($this->_article);
        midcom::get()->metadata->set_request_metadata($this->_article->metadata->revised, $this->_article->guid);
        midcom::get()->head->set_pagetitle("{$this->_topic->extra}: {$this->_article->title}");
        $this->_update_breadcrumb_line($handler_id);
    }

    /**
     * Shows the loaded article.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_edit ($handler_id, array &$data)
    {
        midcom_show_style('admin-edit');
    }

    /**
     * Displays an article delete confirmation view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_delete($handler_id, array $args, array &$data)
    {
        $this->_article = new midcom_db_article($args[0]);
        // Relocate to delete the link instead of the article itself
        if ($this->_article->topic !== $this->_content_topic->id)
        {
            return new midcom_response_relocate("delete/link/{$args[0]}/");
        }
        $workflow = $this->get_workflow('delete', array('object' => $this->_article));
        return $workflow->run();
    }
}
