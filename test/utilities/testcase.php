<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Base class for unittests, provides some helper methods
 *
 * @package openpsa.test
 */
abstract class openpsa_testcase extends PHPUnit_Framework_TestCase
{
    private static $_class_objects = array();
    private $_testcase_objects = array();

    public static function create_user($login = false)
    {
        $person = new midcom_db_person();
        $person->_use_rcs = false;
        $person->_use_activitystream = false;
        $person->extra = substr('p_' . time(), 0, 11);
        $username = uniqid(__CLASS__ . '-user-');

        midcom::get()->auth->request_sudo('midcom.core');
        if (!$person->create())
        {
            throw new Exception('Person could not be created. Reason: ' . midcom_connection::get_error_string());
        }

        $account = new midcom_core_account($person);
        $account->set_password($person->extra);
        $account->set_username($username);
        if (!$account->save())
        {
            throw new Exception('Account could not be saved. Reason: ' . midcom_connection::get_error_string());
        }
        midcom::get()->auth->drop_sudo();
        if ($login)
        {
            if (!midcom::get()->auth->login($username, $person->extra))
            {
                throw new Exception('Login for user ' . $username . ' failed. Reason: ' . midcom_connection::get_error_string());
            }
        }
        self::$_class_objects[$person->guid] = $person;
        //Sync to get password under mgd1
        $person->refresh();
        return $person;
    }

