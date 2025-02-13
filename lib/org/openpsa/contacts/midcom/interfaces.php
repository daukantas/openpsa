<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA Contact registers/user manager
 *
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_interface extends midcom_baseclasses_components_interface
implements midcom_services_permalinks_resolver, org_openpsa_contacts_duplicates_support
{
    /**
     * Prepares the component's indexer client
     */
    public function _on_reindex($topic, $config, &$indexer)
    {
        $qb_organisations = org_openpsa_contacts_group_dba::new_query_builder();
        $qb_organisations->add_constraint('orgOpenpsaObtype', '<>', org_openpsa_contacts_group_dba::MYCONTACTS);
        $organisation_schema = midcom_helper_datamanager2_schema::load_database($config->get('schemadb_group'));

        $qb_persons = org_openpsa_contacts_person_dba::new_query_builder();
        $person_schema = midcom_helper_datamanager2_schema::load_database($config->get('schemadb_person'));

        $indexer = new org_openpsa_contacts_midcom_indexer($topic, $indexer);
        $indexer->add_query('organisations', $qb_organisations, $organisation_schema);
        $indexer->add_query('persons', $qb_persons, $person_schema);

        return $indexer;
    }

    /**
     * Locates the root group
     */
    static function find_root_group($name = '__org_openpsa_contacts')
    {
        static $root_groups = array();

        //Check if we have already initialized
        if (!empty($root_groups[$name]))
        {
            return $root_groups[$name];
        }

        $qb = midcom_db_group::new_query_builder();
        $qb->add_constraint('owner', '=', 0);
        $qb->add_constraint('name', '=', $name);

        $results = $qb->execute();

        if (!empty($results))
        {
            foreach ($results as $group)
            {
                $root_groups[$name] = $group;
            }
        }
        else
        {
            debug_add("OpenPSA Contacts root group could not be found", MIDCOM_LOG_WARN);

            //Attempt to  auto-initialize the group.
            midcom::get()->auth->request_sudo('org.openpsa.contacts');
            $grp = new midcom_db_group();
            $grp->owner = 0;
            $grp->name = $name;
            $grp->official = midcom::get()->i18n->get_l10n('org.openpsa.contacts')->get($name);
            $ret = $grp->create();
            midcom::get()->auth->drop_sudo();
            if (!$ret)
            {
                throw new midcom_error("Could not auto-initialize the module, group creation failed: " . midcom_connection::get_error_string());
            }
            $root_groups[$name] = $grp;
        }

        return $root_groups[$name];
    }

    public function resolve_object_link(midcom_db_topic $topic, midcom_core_dbaobject $object)
    {
        if (   $object instanceof org_openpsa_contacts_group_dba
            || $object instanceof midcom_db_group)
        {
            return "group/{$object->guid}/";
        }
        if (   $object instanceof org_openpsa_contacts_person_dba
            || $object instanceof midcom_db_person)
        {
            return "person/{$object->guid}/";
        }
        return null;
    }

    public function get_merge_configuration($object_mode, $merge_mode)
    {
        $config = array();
        if ($merge_mode == 'future')
        {
            // Contacts does not have future references so we have nothing to transfer...
            return $config;
        }
        if ($object_mode == 'person')
        {
            $config['midcom_db_member'] = array
            (
                'uid' => array
                (
                    'target' => 'id',
                    'duplicate_check' => 'gid'
                )
            );
            $config['org_openpsa_contacts_person_dba'] = array();
            $config['org_openpsa_contacts_group_dba'] = array();

        }
        return $config;
    }

    private function _get_data_from_url($url)
    {
        //We have to hang on to the hKit object, because its configuration is done by require_once
        //and will thus only work for the first instantiation...
        static $hkit;
        if (is_null($hkit))
        {
            require_once MIDCOM_ROOT . '/external/hkit.php';
            $hkit = new hKit();
        }
        $data = array();

        // TODO: Error handling
        $client = new org_openpsa_httplib();
        $html = $client->get($url);

        // Check for ICBM coordinate information
        $icbm = org_openpsa_httplib_helpers::get_meta_value($html, 'icbm');
        if ($icbm)
        {
            $data['icbm'] = $icbm;
        }

        // Check for RSS feed
        $rss_url = org_openpsa_httplib_helpers::get_link_values($html, 'alternate');

        if (!empty($rss_url))
        {
            $data['rss_url'] = $rss_url[0]['href'];

            // We have a feed URL, but we should check if it is GeoRSS as well
            $items = net_nemein_rss_fetch::raw_fetch($data['rss_url'])->get_items();

            if (count($items) > 0)
            {
                if (   $items[0]->get_latitude()
                    || $items[0]->get_longitude())
                {
                    // This is a GeoRSS feed
                    $data['georss_url'] = $data['rss_url'];
                }
            }
        }

        $hcards = @$hkit->getByURL('hcard', $url);

        if (   is_array($hcards)
            && count($hcards) > 0)
        {
            // We have found hCard data here
            $data['hcards'] = $hcards;
        }

        return $data;
    }

    /**
     * AT handler for fetching Semantic Web data for person or group
     *
     * @param array $args handler arguments
     * @param object $handler The cron_handler object calling this method.
     * @return boolean indicating success/failure
     */
    function check_url($args, $handler)
    {
        if (array_key_exists('person', $args))
        {
            // Handling for persons
            try
            {
                $person = new org_openpsa_contacts_person_dba($args['person']);
            }
            catch (midcom_error $e)
            {
                $handler->print_error("Person {$args['person']} not found, error " . $e->getMessage());
                return false;
            }
            if (!$person->homepage)
            {
                $handler->print_error("Person {$person->guid} has no homepage, skipping");
                return false;
            }
            return $this->_check_person_url($person);
        }
        else if (array_key_exists('group', $args))
        {
            // Handling for groups
            try
            {
                $group = new org_openpsa_contacts_group_dba($args['group']);
            }
            catch (midcom_error $e)
            {
                $handler->print_error("Group {$args['group']} not found, error " . $e->getMessage());
                return false;
            }
            if (!$group->homepage)
            {
                $handler->print_error("Group {$group->guid} has no homepage, skipping");
                return false;
            }
            return $this->_check_group_url($group);
        }
        $handler->print_error('Person or Group GUID not set, aborting');
        return false;
    }

    private function _check_group_url(org_openpsa_contacts_group_dba $group)
    {
        $data = org_openpsa_contacts_interface::_get_data_from_url($group->homepage);

        // Use the data we got
        if (array_key_exists('icbm', $data))
        {
            // We know where the group is located
            $icbm_parts = explode(',', $data['icbm']);
            if (count($icbm_parts) == 2)
            {
                $latitude = (float) $icbm_parts[0];
                $longitude = (float) $icbm_parts[1];
                if (   abs($latitude) < 90
                    && abs($longitude) < 180)
                {
                    $location = new org_routamc_positioning_location_dba();
                    $location->date = time();
                    $location->latitude = $latitude;
                    $location->longitude = $longitude;
                    $location->relation = org_routamc_positioning_location_dba::RELATION_LOCATED;
                    $location->parent = $group->guid;
                    $location->parentclass = 'org_openpsa_contacts_group_dba';
                    $location->parentcomponent = 'org.openpsa.contacts';
                    $location->create();
                }
            }
        }
        // TODO: We can use a lot of other data too
        if (array_key_exists('hcards', $data))
        {
            // Process those hCard values that are interesting for us
            foreach ($data['hcards'] as $hcard)
            {
                $group = $this->_update_from_hcard($group, $hcard);
            }

            $group->update();
        }
        return true;
    }

    private function _check_person_url(org_openpsa_contacts_person_dba $person)
    {
        $data = org_openpsa_contacts_interface::_get_data_from_url($person->homepage);

        // Use the data we got
        if (array_key_exists('georss_url', $data))
        {
            // GeoRSS subscription is a good way to keep track of person's location
            $person->set_parameter('org.routamc.positioning:georss', 'georss_url', $data['georss_url']);
        }
        else if (array_key_exists('icbm', $data))
        {
            // Instead of using the ICBM position data directly we can subscribe to it so we get modifications too
            $person->set_parameter('org.routamc.positioning:html', 'icbm_url', $person->homepage);
        }

        if (array_key_exists('rss_url', $data))
        {
            // Instead of using the ICBM position data directly we can subscribe to it so we get modifications too
            $person->set_parameter('net.nemein.rss', 'url', $data['rss_url']);
        }

        if (array_key_exists('hcards', $data))
        {
            // Process those hCard values that are interesting for us
            foreach ($data['hcards'] as $hcard)
            {
                $person = $this->_update_from_hcard($person, $hcard);
            }

            $person->update();
        }
        return true;
    }

    private function _update_from_hcard($object, $hcard)
    {
        foreach ($hcard as $key => $val)
        {
            switch ($key)
            {
                case 'email':
                    $object->email = $val;
                    break;

                case 'tel':
                    $object->workphone = $val;
                    break;

                case 'note':
                    $object->extra = $val;
                    break;

                case 'photo':
                    // TODO: Importing the photo would be cool
                    break;

                case 'adr':
                    if (array_key_exists('street-address', $val))
                    {
                        $object->street = $val['street-address'];
                    }
                    if (array_key_exists('postal-code', $val))
                    {
                        $object->postcode = $val['postal-code'];
                    }
                    if (array_key_exists('locality', $val))
                    {
                        $object->city = $val['locality'];
                    }
                    if (array_key_exists('country-name', $val))
                    {
                        $object->country = $val['country-name'];
                    }
                    break;
            }
        }
        return $object;
    }
}
