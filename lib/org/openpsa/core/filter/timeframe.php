<?php
/**
 * @package org.openpsa.core
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Helper class that encapsulates a timeframe filter
 *
 * @package org.openpsa.core
 */
class org_openpsa_core_filter_timeframe extends org_openpsa_core_filter
{
    /**
     * The object field containing the timeframe's start
     *
     * @var string
     */
    private $start;

    /**
     * The object field containing the timeframe's end
     *
     * @var string
     */
    private $end;

    /**
     * Constructor
     *
     * @param string $name The filter's name
     * @param string $start The field representing the timeframe's start
     * @param string $end The field representing the timeframe's end
     */
    public function __construct($name, $start = null, $end = null)
    {
        $this->name = $name;
        $this->start = $start;
        $this->end = $end;
        if (empty($this->start))
        {
            $this->start = $name;
        }
        if (empty($this->end))
        {
            $this->end = $this->start;
        }
    }

    /**
     * Apply filter to given query
     *
     * @param array $selection The filter selection
     * @param midcom_core_query $query The query object
     */
    public function apply(array $selection, midcom_core_query $query)
    {
        if (!empty($selection['to']))
        {
            $query->add_constraint($this->start, '<=', strtotime($selection['to'] . ' 23:59:59'));
        }
        if (!empty($selection['from']))
        {
            $query->add_constraint($this->end, '>=', strtotime($selection['from'] . ' 00:00:00'));
        }
        $this->_selection = $selection;
    }

    public function add_head_elements()
    {
        midcom_helper_datamanager2_widget_jsdate::add_head_elements();
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $ids = array
        (
            'from' => 'datepicker_' . $this->name . '_from',
            'to' => 'datepicker_' . $this->name . '_to',
        );
        $to_value = (!empty($this->_selection['to'])) ? $this->_selection['to'] : '';
        $from_value = (!empty($this->_selection['from'])) ? $this->_selection['from'] : '';

        echo $this->_label . ': ';
        echo '<input class="filter_input" type="text" name="' . $this->name . '[from]" id="' . $ids['from'] . '" value="' . $from_value . '" />';
        echo '<input class="filter_input" type="text" name="' . $this->name . '[to]" id="' . $ids['to'] . '" value="' . $to_value . '" />';
        $this->_render_actions();

        echo '<script type="text/javascript">';
        echo "\$(document).ready(function()\n{\n\norg_openpsa_filter.init_timeframe(\n";
        echo json_encode($ids) . " );\n});\n";
        echo "\n</script>\n";
    }
}