<?php
/**
 * @package midcom.baseclasses
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Generic CSV export handler baseclass
 *
 * @package midcom.baseclasses
 */
abstract class midcom_baseclasses_components_handler_dataexport extends midcom_baseclasses_components_handler
{
    /**
     * The Datamanager of the objects to export.
     *
     * @var midcom_helper_datamanager2_datamanager[] Array of datamanager instances
     */
    private $_datamanagers = array();

    /**
     * Flag indicating whether or not the GUID of the first type should be included in exports.
     *
     * @var boolean
     */
    public $include_guid = true;

    /**
     * Flag indicating whether or not totals for number fields should be generated
     *
     * @var boolean
     */
    public $include_totals = false;

    public $csv = array();

    var $_schema;

    var $_rows = array();

    private $_totals = array();

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    public function _prepare_request_data()
    {
        $this->_request_data['datamanagers'] =& $this->_datamanagers;
        $this->_request_data['rows'] =& $this->_rows;
    }

    /**
     * Internal helper, loads the datamanagers for the given types. Any error triggers a 500.
     */
    public function _load_datamanagers(array $schemadbs)
    {
        if (empty($this->_schema))
        {
            throw new midcom_error('Export schema ($this->_schema) must be defined, hint: do it in "_load_schemadb"');
        }
        foreach ($schemadbs as $type => $schemadb)
        {
            $this->_datamanagers[$type] = new midcom_helper_datamanager2_datamanager($schemadb);

            if (array_key_exists($this->_schema, $schemadb))
            {
                $schema_name = $this->_schema;
            }
            else
            {
                $schema_name = key($schemadb);
            }

            if (!$this->_datamanagers[$type]->set_schema($schema_name))
            {
                throw new midcom_error("Failed to create a DM2 instance for schemadb schema '{$schema_name}'.");
            }
        }
    }

    abstract function _load_schemadbs($handler_id, &$args, &$data);

    abstract function _load_data($handler_id, &$args, &$data);

    public function _handler_csv($handler_id, array $args, array &$data)
    {
        midcom::get()->auth->require_valid_user();
        $this->_load_datamanagers($this->_load_schemadbs($handler_id, $args, $data));

        if (empty($args[0]))
        {
            //We do not have filename in URL, generate one and redirect
            $fname = preg_replace('/[^a-z0-9-]/i', '_', strtolower($this->_topic->extra)) . '_' . date('Y-m-d') . '.csv';
            if (strpos(midcom_connection::get_url('uri'), '/', strlen(midcom_connection::get_url('uri')) - 2))
            {
                return new midcom_response_relocate(midcom_connection::get_url('uri') . $fname);
            }
            else
            {
                return new midcom_response_relocate(midcom_connection::get_url('uri') . "/{$fname}");
            }
        }

        midcom::get()->disable_limits();

        $rows = $this->_load_data($handler_id, $args, $data);
        if (count($this->_datamanagers) == 1)
        {
            foreach ($rows as $row)
            {
                $this->_rows[] = array($row);
            }
        }
        else
        {
            $this->_rows = $rows;
        }

        if (empty($data['filename']))
        {
            $data['filename'] = str_replace('.csv', '', $args[0]);
        }

        $this->_init_csv_variables();
        midcom::get()->skip_page_style = true;

        midcom::get()->cache->content->content_type($this->csv['mimetype']);
        midcom::get()->header('Content-Disposition: filename=' . $data['filename']);
    }

    public function _show_csv($handler_id, array &$data)
    {
        // Make real sure we're dumping data live
        midcom::get()->cache->content->enable_live_mode();
        while(@ob_end_flush());

        // Dump headers
        reset($this->_datamanagers);
        $first_type = key($this->_datamanagers);
        $multiple_types = count($this->_datamanagers) > 1;
        $row = array();
        if ($this->include_guid)
        {
            $row[] = $first_type . ' GUID';
        }

        foreach ($this->_datamanagers as $type => $datamanager)
        {
            foreach ($datamanager->schema->field_order as $name)
            {
                $title =& $datamanager->schema->fields[$name]['title'];
                $fieldtype =& $datamanager->schema->fields[$name]['type'];
                if (   $this->include_totals
                    && $fieldtype == 'number')
                {
                    $this->_totals[$type . '-' . $name] = 0;
                }
                $title = $this->_l10n->get($title);
                if ($multiple_types)
                {
                    $title = $this->_l10n->get($type) . ': ' . $title;
                }
                $row[] = $title;
            }
        }
        $this->_print_row($row);

        $this->_dump_rows();

        if ($this->include_totals)
        {
            $row = array();
            foreach ($this->_datamanagers as $type => $datamanager)
            {
                foreach ($datamanager->schema->field_order as $name)
                {
                    $fieldtype =& $datamanager->schema->fields[$name]['type'];
                    $value = "";
                    if ($fieldtype == 'number')
                    {
                        $value = $this->_totals[$type . '-' . $name];
                    }
                    $row[] = $value;
                }
            }
            $this->_print_row($row);
        }
        // restart ob to keep MidCOM happy
        ob_start();
    }

