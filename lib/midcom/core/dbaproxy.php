<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM DBA proxy class. This is useful for loading objects on-demand
 *
 * @package midcom
 */
class midcom_core_dbaproxy
{
    /**
     * MidCOM DBA object
     *
     * @var object
     */
    private $__object = null;

    /**
     * MidCOM DBA classname
     *
     * @var string
     */
    public $__midcom_class_name__;

    /**
     * MidCOM DBA object identifier, can be ID or GUID
     *
     * @var mixed
     */
    private $__identifier;

    /**
     * Flag that indicates whether or not we already tried to load this
     * object
     *
     * @var boolean
     */
    private $__tried_to_load = false;

    /**
     * Constructor
     */
    public function __construct($identifier, $classname)
    {
        $this->__midcom_class_name__ = $classname;
        $this->__identifier = $identifier;
    }

    private function _load_object()
    {
        if ($this->__tried_to_load)
        {
            return (null !== $this->__object);
        }

        $this->__tried_to_load = true;

        try
        {
            $this->__object = call_user_func(array($this->__midcom_class_name__, 'get_cached'), $this->__identifier);
            return true;
        }
        catch (midcom_error $e)
        {
            $e->log();
        }
        return false;
    }

    /**
     * Magic getter for object property mapping
     *
     * @param string $property Name of the property
     */
    public function __get($property)
    {
        if (!$this->_load_object())
        {
            return null;
        }

        return $this->__object->$property;
    }

    /**
     * Magic setter for object property mapping
     *
     * @param string $property  Name of the property
     * @param mixed $value      Property value
     */
    public function __set($property, $value)
    {
        if (!$this->_load_object())
        {
            return null;
        }

        return $this->__object->$property = $value;
    }

    /**
     * Magic isset test for object property mapping
     *
     * @param string $property  Name of the property
     */
    public function __isset($property)
    {
        if (!$this->_load_object())
        {
            return null;
        }

        return isset($this->__object->$property);
    }

    public function __call($method, $arguments)
    {
        if (!$this->_load_object())
        {
            return null;
        }

        return call_user_func_array(array($this->__object, $method), $arguments);
    }

    /**
     * This is called when the object is serialized (f.x. written to memcache). It eliminates the object
     * to increase performance
     */
    public function __sleep()
    {
        return array('__identifier', '__midcom_class_name__');
    }
}
