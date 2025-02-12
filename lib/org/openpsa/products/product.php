<?php
/**
 * @package org.openpsa.products
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package org.openpsa.products
 */
class org_openpsa_products_product_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_products_product';

    const DELIVERY_SINGLE = 1000;
    const DELIVERY_SUBSCRIPTION = 2000;

    /**
     * Professional services
     */
    const TYPE_SERVICE = 1000;

    /**
     * Material goods
     */
    const TYPE_GOODS = 2000;

    /**
     * Solution is a nonmaterial good
     */
    const TYPE_SOLUTION = 2001;

    public function get_path(midcom_db_topic $topic)
    {
        $path = $this->code ?: $this->guid;
        try
        {
            $parent = org_openpsa_products_product_group_dba::get_cached($this->productGroup);
            $config = new midcom_helper_configuration($topic, 'org.openpsa.products');

            if ($config->get('root_group'))
            {
                $root_group = org_openpsa_products_product_group_dba::get_cached($config->get('root_group'));
                if ($root_group->id != $parent->id)
                {
                    $qb_intree = org_openpsa_products_product_group_dba::new_query_builder();
                    $qb_intree->add_constraint('up', 'INTREE', $root_group->id);
                    $qb_intree->add_constraint('id', '=', $parent->id);

                    if ($qb_intree->count() == 0)
                    {
                        return null;
                    }
                    //Check if the product is in a nested category.
                    if (!empty($parent->up))
                    {
                        $parent = org_openpsa_products_product_group_dba::get_cached($parent->up);
                    }
                }
            }

            $path = $parent->code . '/' . $path;
        }
        catch (midcom_error $e)
        {
            $e->log();
        }
        return $path . '/';
    }

    public function render_link()
    {
        $siteconfig = new org_openpsa_core_siteconfig();

        if ($products_url= $siteconfig->get_node_full_url('org.openpsa.products'))
        {
            return '<a href="' . $products_url . 'product/' . $this->guid . '/">' . $this->title . "</a>\n";
        }
        return $this->title;
    }

    public function _on_creating()
    {
        if (!$this->validate_code($this->code))
        {
            midcom_connection::set_error(MGD_ERR_OBJECT_NAME_EXISTS);
            return false;
        }
        return true;
    }

    public function _on_updating()
    {
        if (!$this->validate_code($this->code))
        {
            midcom_connection::set_error(MGD_ERR_OBJECT_NAME_EXISTS);
            return false;
        }
        return true;
    }

    function validate_code($code)
    {
        if ($code == '')
        {
            return true;
        }

        // Check for duplicates
        $qb = org_openpsa_products_product_dba::new_query_builder();
        $qb->add_constraint('code', '=', $code);

        if (!empty($this->id))
        {
            $qb->add_constraint('id', '<>', $this->id);
        }
        // Make sure the product is in the same product group
        $qb->add_constraint('productGroup', '=', (int)$this->productGroup);

        return ($qb->count() == 0);
    }
}
