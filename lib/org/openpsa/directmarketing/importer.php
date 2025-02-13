<?php
/**
 * @package org.openpsa.directmarketing
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Importer for subscribers
 *
 * @package org.openpsa.directmarketing
 */
abstract class org_openpsa_directmarketing_importer extends midcom_baseclasses_components_purecode
{
    /**
     * Datamanagers used for saving various objects like persons and organizations
     *
     * @var array
     */
    private $_datamanagers = array();

    /**
     * The schema databases used for importing to various objects like persons and organizations
     *
     * @var array
     */
    protected $_schemadbs = array();

    /**
     * Object registry
     *
     * @var array
     */
    private $_new_objects = array();

    /**
     * Status table
     *
     * @var array
     */
    private $_import_status = array();

    /**
     * Importer configuration, if any
     *
     * @var array
     */
    protected $_settings = array();

    /**
     * @param array $schemadbs The datamanager schemadbs to work on
     * @param array $settings Importer configuration, if any
     */
    public function __construct(array $schemadbs, array $settings = array())
    {
        parent::__construct();
        $this->_settings = $settings;
        $this->_schemadbs = $schemadbs;
        $this->_datamanagers['campaign_member'] = new midcom_helper_datamanager2_datamanager($this->_schemadbs['campaign_member']);
        $this->_datamanagers['person'] = new midcom_helper_datamanager2_datamanager($this->_schemadbs['person']);
        $this->_datamanagers['organization_member'] = new midcom_helper_datamanager2_datamanager($this->_schemadbs['organization_member']);
        $this->_datamanagers['organization'] = new midcom_helper_datamanager2_datamanager($this->_schemadbs['organization']);
    }

    /**
     * Converts input into the importer array format
     *
     * @param mixed $input
     * @return array
     */
    abstract function parse($input);

    /**
     * Process the datamanager
     *
     * @param String $type        Subscription type
     * @param array $subscriber
     * @param midcom_core_dbaobject $object
     */
    private function _datamanager_process($type, array $subscriber, midcom_core_dbaobject $object)
    {
        if (empty($subscriber[$type]))
        {
            // No fields for this type, skip DM phase
            return;
        }

        // Load datamanager2 for the object
        if (!$this->_datamanagers[$type]->autoset_storage($object))
        {
            throw new midcom_error('Failed to set DM2 storage');
        }

        // Set all given values into DM2
        foreach ($subscriber[$type] as $key => $value)
        {
            if (array_key_exists($key, $this->_datamanagers[$type]->types))
            {
                $this->_datamanagers[$type]->types[$key]->value = $value;
            }
        }

        // Save the object
        if (!$this->_datamanagers[$type]->save())
        {
            throw new midcom_error('DM2 save returned false');
        }
    }

    /**
     * Clean the new objects
     */
    private function _clean_new_objects()
    {
        foreach ($this->_new_objects as $object)
        {
            $object->delete();
        }
    }

    private function _import_subscribers_person(array $subscriber)
    {
        $person = null;
        if ($this->_config->get('csv_import_check_duplicates'))
        {
            if (!empty($subscriber['person']['email']))
            {
                // Perform a simple email test. More complicated duplicate checking is best left to the o.o.contacts duplicate checker
                $qb = org_openpsa_contacts_person_dba::new_query_builder();
                $qb->add_constraint('email', '=', $subscriber['person']['email']);
                $persons = $qb->execute_unchecked();
                if (count($persons) > 0)
                {
                    // Match found, use it
                    $person = $persons[0];
                }
            }

            if (   !$person
                && !empty($subscriber['person']['handphone']))
            {
                // Perform a simple cell phone test. More complicated duplicate checking is best left to the o.o.contacts duplicate checker
                $qb = org_openpsa_contacts_person_dba::new_query_builder();
                $qb->add_constraint('handphone', '=', $subscriber['person']['handphone']);
                $persons = $qb->execute_unchecked();
                if (count($persons) > 0)
                {
                    // Match found, use it
                    $person = $persons[0];
                }
            }
        }

        if (!$person)
        {
            // We didn't have person matching the email in DB. Create a new one.
            $person = new org_openpsa_contacts_person_dba();

            // Populate at least one field for the new person
            if (!empty($subscriber['person']['email']))
            {
                $person->email = $subscriber['person']['email'];
            }

            if (!$person->create())
            {
                $this->_import_status['failed_create']++;
                throw new midcom_error("Failed to create person, reason " . midcom_connection::get_error_string());
            }
            $this->_new_objects['person'] = $person;
        }

        $this->_datamanager_process('person', $subscriber, $person);

        return $person;
    }

