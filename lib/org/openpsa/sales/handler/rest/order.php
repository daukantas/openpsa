<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @author Jan Floegel
 * @package org.openpsa.sales
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General
 */

/**
 * org.openpsa.sales rest order handler
 *
 * @package org.openpsa.sales
 */
class org_openpsa_sales_handler_rest_order extends midcom_baseclasses_components_handler_rest
{

    public function get_object_classname()
    {
        return "";
    }

    /**
     * searches for an salesproject the deliverable for the given person can be created for
     * will autogenerate one if none is found
     *
     * @param string $person_guid
     * @return org_openpsa_sales_salesproject_dba
     */
    private function _get_salesproject($person_guid)
    {
        $person = new org_openpsa_contacts_person_dba($person_guid);

        $qb = org_openpsa_sales_salesproject_dba::new_query_builder();
        $qb->add_constraint('customerContact', '=', $person->id);
        $results = $qb->execute();

        if (count($results) > 0)
        {
            return array_pop($results);
        }

        // create a new salesproject
        $salesproject = new org_openpsa_sales_salesproject_dba();
        $salesproject->customerContact = $person->id;

        // add logged in user as salesproject owner
        $salesproject->owner = midcom::get()->auth->user->get_storage()->id;

        $salesproject->title = "";
        if (isset($this->_request['params']['salesproject_title']))
        {
            $salesproject->title = $this->_request['params']['salesproject_title'];
        }
        // add username to salesproject title
        $salesproject->title .= ' ' . $person->rname;
        if (!$salesproject->create())
        {
            $this->_stop("Failed creating salesproject: " . midcom_connection::get_error_string());
        }

        return $salesproject;
    }

    /**
     * create an order
     * this needs to get an person id and product id posted
     */
    public function handle_create()
    {
        $person_guid = isset($this->_request['params']['person_guid']) ? $this->_request['params']['person_guid'] : false;
        $product_id = isset($this->_request['params']['product_id']) ? intval($this->_request['params']['product_id']) : false;
        $run_cycle = isset($this->_request['params']['run_cycle']) ? $this->_request['params']['run_cycle'] : false;

        // check param
        if (!$person_guid || !$product_id)
        {
            $this->_stop("Missing param for creating the order");
        }
        $salesproject = $this->_get_salesproject($person_guid);

        // create deliverable and add it to the salesproject
        // get the product we want to add
        $product = new org_openpsa_products_product_dba($product_id);

        $deliverable = $this->prepare_deliverable($product);
        $deliverable->salesproject = $salesproject->id;

        if (!$deliverable->create())
        {
            $this->_stop("Failed creating deliverable: " . midcom_connection::get_error_string());
        }

        // is a subscription?
        if ($product->delivery == org_openpsa_products_product_dba::DELIVERY_SUBSCRIPTION)
        {
            $continuous = isset($this->_request['params']['continuous']) ? ((bool) $this->_request['params']['continuous']) : false;
            $deliverable->continuous = $continuous;
            // setting schema parameter to subscription
            $deliverable->set_parameter('midcom.helper.datamanager2', 'schema_name', 'subscription');
        }

        // finally, order the product
        if (!$deliverable->order())
        {
            $this->_stop("Failed ordering deliverable: " . midcom_connection::get_error_string());
        }
        if ($run_cycle)
        {
            $deliverable->run_cycle();
        }

        $this->_object = $deliverable;
        $this->_responseStatus = 200;
        $this->_response["guid"] = $this->_object->guid;
        $this->_response["id"] = $this->_object->id;
        $this->_response["message"] = "order created";
    }

    /**
     * Helper function to copy some defaults from the given product to the deliverable
     *
     * @param org_openpsa_products_product_dba $product
     */
    function prepare_deliverable(org_openpsa_products_product_dba $product)
    {
        $deliverable = new org_openpsa_sales_salesproject_deliverable_dba();
        $deliverable->units = 1;
        $deliverable->state = org_openpsa_sales_salesproject_deliverable_dba::STATE_NEW;
        $deliverable->start = gmmktime(0, 0, 0, gmdate('n'), gmdate('j'), gmdate('Y'));

        $deliverable->product = $product->id;
        $deliverable->title = $product->title;
        $deliverable->unit = $product->unit;
        $deliverable->costPerUnit = $product->cost;
        $deliverable->costType = $product->costType;
        $deliverable->pricePerUnit = $product->price;
        $deliverable->orgOpenpsaObtype = $product->delivery;
        $deliverable->description = $product->description;
        $deliverable->supplier = $product->supplier;
        return $deliverable;
    }
}