    public static function get_component_node($component)
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        midcom::get()->auth->request_sudo($component);
        if ($topic_guid = $siteconfig->get_node_guid($component))
        {
            $topic = new midcom_db_topic($topic_guid);
        }
        else
        {
            $qb = midcom_db_topic::new_query_builder();
            $qb->add_constraint('component', '=', $component);
            $qb->set_limit(1);
            $qb->add_order('id');
            $result = $qb->execute();
            if (sizeof($result) == 1)
            {
                midcom::get()->auth->drop_sudo();
                return $result[0];
            }

            $root_topic = midcom_db_topic::get_cached(midcom::get()->config->get('midcom_root_topic_guid'));

            $topic_attributes = array
            (
                'up' => $root_topic->id,
                'component' => $component,
                'name' => 'handler_' . get_called_class() . time()
            );
            $topic = self::create_class_object('midcom_db_topic', $topic_attributes);
        }
        midcom::get()->auth->drop_sudo();
        return $topic;
    }

    public function run_handler($topic, array $args = array())
    {
        if (is_object($topic))
        {
            $component = $topic->component;
        }
        else
        {
            $component = $topic;
            $topic = $this->get_component_node($component);
        }
        $root = $topic;
        while ($root->get_parent())
        {
            $root = $root->get_parent();
        }

        $context = new midcom_core_context(null, $root);
        $context->set_current();
        $context->set_key(MIDCOM_CONTEXT_URI, midcom_connection::get_url('self') . $topic->name . '/' . implode('/', $args) . '/');

        // Parser Init: Generate arguments and instantiate it.
        $context->parser = midcom::get()->serviceloader->load('midcom_core_service_urlparser');
        $context->parser->parse($args);
        $handler = $context->get_handler($topic);
        $context->set_key(MIDCOM_CONTEXT_CONTENTTOPIC, $topic);
        $this->assertTrue(is_a($handler, 'midcom_baseclasses_components_interface'), $component . ' found no handler for ./' . implode('/', $args) . '/');

        $result = $handler->handle();
        $this->assertTrue($result !== false, $component . ' handle returned false on ./' . implode('/', $args) . '/');
        $data = $handler->_context_data[$context->id]['handler']->_handler['handler'][0]->_request_data;

        if (   is_object($result)
            && $result instanceof midcom_response)
        {
            $data['__openpsa_testcase_response'] = $result;
        }

        // added to simulate http uri composition
        $_SERVER['REQUEST_URI'] = $context->get_key(MIDCOM_CONTEXT_URI);

        return $data;
    }

    public function show_handler($data)
    {
        $context = midcom_core_context::get();
        $show_handler = $context->get_key(MIDCOM_CONTEXT_SHOWCALLBACK);

        midcom::get()->set_status(MIDCOM_STATUS_CONTENT);
        midcom::get()->style->enter_context($context->id);
        ob_start();
        call_user_func($show_handler, $context->id);
        $output = ob_get_contents();
        ob_end_clean();
        midcom::get()->style->leave_context();
        midcom::get()->set_status(MIDCOM_STATUS_PREPARE);
        return $output;
    }

    public function set_post_data(array $post_data)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $post_data;
        $_REQUEST = $_POST;
    }

    public function set_get_data(array $get_data)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $get_data;
        $_REQUEST = $_GET;
    }

    public function set_dm2_formdata(midcom_helper_datamanager2_controller $controller, array $formdata)
    {
        $formname = substr($controller->formmanager->namespace, 0, -1);
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $form_values = $controller->formmanager->form->exportValues();
        $_POST = array_merge($form_values, $formdata);

        $_POST['_qf__' . $formname] = '';
        $_POST['midcom_helper_datamanager2_save'] = array('');
        $_REQUEST = $_POST;
    }

    public function submit_dm2_form($controller_key, array $formdata, $component, array $args = array())
    {
        $this->reset_server_vars();
        $data = $this->run_handler($component, $args);
        $this->set_dm2_formdata($data[$controller_key], $formdata);

        try
        {
            $data = $this->run_handler($component, $args);
            if (array_key_exists($controller_key, $data))
            {
                $this->assertEquals(array(), $data[$controller_key]->formmanager->form->_errors, 'Form validation failed');
            }
            $this->assertTrue($data['__openpsa_testcase_response'] instanceof midcom_response_relocate, 'Form did not relocate');
            return $data['__openpsa_testcase_response']->url;
        }
        catch (openpsa_test_relocate $e)
        {
            $url = $e->getMessage();
            $url = preg_replace('/^\//', '', $url);
            return $url;
        }
    }

    /**
     * same logic as submit_dm2_form, but this method does not expect a relocate
     */
    public function submit_dm2_no_relocate_form($controller_key, array $formdata, $component, array $args = array())
    {
        $this->reset_server_vars();
        $data = $this->run_handler($component, $args);
        $this->set_dm2_formdata($data[$controller_key], $formdata);
        $data = $this->run_handler($component, $args);

        $this->assertEquals(array(), $data[$controller_key]->formmanager->form->_errors, 'Form validation failed');

        return $data;
    }

    public function get_dialog_url()
    {
        $head_elements = midcom::get()->head->get_jshead_elements();
        foreach (array_reverse($head_elements) as $element)
        {
            if (   !empty($element['content'])
                && preg_match('/refresh_opener\("\/.+?"\);/', $element['content']))
            {
                return preg_replace('/refresh_opener\("\/(.+?)"\);/', '$1', $element['content']);
            }
        }
        $this->fail('No refresh URL found');
    }

    public function run_relocate_handler($component, array $args = array())
    {
        $url = null;
        try
        {
            $data = $this->run_handler($component, $args);
            if (!array_key_exists('__openpsa_testcase_response', $data))
            {
                $data['__openpsa_testcase_response'] = null;
            }
            $this->assertInstanceOf('midcom_response_relocate', $data['__openpsa_testcase_response'], 'handler did not relocate');
            $url = $data['__openpsa_testcase_response']->url;
        }
        catch (openpsa_test_relocate $e)
        {
            $url = $e->getMessage();
        }

        $url = preg_replace('/^\//', '', $url);
        return $url;
    }

    /**
     * @param string $classname
     * @param array $data
     * @return midcom_core_dbaobject
     */
    public function create_object($classname, array $data = array())
    {
        $object = self::_create_object($classname, $data);
        $this->_testcase_objects[$object->guid] = $object;
        return $object;
    }

    /**
     * Register an object created in a testcase. That way, it'll get properly deleted
     * if the test aborts
     */
    public function register_object($object)
    {
        $this->_testcase_objects[$object->guid] = $object;
    }

    /**
     * Register multiple objects created in a testcase. That way, they'll get properly deleted
     * if the test aborts
     */
    public function register_objects(array $array)
    {
        foreach ($array as $object)
        {
            $this->_testcase_objects[$object->guid] = $object;
        }
    }

    private static function _create_object($classname, array $data)
    {
        $presets = array
        (
            '_use_rcs' => false,
            '_use_activitystream' => false,
        );
        $data = array_merge($presets, $data);
        $object = self::prepare_object($classname, $data);

        midcom::get()->auth->request_sudo('midcom.core');
        if (!$object->create())
        {
            throw new Exception('Object of type ' . $classname . ' could not be created. Reason: ' . midcom_connection::get_error_string());
        }
        midcom::get()->auth->drop_sudo();
        return $object;
    }

    public static function prepare_object($classname, array $data)
    {
        $object = new $classname();

        foreach ($data as $field => $value)
        {
            if (strpos($field, '.') !== false)
            {
                $parts = explode('.', $field);
                $object->{$parts[0]}->{$parts[1]} = $value;
                continue;
            }
            $object->$field = $value;
        }
        return $object;
    }

    public static function create_class_object($classname, array $data = array())
    {
        $object = self::_create_object($classname, $data);

        self::$_class_objects[$object->guid] = $object;
        return $object;
    }

    public static function create_persisted_object($classname, array $data = array())
    {
        return self::_create_object($classname, $data);
    }

    public static function delete_linked_objects($classname, $link_field, $id)
    {
        midcom::get()->auth->request_sudo('midcom.core');
        $qb = call_user_func(array($classname, 'new_query_builder'));
        $qb->add_constraint($link_field, '=', $id);
        $results = $qb->execute();

        foreach ($results as $result)
        {
            $result->_use_rcs = false;
            $result->_use_activitystream = false;
            $result->delete();
            $result->purge();
        }
        midcom::get()->auth->drop_sudo();
    }

    public function reset_server_vars()
    {
        if (isset($_SERVER['REQUEST_METHOD']))
        {
            unset($_SERVER['REQUEST_METHOD']);
        }
        if (!empty($_POST))
        {
            $_POST = array();
        }
        if (!empty($_FILES))
        {
            $_FILES = array();
        }
        if (!empty($_GET))
        {
            $_GET = array();
        }
        if (!empty($_REQUEST))
        {
            $_REQUEST = array();
        }
    }

    public function tearDown()
    {
        $this->reset_server_vars();

        if (midcom_core_context::get()->id != 0)
        {
            midcom_core_context::get(0)->set_current();
        }

        if (!midcom::get()->config->get('auth_allow_sudo'))
        {
            midcom::get()->config->set('auth_allow_sudo', true);
        }

        while (midcom::get()->auth->is_component_sudo())
        {
            midcom::get()->auth->drop_sudo();
        }

        //if object is also in class queue, we delay its deletion
        $queue = array_diff_key($this->_testcase_objects, self::$_class_objects);

        self::_process_delete_queue('method', $queue);
        $this->_testcase_objects = array();
        org_openpsa_mail_backend_unittest::flush();
        midcom_compat_unittest::flush_registered_headers();
    }

    public static function TearDownAfterClass()
    {
        self::_process_delete_queue('class', self::$_class_objects);
        self::$_class_objects = array();
        midcom::get()->auth->logout();
    }

    private static function _process_delete_queue($queue_name, $queue)
    {
        midcom::get()->auth->request_sudo('midcom.core');
        $limit = sizeof($queue) * 5;
        $iteration = 0;
        // we reverse the queue here because parents are usually created
        // before their children. Normally, mgd core should catch parent
        // deletion when children exist, but this doesn't always seem to work
        $queue = array_reverse($queue);
        while (!empty($queue))
        {
            $object = array_pop($queue);
            $object->_use_activitystream = false;
            $object->_use_rcs = false;
            try
            {
                $stat = $object->refresh();
                if ($stat === false)
                {
                    // we can only assume this means that the object is already deleted.
                    // Normally, the error codes from core should tell us later on, too, but
                    // they don't seem to be reliable in all versions
                    continue;
                }
                $stat = $object->delete();
            }
            catch (midcom_error $e)
            {
                $stat = false;
            }
            if (!$stat)
            {
                if (   midcom_connection::get_error() == MGD_ERR_HAS_DEPENDANTS
                    || midcom_connection::get_error() == MGD_ERR_OK)
                {
                    array_unshift($queue, $object);
                }
                else if (midcom_connection::get_error() == MGD_ERR_NOT_EXISTS)
                {
                    continue;
                }
                else
                {
                    throw new midcom_error('Cleanup ' . get_class($object) . ' ' . $object->guid . ' failed, reason: ' . midcom_connection::get_error_string());
                }
            }
            else
            {
                $stat = $object->purge();
            }
            if ($iteration++ > $limit)
            {
                $classnames = array();
                foreach ($queue as $obj) {
                    $ref = midcom_helper_reflector::get($obj);
                    $obj_class = get_class($obj) . ' ' . $ref->get_object_label($obj);
                    if (!in_array($obj_class, $classnames)) {
                        $classnames[] = $obj_class;
                    }
                }
                $classnames_string = implode(', ', $classnames);
                throw new midcom_error('Maximum retry count for ' . $queue_name . ' cleanup reached (' . sizeof($queue) . ' remaining entries: ' . $classnames_string . '). Last Midgard error was: ' . midcom_connection::get_error_string());
            }
        }

        midcom::get()->auth->drop_sudo();
    }
}
