<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_sales_salesproject_deliverableTest extends openpsa_testcase
{
    protected $_salesproject;

    public function setUp()
    {
        $this->_salesproject = $this->create_object('org_openpsa_sales_salesproject_dba');
    }

    public function testCRUD()
    {
        midcom::get()->auth->request_sudo('org.openpsa.sales');
        $deliverable = new org_openpsa_sales_salesproject_deliverable_dba();
        $deliverable->salesproject = $this->_salesproject->id;
        $deliverable->plannedUnits = 2.5;
        $deliverable->pricePerUnit = 100;
        $deliverable->_use_activitystream = false;
        $deliverable->_use_rcs = false;

        $stat = $deliverable->create();
        $this->assertTrue($stat, midcom_connection::get_error_string());
        $this->register_object($deliverable);
        $this->assertEquals($deliverable->price, 250);

        $parent = $deliverable->get_parent();
        $this->assertEquals($parent->guid, $this->_salesproject->guid);

        $this->_salesproject->refresh();
        $this->assertEquals($this->_salesproject->value, 250);
        $this->assertEquals($this->_salesproject->profit, 250);

        $deliverable->plannedUnits = 2;
        $stat = $deliverable->update();
        $this->assertTrue($stat);

        $this->_salesproject->refresh();
        $this->assertEquals($this->_salesproject->value, 200);
        $this->assertEquals($this->_salesproject->profit, 200);

        $stat = $deliverable->delete();
        $this->assertTrue($stat);

        $this->_salesproject->calculate_price();
        $this->assertEquals($this->_salesproject->value, 0);
        $this->assertEquals($this->_salesproject->profit, 0);

        midcom::get()->auth->drop_sudo();
    }

    public function testGet_parent()
    {
        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', array('salesproject' => $this->_salesproject->id));
        $parent = $deliverable->get_parent();
        $this->assertEquals($parent->guid, $this->_salesproject->guid);
    }

    /**
     * @dataProvider providerOrder
     * @depends testCRUD
     */
    public function testOrder($attributes, $retval, $results)
    {
        $productgroup = $this->create_object('org_openpsa_products_product_group_dba');
        $attributes['product']['productGroup'] = $productgroup->id;
        $attributes['product']['name'] = __CLASS__ . __FUNCTION__ . time();
        $product = $this->create_object('org_openpsa_products_product_dba', $attributes['product']);

        $attributes['deliverable']['product'] = $product->id;
        $attributes['deliverable']['salesproject'] = $this->_salesproject->id;

        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $attributes['deliverable']);

        midcom::get()->auth->request_sudo('org.openpsa.sales');
        $stat = $deliverable->order();
        midcom::get()->auth->drop_sudo();

        $this->assertEquals($stat, $retval);

        if ($retval === true)
        {
            $salesproject = $this->_salesproject;
            $salesproject->refresh();

            foreach ($results as $type => $values)
            {
                foreach ($values as $field => $value)
                {
                    $this->assertEquals($value, $$type->$field, 'Difference in ' . $type . ' field ' . $field);
                }
            }
        }
    }

    public function providerOrder()
    {
        return array
        (
            0 => array
            (
                'attributes' => array
                (
                    'product' => array
                    (
                        'delivery' => org_openpsa_products_product_dba::DELIVERY_SINGLE,
                        'type' => org_openpsa_products_product_dba::TYPE_GOODS,
                    ),
                    'deliverable' => array
                    (
                        'plannedUnits' => 10,
                        'costPerUnit' => 2,
                    )
                ),
                true,
                'results' => array
                (
                    'deliverable' => array
                    (
                        'plannedUnits' => 10,
                        'state' => org_openpsa_sales_salesproject_deliverable_dba::STATE_ORDERED
                    ),
                    'salesproject' => array
                    (
                        'state' => org_openpsa_sales_salesproject_dba::STATE_WON
                    )
                ),
            ),
            1 => array
            (
                'attributes' => array
                (
                    'product' => array(),
                    'deliverable' => array
                    (
                        'state' => org_openpsa_sales_salesproject_deliverable_dba::STATE_ORDERED
                    )
                ),
                false,
                'results' => array()
            ),
            2 => array
            (
                'attributes' => array
                (
                    'product' => array
                    (
                        'delivery' => org_openpsa_products_product_dba::DELIVERY_SINGLE,
                        'type' => org_openpsa_products_product_dba::TYPE_GOODS,
                    ),
                    'deliverable' => array
                    (
                        'plannedUnits' => 10,
                        'costPerUnit' => 2,
                        'invoiceByActualUnits' => true,
                    )
                ),
                true,
                'results' => array
                (
                    'deliverable' => array
                    (
                        'plannedUnits' => 10,
                        'cost' => 0,
                        'units' => 0,
                        'state' => org_openpsa_sales_salesproject_deliverable_dba::STATE_ORDERED
                    ),
                    'salesproject' => array
                    (
                        'state' => org_openpsa_sales_salesproject_dba::STATE_WON
                    )
                ),
            ),
        );
    }

    /**
     * @depends testCRUD
     */
    public function testDecline()
    {
        $attributes = array
        (
            'salesproject' => $this->_salesproject->id
        );

        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $attributes);

        midcom::get()->auth->request_sudo('org.openpsa.sales');
        $stat = $deliverable->decline();

        $this->assertTrue($stat);

        $this->_salesproject->refresh();
        $this->assertEquals(org_openpsa_sales_salesproject_dba::STATE_LOST, $this->_salesproject->state);
        $this->assertEquals(org_openpsa_sales_salesproject_deliverable_dba::STATE_DECLINED, $deliverable->state);

        $deliverable2 = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $attributes);
        $deliverable3 = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $attributes);
        $this->_salesproject->state = org_openpsa_sales_salesproject_dba::STATE_ACTIVE;
        $this->_salesproject->update();

        $this->assertTrue($deliverable2->decline());
        $this->assertFalse($deliverable2->decline());

        $this->_salesproject->refresh();
        $this->assertEquals(org_openpsa_sales_salesproject_dba::STATE_ACTIVE, $this->_salesproject->state);

        $this->assertTrue($deliverable3->decline());
        $this->_salesproject->refresh();
        $this->assertEquals(org_openpsa_sales_salesproject_dba::STATE_LOST, $this->_salesproject->state);
        midcom::get()->auth->drop_sudo();
    }

    /**
     * @dataProvider providerCalculate_price
     * @depends testCRUD
     */
    public function testCalculate_price($attributes, $results)
    {
        $salesproject = $this->create_object('org_openpsa_sales_salesproject_dba');
        $attributes['salesproject'] = $salesproject->id;

        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $attributes);
        foreach ($results as $key => $value)
        {
            $this->assertEquals($value, $deliverable->$key, $key . ' test failed');
        }
    }

    public function providerCalculate_price()
    {
        return array
        (
            array
            (
                array
                (
                    'invoiceByActualUnits' => true,
                    'plannedUnits' => 1,
                    'units' => 1,
                    'pricePerUnit' => 100,
                    'costPerUnit' => 10,
                    'costType' => 'm',
                ),
                array
                (
                    'price' => 100,
                    'cost' => 10,
                ),
            ),
            array
            (
                array
                (
                    'invoiceByActualUnits' => false,
                    'plannedUnits' => 2,
                    'units' => 1,
                    'pricePerUnit' => 100,
                    'costPerUnit' => 10,
                    'costType' => 'm',
                ),
                array
                (
                    'price' => 200,
                    'cost' => 20,
                ),
            ),
            array
            (
                array
                (
                    'invoiceByActualUnits' => true,
                    'plannedUnits' => 0,
                    'units' => 2,
                    'pricePerUnit' => 100,
                    'costPerUnit' => 10,
                    'costType' => '%',
                ),
                array
                (
                    'price' => 200,
                    'cost' => 20,
                ),
            ),
        );
    }

    public function tearDown()
    {
        self::delete_linked_objects('org_openpsa_sales_salesproject_deliverable_dba', 'salesproject', $this->_salesproject->id);
        parent::tearDown();
    }
}
