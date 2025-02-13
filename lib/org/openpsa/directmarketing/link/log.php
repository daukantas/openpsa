<?php
/**
 * @package org.openpsa.directmarketing
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_link_log_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_link_log';

    public $_use_activitystream = false;
    public $_use_rcs = false;

    public function _on_creating()
    {
        if (!$this->timestamp)
        {
            $this->timestamp = time();
        }
        if (   !$this->referrer
            && !empty($_SERVER['HTTP_REFERER']))
        {
            $this->referrer = $_SERVER['HTTP_REFERER'];
        }
        return true;
    }
}