    private function _dump_rows()
    {
        reset($this->_datamanagers);
        $first_type = key($this->_datamanagers);
        // Output each row
        foreach ($this->_rows as $num => $row)
        {
            $output = array();
            foreach ($this->_datamanagers as $type => $datamanager)
            {
                if (!array_key_exists($type, $row))
                {
                    debug_add("row #{$num} does not have {$type} set", MIDCOM_LOG_INFO);
                    $target_size = count($datamanager->schema->field_order) + count($output);
                    $output = array_pad($output, $target_size, '');
                    continue;
                }
                $object =& $row[$type];

                if (!$datamanager->set_storage($object))
                {
                    // Major error, panic
                    throw new midcom_error( "Could not set_storage for row #{$num} ({$type} {$object->guid})");
                }

                if (   $this->include_guid
                    && $type == $first_type)
                {
                    $output[] = $object->guid;
                }

                foreach ($datamanager->schema->field_order as $fieldname)
                {
                    $fieldtype = $datamanager->schema->fields[$fieldname]['type'];
                    $data = '';
                    $data = $datamanager->types[$fieldname]->convert_to_csv();
                    if (   $this->include_totals
                        && $fieldtype == 'number')
                    {
                        $this->_totals[$type . '-' . $fieldname] += $data;
                    }
                    $output[] = $data;
                }
            }
            $this->_print_row($output);
        }
    }

    private function _print_row(array $row)
    {
        $row = array_map(array($this, 'encode_csv'), $row);
        echo implode($this->csv['s'], $row);
        echo $this->csv['nl'];
        flush();
    }

    private function _init_csv_variables()
    {
        // FIXME: Use global configuration
        $this->csv['s'] = $this->_config->get('csv_export_separator') ?: ';';
        $this->csv['q'] = $this->_config->get('csv_export_quote') ?: '"';
        if (empty($this->csv['d']))
        {
            $this->csv['d'] = $this->_l10n_midcom->get('decimal point');
        }
        if ($this->csv['s'] == $this->csv['d'])
        {
            throw new midcom_error("CSV decimal separator (configured as '{$this->csv['d']}') may not be the same as field separator (configured as '{$this->csv['s']}')");
        }
        $this->csv['nl'] = $this->_config->get('csv_export_newline') ?: "\n";
        $this->csv['charset'] = $this->_config->get('csv_export_charset');
        if (empty($this->csv['charset']))
        {
            // Default to ISO-LATIN-15 (Latin-1 with EURO sign etc)
            $this->csv['charset'] = 'ISO-8859-15';
            if (   isset($_SERVER['HTTP_USER_AGENT'])
                && !preg_match('/Windows/i', $_SERVER['HTTP_USER_AGENT']))
            {
                // Excep when not on windows, then default to UTF-8
                $this->csv['charset'] = 'UTF-8';
            }
        }
        $this->csv['mimetype'] = $this->_config->get('csv_export_content_type') ?: 'appplication/csv';
    }

    public function encode_csv($data)
    {
        /* START: Quick'n'Dirty on-the-fly charset conversion */
        if ($this->csv['charset'] !== 'UTF-8')
        {
            $to_charset = "{$this->csv['charset']}//TRANSLIT";
            // Ragnaroek-todo: use try-catch here to avoid trouble with the error_handler if iconv gets whiny
            $stat = @iconv('UTF-8', $to_charset, $data);
            if (!empty($stat))
            {
                $data = $stat;
            }
        }
        /* END: Quick'n'Dirty on-the-fly charset conversion */

        if (is_numeric($data))
        {
            // Decimal point format
            $data = str_replace('.', $this->csv['d'], $data);
        }

        // Strings and numbers beginning with zero are quoted
        if (   !empty($data)
            && (   !is_numeric($data)
                || preg_match('/^[0+]/', $data)))
        {
            // Make sure we have only newlines in data
            $data = preg_replace("/\n\r|\r\n|\r/", "\n", $data);
            // Escape quotes (PONDER: make configurable between doubling the character and escaping)
            $data = str_replace($this->csv['q'], '\\' . $this->csv['q'], $data);
            // Quote
            $data = "{$this->csv['q']}{$data}{$this->csv['q']}";
        }

        return $data;
    }
}
