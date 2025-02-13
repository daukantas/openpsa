<?php
/**
 * @package org.openpsa.reports
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Deliverable reports
 *
 * @package org.openpsa.reports
 */
class org_openpsa_reports_handler_sales_report extends org_openpsa_reports_handler_base
implements org_openpsa_widgets_grid_provider_client
{
    /**
     * {@inheritdoc}
     */
    public function get_qb($field = null, $direction = 'ASC')
    {
        $qb = org_openpsa_invoices_invoice_item_dba::new_query_builder();

        $deliverable_mc = org_openpsa_sales_salesproject_deliverable_dba::new_collector('metadata.deleted', false);
        $deliverable_mc->add_constraint('state', '<>', org_openpsa_sales_salesproject_deliverable_dba::STATE_DECLINED);
        $deliverable_mc->add_constraint('salesproject.state', '<>', org_openpsa_sales_salesproject_dba::STATE_LOST);
        if ($this->_request_data['query_data']['resource'] != 'all')
        {
            $resource_expanded = $this->_expand_resource($this->_request_data['query_data']['resource']);
            $deliverable_mc->add_constraint('salesproject.owner', 'IN', $resource_expanded);
        }
        $qb->add_constraint('deliverable', 'IN', $deliverable_mc->get_values('id'));
        $qb->add_constraint('invoice.sent', '>=', $this->_request_data['start']);
        $qb->add_constraint('invoice.sent', '<=', $this->_request_data['end']);

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function get_row(midcom_core_dbaobject $object)
    {
        $invoices_url = org_openpsa_core_siteconfig::get_instance()->get_node_full_url('org.openpsa.invoices');
        $row = array
        (
            'invoice' => '',
            'index_invoice' => '',
            'owner' => '',
            'index_owner' => '',
            'customer' => '',
            'salesproject' => '',
            'product' => '',
            'item' => '',
            'amount' => 0
        );

        try
        {
            $invoice = org_openpsa_invoices_invoice_dba::get_cached($object->invoice);
            $row['index_invoice'] = $invoice->number;
            $row['invoice'] = $invoice->get_label();
            if ($invoices_url)
            {
                $row['invoice'] = "<a href=\"{$invoices_url}invoice/{$invoice->guid}/\">" . $row['invoice'] . "</a>";
            }
            $deliverable = org_openpsa_sales_salesproject_deliverable_dba::get_cached($object->deliverable);
            $row['deliverable'] = $deliverable->title;
            $product = org_openpsa_products_product_dba::get_cached($deliverable->product);
            $row['product'] = $product->title;
            $salesproject = org_openpsa_sales_salesproject_dba::get_cached($deliverable->salesproject);
            $row['salesproject'] = $salesproject->title;
            $customer = $salesproject->get_customer();
            $row['customer'] = $customer->get_label();
            $owner = org_openpsa_contacts_person_dba::get_cached($salesproject->owner);
            $row['index_owner'] = $owner->name;
            $row['owner'] = org_openpsa_widgets_contact::get($owner->guid)->show_inline();
        }
        catch (midcom_error $e)
        {
            $e->log();
        }
        $row['amount'] = $object->pricePerUnit * $object->units;
        $row['item'] = $object->description;

        return $row;
    }

    public function _on_initialize()
    {
        $this->module = 'sales';
        $this->_initialize_datamanager();
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_generator($handler_id, array $args, array &$data)
    {
        parent::_handler_generator($handler_id, $args, $data);

        $data['invoices'] = Array();

        // Calculate time range
        $data['start'] = $this->_request_data['query_data']['start'];
        $data['end'] = $this->_request_data['query_data']['end'];

        $provider = new org_openpsa_widgets_grid_provider($this, 'local');
        $data['grid'] = $provider->get_grid('deliverable_report_grid');
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_generator($handler_id, array &$data)
    {
        midcom_show_style('sales_report-deliverable-start');

        // Quick workaround to Bergies lazy determination of whether this is user's or everyone's report...
        if ($this->_request_data['query_data']['resource'] == 'user:' . midcom::get()->auth->user->guid)
        {
            // My report
            $data['handler_id'] = 'deliverable_report';
        }
        else
        {
            // Generic report
            $data['handler_id'] = 'sales_report';
        }

        midcom_show_style('sales_report-deliverable-grid');
        midcom_show_style('sales_report-deliverable-end');
    }
}
