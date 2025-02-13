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
class org_openpsa_user_handler_lostpasswordTest extends openpsa_testcase
{
    public function test_handler_lostpassword()
    {
        $data = $this->run_handler('org.openpsa.user', array('lostpassword'));
        $this->assertEquals('lostpassword', $data['handler_id']);

        $user = self::create_user();
        $account = new midcom_core_account($user);
        $old_password = $account->get_password();
        $formdata = array
        (
            'username' => $account->get_username()
        );

        $this->set_dm2_formdata($data['controller'], $formdata);
        $data = $this->run_handler('org.openpsa.user', array('lostpassword'));
        $this->assertEquals(array(), $data['controller']->formmanager->form->_errors);

        $user->refresh();
        $account = new midcom_core_account($user);
        $this->assertEquals('lostpassword', $data['handler_id']);
        $this->assertNotEquals($old_password, $account->get_password());
    }
}
