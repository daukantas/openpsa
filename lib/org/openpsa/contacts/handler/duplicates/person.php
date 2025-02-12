<?php
/**
 * @package org.openpsa.contacts
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Duplicates handler
 *
 * @todo This cannot work in 8.09, since metadata fields like creator are read-only.
 * Also, deleting persons isn't supported (although it works if you just call delete())
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_duplicates_person extends midcom_baseclasses_components_handler
{
    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_sidebyside($handler_id, array $args, array &$data)
    {
        $this->set_active_leaf('persons_merge');

        // Process the selection if present.
        $this->_process_selection();

        // Then find us next pair we have sufficient rights for...
        $this->_request_data['notfound'] = false;
        $this->_request_data['person1'] = false;
        $this->_request_data['person2'] = false;
        $this->_request_data['loop_i'] = 0;
        $i =& $this->_request_data['loop_i'];
        if (   isset($_REQUEST['org_openpsa_contacts_handler_duplicates_person_decide_later'])
            && isset($_REQUEST['org_openpsa_contacts_handler_duplicates_person_loop_i']))
        {
            $i = $_REQUEST['org_openpsa_contacts_handler_duplicates_person_loop_i']+1;
        }
        while ($i < 100)
        {
            debug_add("Loop iteration {$i}");
            $qb = new midgard_query_builder('midgard_parameter');
            $qb->add_constraint('domain', '=', 'org.openpsa.contacts.duplicates:possible_duplicate');
            $qb->add_order('name', 'ASC');
            $qb->set_limit(1);
            if ($i > 0)
            {
                $qb->set_offset($i);
            }
            $ret = @$qb->execute();

            if (empty($ret))
            {
                debug_add("No more results to be had, setting notfound and breaking out of loop");
                $this->_request_data['notfound'] = true;
                break;
            }

            $param =& $ret[0];
            debug_add("Found duplicate mark on person #{$param->parentguid} for person {$param->name}");
            try
            {
                $person1 = new org_openpsa_contacts_person_dba($param->parentguid);
                $person2 = new org_openpsa_contacts_person_dba($param->name);
            }
            catch (midcom_error $e)
            {
                $i++;
                continue;
            }
            // Make sure we actually have enough rights to do this
            if (   !$person1->can_do('midgard:update')
                || !$person1->can_do('midgard:delete')
                || !$person2->can_do('midgard:update')
                || !$person2->can_do('midgard:delete'))
            {
                debug_add("Insufficient rights to merge these two, continuing to see if we have more");
                $i++;
                continue;
            }
            // Extra sanity check (in case of semi-successful not-duplicate mark)
            if (   $person1->get_parameter('org.openpsa.contacts.duplicates:not_duplicate', $person2->guid)
                || $person2->get_parameter('org.openpsa.contacts.duplicates:not_duplicate', $person1->guid))
            {
                debug_add("It seems these two (#{$person1->id} and #{$person2->id}) have also marked as not duplicates, some cleanup might be a good thing", MIDCOM_LOG_WARN);
                $i++;
                continue;
            }

            $this->_request_data['probability'] = (float)$param->value;
            $this->_request_data['person1'] = $person1;
            $this->_request_data['person2'] = $person2;
            break;
        }
    }

    private function _process_selection()
    {
        if (   !empty($_POST['org_openpsa_contacts_handler_duplicates_person_keep'])
                && !empty($_POST['org_openpsa_contacts_handler_duplicates_person_options'])
                && count($_POST['org_openpsa_contacts_handler_duplicates_person_options']) == 2)
        {
            $option1 = new org_openpsa_contacts_person_dba($_POST['org_openpsa_contacts_handler_duplicates_person_options'][1]);
            $option2 = new org_openpsa_contacts_person_dba($_POST['org_openpsa_contacts_handler_duplicates_person_options'][2]);
            foreach ($_POST['org_openpsa_contacts_handler_duplicates_person_keep'] as $keep => $dummy)
            {
                switch(true)
                {
                    case ($keep == 'both'):
                        $option1->require_do('midgard:update');
                        $option2->require_do('midgard:update');
                        if (   !$option1->set_parameter('org.openpsa.contacts.duplicates:not_duplicate', $option2->guid, time())
                            || !$option2->set_parameter('org.openpsa.contacts.duplicates:not_duplicate', $option1->guid, time()))
                        {
                            $errstr = midcom_connection::get_error_string();
                            // Failed to set as not duplicate, clear parameters that might have been set
                            $option1->delete_parameter('org.openpsa.contacts.duplicates:not_duplicate', $option2->guid);
                            $option2->delete_parameter('org.openpsa.contacts.duplicates:not_duplicate', $option1->guid);

                            // TODO: Localize
                            midcom::get()->uimessages->add($this->_l10n->get('org.openpsa.contacts'), "Failed to mark #{$option1->id} and # {$option2->id} as not duplicates, errstr: {$errstr}", 'error');

                            // Switch is a "loop" so we continue 2 levels to get out of the foreach as well
                            continue(2);
                        }
                        // Clear the possible duplicate parameters
                        $option1->delete_parameter('org.openpsa.contacts.duplicates:possible_duplicate', $option2->guid);
                        $option2->delete_parameter('org.openpsa.contacts.duplicates:possible_duplicate', $option1->guid);

                        // TODO: Localize
                        midcom::get()->uimessages->add($this->_l10n->get('org.openpsa.contacts'), "Keeping both \"{$option1->name}\" and \"{$option2->name}\", they will not be marked as duplicates in the future", 'ok');

                        // Switch is a "loop" so we continue 2 levels to get out of the foreach as well
                        continue(2);
                        // Safety break
                        break;
                    case ($keep == $option1->guid):
                        $person1 =& $option1;
                        $person2 =& $option2;
                        break;
                    case ($keep == $option2->guid):
                        $person1 =& $option2;
                        $person2 =& $option1;
                        break;
                    default:
                        throw new midcom_error('Something weird happened (basically we got bogus data)');
                }
                $person1->require_do('midgard:update');
                $person2->require_do('midgard:delete');

                // TODO: Merge person2 data to person1 and then delete person2

                $merger = new org_openpsa_contacts_duplicates_merge('person');
                if (!$merger->merge_delete($person1, $person2))
                {
                    // TODO: Localize
                    midcom::get()->uimessages->add($this->_l10n->get('org.openpsa.contacts'), 'Merge failed, errstr: ' . $merger->errstr(), 'error');
                }
            }

            //PONDER: redirect to avoid reloading the POST in case user presses reload ??
        }
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_sidebyside($handler_id, array &$data)
    {
        if (!$this->_request_data['notfound'])
        {
            midcom_show_style('show-duplicate-persons');
        }
        else
        {
            midcom_show_style('show-duplicate-persons-notfound');
        }
    }
}
