<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Privilege class, used to interact with the privilege system. It encapsulates the actual
 * Database Level Object. As usual with MidCOM DBA, you <i>must never access the DB layer
 * object.</i>
 *
 * The main area of expertise of this class is privilege IO (loading and storing), their
 * validation and privilege merging.
 *
 * It is important to understand that you must never load privilege records directly, or
 * access them by their IDs. Instead, use the DBA level interface functions to locate
 * existing privilege sets. The only time where you use this class directly is when
 * creating new privilege, using the default constructor of this class (although the
 * create_new_privilege_object DBA member methods are the preferred way of doing this).
 *
 * <b>Caching:</b>
 *
 * This class uses the memcache cache module to speed up ACL accesses. It caches the ACL
 * objects retrieved from the database, not any merged privilege set (at this time, that is).
 * This should speed up regular operations quite a bit (along with the parent guid cache,
 * which is a second important key).
 *
 * @package midcom
 */
class midcom_core_privilege
{
    /**
     * Cached actual midcom_core_privilege_db data for this privilege.
     *
     * @var array
     */
    private $__privilege = array
    (
        'guid' => '',
        'objectguid' => '',
        'privilegename'=> '',
        'assignee' => null,
        'scope' => -1,
        'classname' => '',
        'value' => null
    );

    /**
     * The actual midcom_core_privilege_db object for this privilege.
     *
     * @var midcom_core_privilege_db
     */
    private $__privilege_object = null;

    /**
     * GUID of the midcom_core_privilege_db object, used when values are retrieved via collector instead of QB
     *
     * @var string
     */
    private $__guid = '';

    /**
     * Cached content object, based on $objectguid.
     *
     * @var object
     */
    private $__cached_object = null;

    /**
     * The Default constructor creates an empty privilege, if you specify
     * another privilege object in the constructor, a copy is constructed.
     *
     * @param midcom_core_privilege_db $src Object to copy from.
     */
    public function __construct($src = null)
    {
        if (is_array($src))
        {
            // Store given values to our privilege array
            $this->__privilege = array_merge($this->__privilege, $src);
        }
        else
        {
            $this->_load($src);
            if (!is_null($src))
            {
                $this->_sync_from_db_object();
            }
        }
    }

    // Magic getter and setter for object property mapping
    public function __get($property)
    {
        if (!array_key_exists($property, $this->__privilege))
        {
            return null;
        }

        return $this->__privilege[$property];
    }

    public function __set($property, $value)
    {
        return $this->__privilege[$property] = $value;
    }

    public function __isset($property)
    {
        return isset($this->__privilege[$property]);
    }

    private function _get_scope()
    {
        $scope = -1;

        switch ($this->__privilege['assignee'])
        {
            case 'EVERYONE':
                $scope = MIDCOM_PRIVILEGE_SCOPE_EVERYONE;
                break;
            case 'USERS':
                $scope = MIDCOM_PRIVILEGE_SCOPE_USERS;
                break;
            case 'ANONYMOUS':
                $scope = MIDCOM_PRIVILEGE_SCOPE_ANONYMOUS;
                break;
            case 'SELF':
                //scope is not applicable here
                break;
            default:
                if ($assignee = $this->get_assignee())
                {
                    $scope = $assignee->scope;
                }
                else
                {
                    debug_print_r('Could not resolve the assignee of this privilege:', $this);
                }
                break;
        }

        return $scope;
    }

    /**
     * A copy of the object referenced by the guid value of this privilege.
     *
     * @return midcom_core_dbaobject The DBA object to which this privileges is assigned or false on failure (f.x. missing access permissions).
     */
    public function get_object()
    {
        if (is_null($this->__cached_object))
        {
            try
            {
                $this->__cached_object = midcom::get()->dbfactory->get_object_by_guid($this->objectguid);
            }
            catch (midcom_error $e)
            {
                return false;
            }
        }
        return $this->__cached_object;
    }

    /**
     * Set a privilege to a given content object.
     *
     * @param object $object A MidCOM DBA level object to which this privilege should be assigned to.
     */
    public function set_object($object)
    {
        $this->__cached_object = $object;
        $this->objectguid = $object->guid;
    }

