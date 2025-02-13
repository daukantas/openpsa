<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * DBA-Style flat file database caching backend.
 *
 * Uses DBA database locking for synchronization.
 *
 * <b>Configuration options:</b>
 *
 * - <i>string handler</i> Defines the DBA handler to use. If omitted, autodetection
 *   is attempted.
 *
 * @package midcom.services
 */
class midcom_services_cache_backend_dba extends midcom_services_cache_backend
{
    /**
     * The handler to use
     *
     * @var string
     */
    private $_handler;

    /**
     * The full database filename.
     *
     * @var string
     */
    private $_filename;

    /**
     * The current handle, controlled by _open() and _close().
     *
     * @var resource
     */
    private $_handle;

    /**
     * This handler completes the configuration.
     */
    public function _on_initialize()
    {
        // We need to serialize data
        $this->_auto_serialize = true;

        if (array_key_exists('handler', $this->_config))
        {
            $this->_handler = $this->_config['handler'];
        }
        else
        {
            $handlers = dba_handlers();
            foreach (array('db4', 'db3', 'db2', 'gdbm', 'flatfile') as $handler)
            {
                if (in_array($handler, $handlers))
                {
                    $this->_handler = $handler;
                    break;
                }
            }
            if (!$this->_handler)
            {
                debug_print_r("Failed autodetection of a usable DBA handler. Found handlers were:", $handlers);
                throw new midcom_error('Failed autodetection of a usable DBA handler');
            }
        }
        $this->_filename = "{$this->_cache_dir}{$this->_name}.{$this->_handler}";

        // Check for file existence by opening it once for write access.
        if (! file_exists($this->_filename))
        {
            $handle = dba_open($this->_filename, 'c', $this->_handler);
            if ($handle === false)
            {
                throw new midcom_error("Failed to open the database {$this->_filename} for creation.");
            }
            dba_close($handle);
        }

        debug_add("DBA Cache backend '{$this->_name}' initialized to file: {$this->_filename}");
    }

    /**
     * Internal helper, which opens a handle to the DBA file in either
     * read-only or read/write mode. The handle has to be closed by the
     * _close() function.
     *
     * The handle is stored in $_handle
     *
     * @param boolean $write Set to true to enable read/write access with the corresponding exclusive lock. Otherwise
     *        shared read-only mode is used.
     */
    function _open($write = false)
    {
        if ($write)
        {
            $handle = @dba_open($this->_filename, 'w', $this->_handler);
        }
        else
        {
            $handle = @dba_open($this->_filename, 'r', $this->_handler);
        }
        if ($handle === false)
        {
            midcom::get()->debug->log_php_error(MIDCOM_LOG_ERROR);
            throw new midcom_error("Failed to open the database {$this->_filename} (Write-mode: {$write})");
        }
        $this->_handle = $handle;
    }

    function _close()
    {
        if ($this->_handle == null)
        {
            debug_add("There was a null handle in the DBA backend, ignoring close request.");
            return;
        }
        dba_close($this->_handle);
        $this->_handle = null;
    }

    function _get($key)
    {
        if (! $this->_exists($key))
        {
            throw new midcom_error("Failed to read key {$key} from the database {$this->_filename}: The key does not exist.");
        }
        $result = @dba_fetch($key, $this->_handle);
        if ($result === false)
        {
            midcom::get()->debug->log_php_error(MIDCOM_LOG_ERROR);
            throw new midcom_error("Failed to read key {$key} from the database {$this->_filename}");
        }
        return $result;
    }

    function _put($key, $data)
    {
        if (! @dba_replace($key, $data, $this->_handle))
        {
            midcom::get()->debug->log_php_error(MIDCOM_LOG_ERROR);
            throw new midcom_error("Failed to write key {$key} to the database {$this->_filename}");
        }
    }

    function _remove($key)
    {
        if (! $this->_exists($key))
        {
            debug_add("The key {$key} does not exist, so not removing it.");
            return;
        }
        if (! @dba_delete($key, $this->_handle))
        {
            midcom::get()->debug->log_php_error(MIDCOM_LOG_ERROR);
            throw new midcom_error("Failed to remove key {$key} from the database {$this->_filename}");
        }
    }

    function _remove_all()
    {
        // This will open the database in truncate/write mode once to clear the file.
        $handle = @dba_open($this->_filename, 'n', $this->_handler);
        if ($handle === false)
        {
            midcom::get()->debug->log_php_error(MIDCOM_LOG_ERROR);
            throw new midcom_error("Failed to truncate the database {$this->_filename}");
        }
        dba_close($handle);
    }

    function _exists($key)
    {
        return dba_exists($key, $this->_handle);
    }
}