    private function _import_subscribers_campaign_member(array $subscriber, org_openpsa_contacts_person_dba $person, org_openpsa_directmarketing_campaign_dba $campaign)
    {
        // Check if person is already in campaign
        $member = null;
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('person', '=', $person->id);
        $qb->add_constraint('campaign', '=', $campaign->id);
        $qb->add_constraint('orgOpenpsaObtype', '<>', org_openpsa_directmarketing_campaign_member_dba::TESTER);
        $members = $qb->execute_unchecked();
        if (count($members) > 0)
        {
            // User is or has been subscriber earlier, update status
            $member = $members[0];

            if (   $member->orgOpenpsaObtype == org_openpsa_directmarketing_campaign_member_dba::UNSUBSCRIBED
                || $member->orgOpenpsaObtype == org_openpsa_directmarketing_campaign_member_dba::NORMAL)
            {
                $this->_import_status['already_subscribed']++;
                return;
            }
            $member->orgOpenpsaObtype = org_openpsa_directmarketing_campaign_member_dba::NORMAL;
            if (!$member->update())
            {
                $this->_import_status['failed_add']++;
                throw new midcom_error('Failed to save membership: ' . midcom_connection::get_error_string());
            }
            if (array_key_exists('person', $this->_new_objects))
            {
                $this->_import_status['subscribed_new']++;
            }
            else
            {
                $this->_import_status['already_subscribed']++;
            }
        }
        else
        {
            // Not a subscribed member yet, add
            $member = new org_openpsa_directmarketing_campaign_member_dba();
            $member->person = $person->id;
            $member->campaign = $campaign->id;
            $member->orgOpenpsaObtype = org_openpsa_directmarketing_campaign_member_dba::NORMAL;
            if (!$member->create())
            {
                $this->_import_status['failed_add']++;
                throw new midcom_error('Failed to create membership: ' . midcom_connection::get_error_string());
            }
            $this->_new_objects['campaign_member'] = $member;
            $this->_import_status['subscribed_new']++;
        }

        $this->_datamanager_process('campaign_member', $subscriber, $person);
    }

    private function _import_subscribers_organization(array $subscriber)
    {
        $organization = null;
        if (!empty($subscriber['organization']['official']))
        {
            // Perform a simple check for existing organization. More complicated duplicate checking is best left to the o.o.contacts duplicate checker

            $qb = org_openpsa_contacts_group_dba::new_query_builder();

            if (   array_key_exists('company_id', $this->_schemadbs['organization']['default']->fields)
                && !empty($subscriber['organization']['company_id']))
            {
                // Imported data has a company id, we use that instead of name
                $qb->add_constraint($this->_schemadbs['organization']['default']->fields['company_id']['storage']['location'], '=', $subscriber['organization']['company_id']);
            }
            else
            {
                // Seek by official name
                $qb->add_constraint('official', '=', $subscriber['organization']['official']);

                if (   array_key_exists('city', $this->_schemadbs['organization']['default']->fields)
                    && !empty($subscriber['organization']['city']))
                {
                    // Imported data has a city, we use also that for matching
                    $qb->add_constraint($this->_schemadbs['organization']['default']->fields['city']['storage']['location'], '=', $subscriber['organization']['city']);
                }
            }

            $organizations = $qb->execute_unchecked();
            if (count($organizations) > 0)
            {
                // Match found, use it
                $organization = array_shift($organizations);
            }
        }

        if (!$organization)
        {
            // We didn't have person matching the email in DB. Create a new one.
            $organization = new org_openpsa_contacts_group_dba();
            if (!$organization->create())
            {
                throw new midcom_error("Failed to create organization, reason " . midcom_connection::get_error_string());
            }
        }

        $this->_datamanager_process('organization', $subscriber, $organization);

        return $organization;
    }

    private function _import_subscribers_organization_member(array $subscriber, org_openpsa_contacts_person_dba $person, org_openpsa_contacts_group_dba $organization)
    {
        // Check if person is already in organization
        $qb = midcom_db_member::new_query_builder();
        $qb->add_constraint('uid', '=', $person->id);
        $qb->add_constraint('gid', '=', $organization->id);
        $members = $qb->execute_unchecked();
        if (count($members) > 0)
        {
            // Match found, use it
            $member = $members[0];
        }
        else
        {
            // We didn't have person matching the email in DB. Create a new one.
            $member = new midcom_db_member();
            $member->uid = $person->id;
            $member->gid = $organization->id;
            if (!$member->create())
            {
                throw new midcom_error("Failed to create organization member, reason " . midcom_connection::get_error_string());
            }
        }

        $this->_datamanager_process('organization_member', $subscriber, $member);
    }

    /**
     * Takes an array of new subscribers and processes each of them using datamanager2.
     *
     * @param array $subscribers The subscribers to import
     * @param org_openpsa_directmarketing_campaign_dba $campaign The campaign to import into
     * @return array Import status
     */
    public function import_subscribers(array $subscribers, org_openpsa_directmarketing_campaign_dba $campaign)
    {
        $this->_import_status = array
        (
            'already_subscribed' => 0,
            'subscribed_new' => 0,
            'failed_create' => 0,
            'failed_add' => 0,
        );

        foreach ($subscribers as $subscriber)
        {
            // Submethods will register any objects they create to this array so we can clean them up as needed
            $this->_new_objects = array();

            try
            {
                $person = $this->_import_subscribers_person($subscriber);
                $this->_import_subscribers_campaign_member($subscriber, $person, $campaign);

                if (!empty($subscriber['organization']))
                {
                    $organization = $this->_import_subscribers_organization($subscriber);
                    $this->_import_subscribers_organization_member($subscriber, $person, $organization);
                }
            }
            catch (midcom_error $e)
            {
                $e->log();
                // Clean up possibly created data
                $this->_clean_new_objects();

                // Skip to next
                continue;
            }

            // All done, import the next one
            debug_add("Person $person->name (#{$person->id}) all processed");
        }
        return $this->_import_status;
    }
}