    /**
     * If the assignee has an object representation (at this time, only users and groups have), this call
     * will return a reference to the assignee object held by the authentication service.
     *
     * Use is_magic_assignee to determine if you have an assignee object.
     *
     * @see midcom_services_auth::get_assignee()
     * @return mixed A midcom_core_user or midcom_core_group object reference as returned by the auth service,
     *     returns false on failure.
     */
    public function get_assignee()
    {
        if ($this->is_magic_assignee())
        {
            return false;
        }

        return midcom::get()->auth->get_assignee($this->assignee);
    }

    /**
     * Checks whether the current assignee is a magic assignee or an object identifier.
     *
     * @return boolean True if it is a magic assignee, false otherwise.
     */
    public function is_magic_assignee($assignee = null)
    {
        if ($assignee === null)
        {
            $assignee = $this->assignee;
        }
        return (in_array($assignee, array('SELF', 'EVERYONE', 'USERS', 'ANONYMOUS', 'OWNER')));
    }

    /**
     * This call sets the assignee member string to the correct value to represent the
     * object passed, in general, this resolves users and groups to their strings and
     * leaves magic assignees intact.
     *
     * Possible argument types:
     *
     * - Any one of the magic assignees SELF, EVERYONE, ANONYMOUS, USERS.
     * - Any midcom_core_user or midcom_core_group object or subtype thereof.
     * - Any string identifier which can be resolved using midcom_services_auth::get_assignee().
     *
     * @param mixed $assignee An assignee representation as outlined above.
     * @return boolean indicating success.
     */
    public function set_assignee($assignee)
    {
        if (   is_a($assignee, 'midcom_core_user')
            || is_a($assignee, 'midcom_core_group'))
        {
            $this->assignee = $assignee->id;
        }
        else if (is_string($assignee))
        {
            if ($this->is_magic_assignee($assignee))
            {
                $this->assignee = $assignee;
            }
            else
            {
                $tmp = midcom::get()->auth->get_assignee($assignee);
                if (! $tmp)
                {
                    debug_add("Could not resolve the assignee string '{$assignee}', see above for more information.", MIDCOM_LOG_INFO);
                    return false;
                }
                $this->assignee = $tmp->id;
            }
        }
        else
        {
            debug_add('Unknown type passed, aborting.', MIDCOM_LOG_INFO);
            debug_print_r('Argument was:', $assignee);
            return false;
        }

        return true;
    }

    /**
     * This call validates the privilege for correctness of all set options. This includes:
     *
     * - A check against the list of registered privileges to ensure the existence of the
     *   privilege itself.
     * - A check for a valid and existing assignee, this includes a class existence check for classname restrictions
     *   for SELF privileges.
     * - A check for an existing content object GUID (this implicitly checks for midgard:read as well).
     * - Enough privileges of the current user to update the object's privileges (the user
     *   must have midgard:update and midgard:privileges for this to succeed).
     * - A valid privilege value.
     */
    public function validate()
    {
        // 1. Privilege name
        if (! midcom::get()->auth->acl->privilege_exists($this->privilegename))
        {
            debug_add("The privilege name '{$this->privilegename}' is unknown to the system. Perhaps the corresponding component is not loaded?",
                MIDCOM_LOG_INFO);
            return false;
        }

        // 2. Assignee
        if (   ! $this->is_magic_assignee()
            && ! $this->get_assignee())
        {
            debug_add("The assignee identifier '{$this->assignee}' is invalid.", MIDCOM_LOG_INFO);
            return false;
        }

        if (   $this->assignee == 'SELF'
            && $this->classname != ''
            && ! class_exists($this->classname))
        {
            debug_add("The class '{$this->classname}' is not loaded, the SELF magic assignee with class restriction is invalid therefore.", MIDCOM_LOG_INFO);
            return false;
        }

        if (   $this->assignee != 'SELF'
            && $this->classname != '')
        {
            debug_add("The classname parameter was specified without having the magic assignee SELF set, this is invalid.", MIDCOM_LOG_INFO);
            return false;
        }

        // Prevent owner assignments to owners
        if (   $this->assignee == 'OWNER'
            && $this->privilegename == 'midgard:owner')
        {
            debug_add("Tried to assign midgard:owner to the OWNER magic assignee, this is invalid.", MIDCOM_LOG_INFO);
            return false;
        }

        $object = $this->get_object();
        if (!is_object($object))
        {
            debug_add("Could not retrieve the content object with the GUID '{$this->objectguid}'; see the debug level log for more information.",
                MIDCOM_LOG_INFO);
            return false;
        }
        if (   !$object->can_do('midgard:update')
            || !$object->can_do('midgard:privileges'))
        {
            debug_add("Insufficient privileges on the content object with the GUID '{$this->__guid}', midgard:update and midgard:privileges required.",
                MIDCOM_LOG_INFO);
            return false;
        }
        $valid_values = array
        (
            MIDCOM_PRIVILEGE_ALLOW,
            MIDCOM_PRIVILEGE_DENY,
            MIDCOM_PRIVILEGE_INHERIT,
        );

        if (!in_array($this->value, $valid_values))
        {
            debug_add("Invalid privilege value '{$this->value}'.", MIDCOM_LOG_INFO);
            return false;
        }

        return true;
    }

