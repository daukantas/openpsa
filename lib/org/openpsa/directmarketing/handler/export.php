<?php
/**
 * @package org.openpsa.directmarketing
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.directmarketing campaign handler class.
 *
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_export extends midcom_baseclasses_components_handler_dataexport
{
    /**
     * config key csv_export_memberships cached
     *
     * @var string
     */
    var $membership_mode = false;

    public function _load_schemadbs($handler_id, &$args, &$data)
    {
        // Try to load the correct campaign
        $this->_request_data['campaign'] = $this->_master->load_campaign($args[0]);

        $data['filename'] = preg_replace('/[^a-z0-9-]/i', '_', strtolower($this->_request_data['campaign']->title)) . '_' . date('Y-m-d') . '.csv';

        $this->_schema = 'export';

        return $this->_master->load_schemas();
    }

    public function _load_data($handler_id, &$args, &$data)
    {
        $rows = array();
        $qb_members = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb_members->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb_members->add_constraint('orgOpenpsaObtype', '<>', org_openpsa_directmarketing_campaign_member_dba::TESTER);
        // PONDER: Filter by status (other than tester) ??
        $qb_members->add_order('person.lastname', 'ASC');
        $qb_members->add_order('person.firstname', 'ASC');
        $members = $qb_members->execute_unchecked();

        $this->membership_mode = $this->_config->get('csv_export_memberships');

        if ($this->membership_mode == 'all')
        {
            $this->_include_guid = true;
        }

        foreach ($members as $member)
        {
            $this->_process_member($member, $rows);
        }
        return $rows;
    }

    private function _process_member(org_openpsa_directmarketing_campaign_member_dba $member, array &$rows)
    {
        try
        {
            $person = org_openpsa_contacts_person_dba::get_cached($member->person);
        }
        catch (midcom_error $e)
        {
            $e->log();
            return;
        }
        $qb_memberships = midcom_db_member::new_query_builder();
        $qb_memberships->add_constraint('uid', '=', $member->person);

        if ($memberships = $qb_memberships->execute_unchecked())
        {
            if ($this->membership_mode == 'first')
            {
                $memberships = array(reset($memberships));
            }
            else if ($this->membership_mode == 'last')
            {
                $memberships = array(end($memberships));
            }
            foreach ($memberships as $membership)
            {
                $row = array
                (
                    'person' => $person,
                    'campaign_member' => $member,
                    'organization_member' => $membership
                );
                try
                {
                    $row['organization'] = org_openpsa_contacts_group_dba::get_cached($membership->gid);
                }
                catch (midcom_error $e)
                {
                    debug_add("Error fetching org_openpsa_contacts_group_dba #{$membership->gid}, skipping", MIDCOM_LOG_WARN);
                    continue;
                }
            }
        }
        else
        {
            $row = array
            (
                'person' => $person,
                'campaign_member' => $member
            );
        }
        $rows[] = $row;
    }
}
