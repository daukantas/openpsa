<?php
/**
 * @package org.openpsa.core
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * directmarketing campaign rules handler
 *
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_campaign_rules extends midcom_baseclasses_components_handler
implements org_openpsa_widgets_grid_provider_client
{
    /**
     * The campaign to operate on
     *
     * @var org_openpsa_directmarketing_campaign
     */
    private $_campaign;

    /**
     *
     * @var array
     */
    private $rules;

    public function get_qb($field = null, $direction = 'ASC')
    {
        $resolver = new org_openpsa_directmarketing_campaign_ruleresolver();
        $resolver->resolve($this->rules);
        $query = $resolver->get_mc();

        if (!is_null($field))
        {
            $query->add_order($field, $direction);
        }
        // Set the order
        $query->add_order('lastname', 'ASC');
        $query->add_order('firstname', 'ASC');
        $query->add_order('email', 'ASC');

        return $query;
    }

    public function get_row(midcom_core_dbaobject $person)
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $url = $siteconfig->get_node_full_url('org.openpsa.contacts') . 'person/';

        return array
        (
            'id' => $person->id,
            'index_firstname' => $person->firstname,
            'firstname' => '<a target="_blank" href="' . $url . $person->guid . '/">' . $person->firstname . '</a>',
            'index_lastname' => $person->lastname,
            'lastname' => '<a target="_blank" href="' . $url . $person->guid . '/">' . $person->lastname . '</a>',
            'index_email' => $person->email,
            'email' => '<a target="_blank" href="' . $url . $person->guid . '/">' . $person->email . '</a>'
        );
    }

    /**
     * Displays campaign members.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_query($handler_id, array $args, array &$data)
    {
        $this->_campaign = $this->_master->load_campaign($args[0]);
        $this->_campaign->require_do('midgard:update');
        $this->rules = $this->_load_rules();

        midcom::get()->skip_page_style = true;
    }

    /**
     * Shows campaign members.
     */
    public function _show_query($handler_id, array &$data)
    {
        $data['provider'] = new org_openpsa_widgets_grid_provider($this);
        midcom_show_style('show-campaign-members');
    }

    /**
     * Displays an campaign edit view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_edit_query($handler_id, array $args, array &$data)
    {
        $this->_campaign = $this->_master->load_campaign($args[0]);
        $this->_campaign->require_do('midgard:update');
        $data['campaign'] = $this->_campaign;

        // PONDER: Locking ?
        if (!empty($_POST['midcom_helper_datamanager2_cancel']))
        {
            return new midcom_response_relocate("campaign/{$this->_campaign->guid}/");
        }

        //check if it should be saved
        if (!empty($_POST['midcom_helper_datamanager2_save']))
        {
            try
            {
                $rules = $this->_load_rules();
            }
            catch (midcom_error $e)
            {
                midcom::get()->uimessages->add('org.openpsa.directmarketing', $this->_l10n->get($e->getMessage()), 'error');
                return;
            }

            //update campaign & Schedule background members refresh
            $this->_campaign->rules = $rules;
            if ($this->_campaign->update())
            {
                //Schedule background members refresh
                $this->_campaign->schedule_update_smart_campaign_members();

                //Save ok, relocate
                return new midcom_response_relocate("campaign/{$this->_campaign->guid}/");
            }
            //Save failed
            midcom::get()->uimessages->add($this->_component, sprintf($this->_l10n->get('error when saving rule, errstr: %s'), midcom_connection::get_error_string()), 'error');
        }

        $buttons = array
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "#",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('advanced rule editor'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/repair.png',
                MIDCOM_TOOLBAR_OPTIONS  => array
                (
                    'id' => 'openpsa_dirmar_edit_query_advanced',
                ),
            ),
            array
            (
                MIDCOM_TOOLBAR_URL => "campaign/edit_query/{$this->_campaign->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit rules'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/repair.png',
                MIDCOM_TOOLBAR_OPTIONS  => array
                (
                    'id' => 'openpsa_dirmar_edit_query',
                ),
            )
        );
        $this->_view_toolbar->add_items($buttons);

        $provider = new org_openpsa_widgets_grid_provider($this);
        $data['grid'] = $provider->get_grid('preview_persons');

        midcom::get()->head->enable_jquery();
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.directmarketing/edit_query.js');
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/org.openpsa.directmarketing/edit_query.css');

        midcom::get()->head->set_pagetitle($this->_campaign->title);
        $this->bind_view_to_object($this->_campaign);
        $this->add_breadcrumb("campaign/edit_query/{$this->_campaign->guid}/", $this->_l10n->get('edit rules'));
    }

    private function _load_rules()
    {
        if (empty($_REQUEST['midcom_helper_datamanager2_dummy_field_rules']))
        {
            return $this->_campaign->rules;
        }
        return org_openpsa_directmarketing_campaign_ruleresolver::parse($_REQUEST['midcom_helper_datamanager2_dummy_field_rules']);
    }

    /**
     * Shows the loaded campaign.
     */
    public function _show_edit_query($handler_id, array &$data)
    {
        midcom_show_style('show-campaign-edit_query');
    }
}