    /**
     * This is a helper function which lists all content privileges
     * assigned to a given object. Essentially, this will exclude all SELF style assignees.
     *
     * This function is for use in the authentication framework only.
     *
     * @param string $guid A GUID to query.
     * @return midcom_core_privilege[]
     */
    public static function get_content_privileges($guid)
    {
        return self::_get_privileges($guid, 'CONTENT');
    }

    /**
     * This is a helper function which lists all privileges assigned
     * directly to a user or group. These are all SELF privileges.
     *
     * This function is for use in the authentication framework only.
     *
     * @param string $guid A GUID to query.
     * @return midcom_core_privilege[]
     */
    public static function get_self_privileges($guid)
    {
        return self::_get_privileges($guid, 'SELF');
    }

    /**
     * This is a static helper function which lists all privileges assigned
     * an object unfiltered.
     *
     * This function is for use in the authentication framework only
     *
     * @param string $guid The GUID of the object for which we should look up privileges.
     * @return midcom_core_privilege[]
     */
    public static function get_all_privileges($guid)
    {
        return array_merge(self::get_content_privileges($guid), self::get_self_privileges($guid));
    }

    /**
     * This is a static helper function which lists all privileges assigned
     * an object unfiltered.
     *
     * @param string $guid The GUID of the object for which we should look up privileges.
     * @return midcom_core_privilege[]
     */
    private static function _get_privileges($guid, $type)
    {
        static $cache = array();

        $cache_key = $type . '::' . $guid;

        if (!array_key_exists($cache_key, $cache))
        {
            $return = midcom::get()->cache->memcache->get('ACL', $cache_key);

            if (! is_array($return))
            {
                // Didn't get privileges from cache, get them from DB
                $return = self::_query_privileges($guid, $type);
                midcom::get()->cache->memcache->put('ACL', $cache_key, $return);
            }

            $cache[$cache_key] = $return;
        }

        return $cache[$cache_key];
    }

    /**
     * This is an internal helper function used by get_privileges in case
     * that there is no cache hit. It will query the database and construct all
     * necessary objects out of it.
     *
     * @param string $guid The GUID of the object for which to query ACL data.
     * @param string $type SELF or CONTENT
     * @return midcom_core_privilege[]
     */
    protected static function _query_privileges($guid, $type)
    {
        $result = array();

        $mc = new midgard_collector('midcom_core_privilege_db', 'objectguid', $guid);
        $mc->add_constraint('value', '<>', MIDCOM_PRIVILEGE_INHERIT);

        if ($type == 'CONTENT')
        {
            $mc->add_constraint('assignee', '<>', 'SELF');
        }
        else
        {
            $mc->add_constraint('assignee', '=', 'SELF');
        }

        $mc->set_key_property('guid');
        $mc->add_value_property('id');
        $mc->add_value_property('privilegename');
        $mc->add_value_property('assignee');
        $mc->add_value_property('classname');
        $mc->add_value_property('value');
        $mc->execute();
        $privileges = $mc->list_keys();

        foreach (array_keys($privileges) as $privilege_guid)
        {
            $privilege = $mc->get($privilege_guid);
            $privilege['objectguid'] = $guid;
            $privilege['guid'] = $privilege_guid;
            $privilege_object = new static($privilege);
            if (!isset($privilege_object->assignee))
            {
                // Invalid privilege, skip
                continue;
            }
            $privilege_object->scope = $privilege_object->_get_scope();
            $result[] = $privilege_object;
        }

        return $result;
    }

