<?php
/**
 * @package net.nehmer.buddylist
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Buddylist Schema callback, post-processes the available categories and makes them
 * accessible. This callback can only be used from within the buddylist component, since
 * it relies on its component context to be correctly initialized.
 *
 * @package net.nehmer.buddylist
 */
class net_nehmer_buddylist_callbacks_categorylister extends midcom_baseclasses_components_purecode
 implements midcom_helper_datamanager2_callback_interface
{
    /**
     * The array with the data we're working on.
     *
     * @var array
     * @access private
     */
    private $_data = null;

    /**
     * Initializes the class to the category listing in the configuration. It does the necessary
     * postprocessing to move the configuration syntax to the rendering one.
     */
    public function __construct($args)
    {
        $this->_component = 'net.nehmer.buddylist';

        parent::__construct();

        $data =& $_MIDCOM->get_custom_context_data('request_data');
        $this->_data = $data['config']->get('categories');
        foreach ($this->_data as $key => $copy)
        {
            $this->_data[$key] = str_replace('|', ': ', $copy);
        }
    }

    /** Ignored. */
    function set_type(&$type) {}

    function get_name_for_key($key)
    {
        return $this->_data[$key];
    }

    function key_exists($key)
    {
        return array_key_exists($key, $this->_data);
    }

    function list_all()
    {
        return $this->_data;
    }
}
?>