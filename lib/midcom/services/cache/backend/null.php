<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Database backend that does not do anything.
 * @package midcom.services
 */
class midcom_services_cache_backend_null extends midcom_services_cache_backend
{
    protected function _check_dir()
    {
    }

    function _open($write = false) {}

    function _close() {}

    function get($key)
    {
        return null;
    }

    function put($key, $data)
    {
    }

    function remove($key)
    {
    }

    function remove_all()
    {
    }

    function exists($key)
    {
        return false;
    }
}