    /**
     * This is a helper function which retrieves a single given privilege
     * at a content object, identified by the combination of assignee and privilege
     * name.
     *
     * This call will return an object even if the privilege is set to INHERITED at
     * the given object (i.e. does not exist) for consistency reasons. Errors are
     * thrown for example on database inconsistencies.
     *
     * This function is for use in the authentication framework only.
     *
     * @param object $object The object to query.
     * @param string $name The name of the privilege to query
     * @param string $assignee The identifier of the assignee to query.
     * @param string $classname The optional classname required only for class-limited SELF privileges.
     * @return midcom_core_privilege The privilege matching the constraints.
     */
    public static function get_privilege($object, $name, $assignee, $classname = '')
    {
        $qb = new midgard_query_builder('midcom_core_privilege_db');
        $qb->add_constraint('objectguid', '=', $object->guid);
        $qb->add_constraint('privilegename', '=', $name);
        $qb->add_constraint('assignee', '=', $assignee);
        $qb->add_constraint('classname', '=', $classname);
        $result = @$qb->execute();

        if (empty($result))
        {
            // No such privilege stored, return non-persistent one
            $privilege = new midcom_core_privilege();
            $privilege->set_object($object);
            $privilege->set_assignee($assignee);
            $privilege->privilegename = $name;
            if (! is_null($classname))
            {
                $privilege->classname = $classname;
            }
            $privilege->value = MIDCOM_PRIVILEGE_INHERIT;
            return $privilege;
        }
        else if (count($result) > 1)
        {
            debug_add('A DB inconsistency has been detected. There is more than one record for privilege specified. Deleting all excess records after the first one!',
                MIDCOM_LOG_ERROR);
            debug_print_r('Content Object:', $object);
            debug_add("Privilege {$name} for assignee {$assignee} with classname {$classname} was queried.", MIDCOM_LOG_INFO);
            debug_print_r('Resultset was:', $result);
            midcom::get()->auth->request_sudo('midcom.core');
            while (count($result) > 1)
            {
                $privilege = array_pop($result);
                $privilege->delete();
            }
            midcom::get()->auth->drop_sudo();
        }

        return new midcom_core_privilege($result[0]);
    }

    /**
     * Internal helper function, determines whether a given privilege applies for the given
     * user in content mode. This means, that all SELF privileges are skipped at this point,
     * EVERYONE privileges apply always, and all other privileges are checked against the
     * user.
     *
     * @param string $user_id The user id in question.
     * @return boolean Indicating whether the privilege record applies for the user, or not.
     */
    public function does_privilege_apply($user_id)
    {
        if (!is_array($this->__privilege))
        {
            return false;
        }

        switch ($this->__privilege['assignee'])
        {
            case 'EVERYONE':
                return true;
            case 'ANONYMOUS':
                return ($user_id == 'EVERYONE' || $user_id == 'ANONYMOUS');
            case 'USERS':
                return ($user_id != 'ANONYMOUS' && $user_id != 'EVERYONE');
            default:
                if ($this->__privilege['assignee'] == $user_id)
                {
                    return true;
                }
                if (strstr($this->__privilege['assignee'], 'group:') !== false)
                {
                    $user = midcom::get()->auth->get_user($user_id);
                    if (is_object($user))
                    {
                       return $user->is_in_group($this->__privilege['assignee']);
                    }
                }
                return false;
        }
    }

    private function _load($src)
    {
        if (is_a($src, 'midcom_core_privilege_db'))
        {
            // Got a privilege object as argument, use that
            $this->__guid = $src->guid;
            $this->__privilege_object = $src;
        }
        else if (   is_string($src)
                 && mgd_is_guid($src))
        {
            $this->__guid = $src;
            $this->__privilege_object = new midcom_core_privilege_db($src);
        }
        else
        {
            // Have a nonpersistent privilege
            $this->__privilege_object = new midcom_core_privilege_db();
        }
    }

    private function _sync_to_db_object()
    {
        if (!is_object($this->__privilege_object))
        {
            $this->_load($this->guid);
        }
        $this->__privilege_object->objectguid = $this->objectguid;
        $this->__privilege_object->privilegename = $this->privilegename;
        $this->__privilege_object->assignee = $this->assignee;
        $this->__privilege_object->classname = $this->classname;
        $this->__privilege_object->value = $this->value;
    }

