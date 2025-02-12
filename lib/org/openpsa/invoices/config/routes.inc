// Match /
'dashboard' => array
(
    'handler' => Array('org_openpsa_invoices_handler_list', 'dashboard'),
),

// Match /projects/
'list_projects_uninvoiced' => array
(
    'fixed_args' => array('projects'),
    'handler' => Array('org_openpsa_invoices_handler_projects', 'uninvoiced'),
),

// Match /list/json/<type>
'list_json_type' => array
(
    'handler' => Array('org_openpsa_invoices_handler_list', 'json'),
    'fixed_args' => array('list', 'json'),
    'variable_args' => 1,
),

// Match /list/customer/all/<company guid>
'list_customer_all' => array
(
    'handler' => Array('org_openpsa_invoices_handler_list', 'customer'),
    'fixed_args' => array('list', 'customer', 'all'),
    'variable_args' => 1,
),

// Match /list/deliverable/<deliverable guid>
'list_deliverable_all' => array
(
    'handler' => Array('org_openpsa_invoices_handler_list', 'deliverable'),
    'fixed_args' => array('list', 'deliverable'),
    'variable_args' => 1,
),

// Match /scheduled/
'list_scheduled' => array
(
    'handler' => Array('org_openpsa_invoices_handler_scheduled', 'list'),
    'fixed_args' => array('scheduled'),
),

// Match /invoice/new/
'invoice_new_nocustomer' => array
(
    'handler' => Array('org_openpsa_invoices_handler_crud', 'create'),
    'fixed_args' => array('invoice', 'new'),
),

// Match /invoice/new/<company guid>
'invoice_new' => array
(
    'handler' => Array('org_openpsa_invoices_handler_crud', 'create'),
    'fixed_args' => array('invoice', 'new'),
    'variable_args' => 1,
),

// Match /invoice/edit/<guid>
'invoice_edit' => array
(
    'handler' => Array('org_openpsa_invoices_handler_crud', 'update'),
    'fixed_args' => array('invoice', 'edit'),
    'variable_args' => 1,
),

// Match /invoice/delete/<guid>
'invoice_delete' => array
(
    'handler' => Array('org_openpsa_invoices_handler_crud', 'delete'),
    'fixed_args' => array('invoice', 'delete'),
    'variable_args' => 1,
),
// Match /invoice/process/
'invoice_process' => array
(
    'handler' => Array('org_openpsa_invoices_handler_action', 'process'),
    'fixed_args' => array('invoice', 'process'),
),

// Match /invoice/recalculation/<guid>
'recalc_invoice' => array
(
    'handler' => Array('org_openpsa_invoices_handler_action', 'recalculation'),
    'fixed_args' => array('invoice', 'recalculation'),
    'variable_args' => 1,
),

// Match /invoice/items/<guid>
'invoice_items' => array
(
    'handler' => Array('org_openpsa_invoices_handler_action', 'items'),
    'fixed_args' => array('invoice', 'items'),
    'variable_args' => 1,
),

// Match /invoice/itemedit/<guid>
'invoice_item_edit' => array
(
    'handler' => Array('org_openpsa_invoices_handler_action', 'itemedit'),
    'fixed_args' => array('invoice', 'itemedit'),
    'variable_args' => 1,
),

// Match /invoice/itemposition/
'invoice_item_position' => array
(
    'handler' => Array('org_openpsa_invoices_handler_action', 'itemposition'),
    'fixed_args' => array('invoice', 'itemposition'),
),

// Match /invoice/pdf/<guid>
'create_pdf' => array
(
    'fixed_args' => array('invoice', 'pdf'),
    'handler' => Array('org_openpsa_invoices_handler_pdf', 'pdf'),
    'variable_args' => 1,
),
// Match /invoice/<guid>
'invoice' => array
(
    'handler' => Array('org_openpsa_invoices_handler_crud', 'read'),
    'fixed_args' => array('invoice'),
    'variable_args' => 1,
),
// Match /billingdata/delete/<guid>
'billing_data_delete' => array
(
    'handler' => array('org_openpsa_invoices_handler_billingdata', 'delete'),
    'fixed_args' => array('billingdata', 'delete'),
    'variable_args' => 1,
),
// Match /billingdata/<customer guid>
'billing_data' => array
(
    'handler' => array('org_openpsa_invoices_handler_billingdata', 'edit'),
    'fixed_args' => array('billingdata'),
    'variable_args' => 1,
),

'invoice_rest' => array(
    'handler' => array('org_openpsa_invoices_handler_rest_invoice', 'process'),
    'fixed_args' => array('rest', 'invoice'),
),

'billingdata_rest' => array(
    'handler' => array('org_openpsa_invoices_handler_rest_billingdata', 'process'),
    'fixed_args' => array('rest', 'billingdata'),
),

// Match /config/
'config' => array
(
    'handler' => array ('midcom_baseclasses_components_handler_configuration', 'config'),
    'fixed_args' => array ('config'),
),

// Match /goto
'goto' => array
(
    'fixed_args' => array('goto'),
    'handler' => Array('org_openpsa_invoices_handler_goto', 'goto'),
),
