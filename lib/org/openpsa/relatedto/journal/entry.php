<?php
/**
 * @package org.openpsa.relatedto
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped base class, keep logic here
 *
 * @package org.openpsa.relatedto
 */
class org_openpsa_relatedto_journal_entry_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_relatedto_journal_entry';

    public $_use_activitystream = false;
    public $_use_rcs = false;

    function get_parent_guid_uncached()
    {
        return $this->linkGuid;
    }
}