    private function _sync_from_db_object()
    {
        if (!is_object($this->__privilege_object))
        {
            return;
        }
        $this->objectguid = $this->__privilege_object->objectguid;
        $this->privilegename = $this->__privilege_object->privilegename;
        $this->assignee = $this->__privilege_object->assignee;
        $this->classname = $this->__privilege_object->classname;
        $this->value = $this->__privilege_object->value;
        $this->scope = $this->_get_scope();
    }

    /**
     * Store the privilege. This will validate it first and then either
     * update an existing privilege record, or create a new one, depending on the
     * DB state.
     *
     * @return boolean Indicating success.
     */
    function store()
    {
        if (! $this->validate())
        {
            debug_add('This privilege failed to validate, rejecting it, see the debug log for details.', MIDCOM_LOG_WARN);
            $this->__cached_object = null;
            debug_print_r('Privilege dump (w/o cached object):', $this);
            return false;
        }

        $this->_sync_to_db_object();

        if ($this->value == MIDCOM_PRIVILEGE_INHERIT)
        {
            if ($this->__guid)
            {
                // Already a persistent record, drop it.
                return $this->drop();
            }
            // This is a temporary object only, try to load the real object first. If it is not found,
            // exit silently, as this is the desired final state.
            $object = $this->get_object();
            $privilege = $this->get_privilege($object, $this->privilegename, $this->assignee, $this->classname);
            if (!empty($privilege->__guid))
            {
                if (! $privilege->drop())
                {
                    return false;
                }
                $this->_invalidate_cache();
            }
            return true;
        }

        if ($this->__guid)
        {
            if (!$this->__privilege_object->update())
            {
                return false;
            }
            $this->_invalidate_cache();
            return true;
        }

        $object = $this->get_object();
        $privilege = $this->get_privilege($object, $this->privilegename, $this->assignee, $this->classname);
        if (!empty($privilege->__guid))
        {
            $privilege->value = $this->value;
            if (!$privilege->store())
            {
                debug_add('Update of the existing privilege failed.', MIDCOM_LOG_WARN);
                return false;
            }
            $this->__guid = $privilege->__guid;
            $this->objectguid = $privilege->objectguid;
            $this->privilegename = $privilege->privilegename;
            $this->assignee = $privilege->assignee;
            $this->classname = $privilege->classname;
            $this->value = $privilege->value;

            $this->_invalidate_cache();
            return true;
        }

        if (!$this->__privilege_object->create())
        {
            debug_add('Creating new privilege failed: ' . midcom_connection::get_error_string(), MIDCOM_LOG_WARN);
            return false;
        }
        $this->__guid = $this->__privilege_object->guid;
        $this->_invalidate_cache();
        return true;
    }

    /**
     * This is an internal helper called after all I/O operation which invalidates the memcache
     * accordingly.
     */
    private function _invalidate_cache()
    {
        midcom::get()->cache->invalidate($this->objectguid);
    }

    /**
     * Drop the privilege. If we are a known DB record, we delete us, otherwise
     * we return silently.
     *
     * @return boolean Indicating success.
     */
    public function drop()
    {
        $this->_sync_to_db_object();

        if (!$this->__guid)
        {
            debug_add('We are not stored, GUID is empty. Ignoring silently.');
            return true;
        }

        if (! $this->validate())
        {
            debug_add('This privilege failed to validate, rejecting to drop it, see the debug log for details.', MIDCOM_LOG_WARN);
            debug_print_r('Privilege dump:', $this);
            return false;
        }

        if (!$this->__privilege_object->guid)
        {
            // We created this via collector, instantiate a new one
            $privilege = new midcom_core_privilege($this->__guid);
            return $privilege->drop();
        }

        try
        {
            if (!$this->__privilege_object->delete())
            {
                debug_add('Failed to delete privilege record, aborting. Error: ' . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
                return false;
            }
        }
        catch (Exception $e)
        {
            debug_add('Failed to delete privilege record, aborting. Error: ' . $e->getMessage(), MIDCOM_LOG_ERROR);
            return false;
        }

        debug_add("Delete privilege record {$this->__guid} ({$this->__privilege_object->objectguid} {$this->__privilege_object->privilegename} {$this->__privilege_object->assignee} {$this->__privilege_object->value}");

        $this->__privilege_object->purge();
        $this->_invalidate_cache();
        $this->value = MIDCOM_PRIVILEGE_INHERIT;

        return true;
    }
}
