<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Copy/delete tree branch viewer
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_copytree extends midgard_admin_asgard_navigation
{
    /**
     * Switch to determine if the whole tree should be copied
     *
     * @var boolean
     */
    public $copy_tree = false;

    /**
     * Switch to determine the visibility of inputs
     *
     * @var boolean
     */
    public $inputs = true;

    /**
     * Choose the target type
     *
     * @var String
     */
    public $input_type;

    /**
     * Choose the target name for the form
     *
     * @var String
     */
    public $input_name;

    /**
     * Show the link to view the object
     *
     * @var boolean
     */
    public $view_link = false;

    /**
     * Show the link to view the object
     *
     * @var boolean
     */
    public $edit_link = false;

    /**
     * Page prefix
     *
     * @var String
     */
    public $page_prefix = '';

    /**
     * Constructor, connect to the parent class constructor.
     *
     * @param mixed $object
     * @param array $request_data
     */
    public function __construct($object, &$request_data)
    {
        parent::__construct($object, $request_data);
        $this->page_prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
    }

    protected function _draw_element($object, $label, $level = 0, $autoexpand = false)
    {
        $ref = $this->_get_reflector($object);
        $type = $ref->mgdschema_class;
        $span_class = '';
        $css_class = $type;
        $this->_common_css_classes($object, $ref, $css_class);
        $this->shown_objects[$object->guid] = true;

        echo "<li class=\"{$css_class}\">\n";

        $label = htmlspecialchars($ref->get_object_label($object));
        $icon = $ref->get_object_icon($object);
        if (empty($label))
        {
            $label = "#{$object->id}";
        }

        if ($this->copy_tree)
        {
            $checked = ' checked="checked"';
        }
        else
        {
            $checked = '';
        }

        if ($this->inputs)
        {
            // This value is used for compiling the exclusion list: if the object is found from this list, but not from the selection list,
            // it means that the selection did not include the object GUID
            echo "<input type=\"hidden\" name=\"all_objects[]\" value=\"{$object->guid}\" />\n";

            echo "<label for=\"item_{$object->guid}\">\n";
            echo "<input id=\"item_{$object->guid}\" type=\"{$this->input_type}\" name=\"{$this->input_name}\" value=\"{$object->guid}\"{$checked} />\n";
        }

        echo "<span class=\"title{$span_class}\">{$icon}{$label}</span>\n";

        // Show the link to the object
        if ($this->view_link)
        {
            echo "<a href=\"{$this->page_prefix}__mfa/asgard/object/view/{$object->guid}/\" class=\"thickbox\" target=\"_blank\" title=\"" . sprintf($this->_l10n->get('%s (%s)'), $label, $ref->get_class_label()) . "\">\n";
            echo "<img src=\"" . MIDCOM_STATIC_URL . "/stock-icons/16x16/view.png\" alt=\"" . $this->_l10n->get('view object') . "\" />\n";
            echo "</a>\n";
        }

        // Show the link to the object
        if ($this->edit_link)
        {
            echo "<a href=\"{$this->page_prefix}__mfa/asgard/object/edit/{$object->guid}/\" target='_blank' title=\"" . sprintf($this->_l10n->get('%s (%s)'), $label, $ref->get_class_label()) . "\">\n";
            echo "<img src=\"" . MIDCOM_STATIC_URL . "/stock-icons/16x16/edit.png\" alt=\"" . $this->_l10n->get('edit object') . "\" />\n";
            echo "</a>\n";
        }

        if ($this->inputs)
        {
            echo "</label>\n";
        }

        // List the child elements
        $this->_list_child_elements($object, $level + 1);

        echo "</li>\n";
    }

    protected function _is_collapsed($type, $total)
    {
        if ($this->inputs)
        {
            return false;
        }
        return parent::_is_collapsed($type, $total);
    }

    /**
     * Draw the tree selector
     */
    public function draw()
    {
        if (!$this->input_type)
        {
            $this->input_type = 'checkbox';
        }

        if (!$this->input_name)
        {
            if ($this->input_type === 'checkbox')
            {
                $this->input_name = 'selected[]';
            }
            else
            {
                $this->input_name = 'target';
            }
        }

        $root_object = $this->_object;
        $this->_list_child_elements($root_object);
    }
}
