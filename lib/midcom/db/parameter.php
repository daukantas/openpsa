<?php
/**
 * @package midcom.db
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM level replacement for the Midgard Parameter record with framework support.
 *
 * The uplink is the parentguid parameter.
 *
 * @package midcom.db
 */
class midcom_db_parameter extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'midgard_parameter';

    public $_use_activitystream = false;
    public $_use_rcs = false;

    function get_parent_guid_uncached()
    {
        return $this->parentguid;
    }

    /**
     * Returns the Parent of the Parameter.
     *
     * @return string Parent GUID or null if there is none.
     */
    public static function get_parent_guid_uncached_static($guid, $classname = __CLASS__)
    {
        $mc = new midgard_collector('midgard_parameter', 'guid', $guid);
        $mc->set_key_property('parentguid');
        $mc->execute();
        $link_values = $mc->list_keys();
        if (empty($link_values))
        {
            return null;
        }
        return key($link_values);
    }

    /**
     * Helper function to read a parameter without loading the corresponding object.
     * This is primarily for improving performance, so the function does not check
     * for privileges.
     *
     * @param string $objectguid The object's GUID
     * @param string $domain The parameter's domain
     * @param string $name The parameter to look for
     */
    public static function get_by_objectguid($objectguid, $domain, $name)
    {
        static $parameter_cache = array();

        if (!isset($parameter_cache[$objectguid]))
        {
            $parameter_cache[$objectguid] = array();
        }

        if (isset($parameter_cache[$objectguid][$name]))
        {
            return $parameter_cache[$objectguid][$name];
        }
        $parameter_cache[$objectguid][$name] = null;

        $mc = midgard_parameter::new_collector('parentguid', $objectguid);
        $mc->set_key_property('value');
        $mc->add_constraint('name', '=', $name);
        $mc->add_constraint('domain', '=', $domain);
        $mc->set_limit(1);
        $mc->execute();
        $parameters = $mc->list_keys();

        if (count($parameters) > 0)
        {
            $parameter_cache[$objectguid][$name] = key($parameters);
        }

        return $parameter_cache[$objectguid][$name];
    }

    public function get_label()
    {
        return "{$this->domain} {$this->name}";
    }
}
