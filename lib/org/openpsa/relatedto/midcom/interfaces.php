<?php
/**
 * @package org.openpsa.relatedto
 * @author Nemein Oy, http://www.nemein.com/
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA relatedto library, handled saving and retrieving "related to" information
 *
 * Startup loads main class, which is used for all operations.
 *
 * @package org.openpsa.relatedto
 */
class org_openpsa_relatedto_interface extends midcom_baseclasses_components_interface
implements org_openpsa_contacts_duplicates_support
{
    public function _on_watched_dba_create($object)
    {
        //Check if we have data in session, if so use that.
        $session = new midcom_services_session('org.openpsa.relatedto');
        if ($session->exists('relatedto2get_array'))
        {
            $relatedto_arr = $session->get('relatedto2get_array');
            $session->remove('relatedto2get_array');
        }
        else
        {
            $relatedto_arr = org_openpsa_relatedto_plugin::get2relatedto();
        }
        foreach ($relatedto_arr as $rel)
        {
            $rel->fromClass = get_class($object);
            $rel->fromGuid = $object->guid;
            if (!$rel->id)
            {
                $rel->create();
            }
            else
            {
                //In theory we should not ever hit this, but better to be sure.
                $rel->update();
            }
        }
    }

    /**
     * Ensure relatedto links pointing to an object are deleted when the object is
     */
    public function _on_watched_dba_delete($object)
    {
        $qb = org_openpsa_relatedto_dba::new_query_builder();
        $qb->begin_group('OR');
            $qb->add_constraint('fromGuid', '=', $object->guid);
            $qb->add_constraint('toGuid', '=', $object->guid);
        $qb->end_group();
        if ($qb->count_unchecked() == 0)
        {
            return;
        }
        midcom::get()->auth->request_sudo($this->_component);
        $links = $qb->execute();
        foreach ($links as $link)
        {
            $link->delete();
        }
        midcom::get()->auth->drop_sudo();
    }

    public function get_merge_configuration($object_mode, $merge_mode)
    {
        $config = array();
        if ($merge_mode == 'future')
        {
            // Relatedto does not have future references so we have nothing to transfer...
            return $config;
        }
        if ($object_mode == 'person')
        {
            $config['org_openpsa_relatedto_dba'] = array
            (
                'fromGuid' => array
                (
                    'target' => 'guid',
                    'duplicate_check' => 'toGuid'
                ),
                'toGuid' => array
                (
                    'target' => 'guid',
                    'duplicate_check' => 'fromGuid'
                )
            );
        }
        return $config;
    }
}
