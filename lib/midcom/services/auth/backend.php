<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Authentication backend, responsible for validating user/password pairs and
 * mapping them to a given user as well as the "sessioning" part, e.g. the transition
 * of the authentication credentials over several requests.
 *
 * All functions except authenticate() must be implemented, see their individual
 * documentation about what exactly they should do.
 *
 * Configuration, if necessary, should be done using the MidCOM configuration
 * system, prefixing all values with 'auth_backend_$name_', e.g.
 * 'auth_backend_cookie_timeout'.
 *
 * @package midcom.services
 */
abstract class midcom_services_auth_backend
{
    /**
     * This variable holds the user that has been successfully authenticated by the class,
     * it is considered to be read-only.
     *
     * @var midcom_core_user
     */
    var $user;

    /**
     * The ID of the session we are currently using, usable as an authentication token
     * in the login session manager.
     *
     * @var string
     */
    var $session_id;

    /**
     * @var midcom_services_auth
     */
    var $auth = null;

    /**
     * The constructor should do only basic initialization.
     *
     * @param midcom_services_auth $auth Main authentication instance
     */
    public function __construct(midcom_services_auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * This function, always called first in the order of execution, should check
     * whether we have a usable login session. It has to use the login session management
     * system to load a login session. At the end of the successful execution of this
     * function, you have to populate the $session_id and $user members accordingly.
     *
     * @return boolean Return true if the login session was successfully loaded, false
     *     otherwise.
     */
    abstract function read_login_session();

    /**
     * This function checks the given username / password pair is valid and sets
     * the $user member accordingly. The default implementation checks against
     * midcom_connection::login().
     *
     * Normally you should not need to override this function.
     *
     * @param string $username The name of the user to authenticate.
     * @param string $password The password of the user to authenticate.
     * @return boolean Indicating successful authentication.
     */
    function authenticate()
    {
        return $this->auth->sessionmgr->authenticate_session($this->_session_id);
    }

    /**
     * This function stores a login session using the given credentials through the
     * session service. It assumes that no login has concluded earlier. The login
     * session management system is used for authentication. If the login session
     * was created successfully, the _on_login_session_created() handler is called
     * with the $user and $session_id members populated.
     *
     * @param string $username The name of the user to authenticate.
     * @param string $password The password of the user to authenticate.
     * @param string $clientip The client IP to which this session is assigned to. This
     *     defaults to the client IP reported by Apache.
     * @return boolean Indicating success.
     */
    public function create_login_session($username, $password, $clientip = null)
    {
        if ($clientip === null)
        {
            $clientip = $_SERVER['REMOTE_ADDR'];
        }

        $result = $this->auth->sessionmgr->create_login_session($username, $password, $clientip);

        if (!$result)
        {
            // The callee will log errors at this point.
            return false;
        }

        $this->session_id = $result['session_id'];
        $this->user = $result['user'];

        $this->_on_login_session_created();
        return true;
    }

    /**
     * This function stores a trusted login session using the given credentials through the
     * session service. It assumes that no login has concluded earlier. The login
     * session management system is used for authentication. If the login session
     * was created successfully, the _on_login_session_created() handler is called
     * with the $user and $session_id members populated.
     *
     * @param string $username The name of the user to authenticate.
     * @param string $clientip The client IP to which this session is assigned to. This
     *     defaults to the client IP reported by Apache.
     * @return boolean Indicating success.
     */
    public function create_trusted_login_session($username, $clientip = null)
    {
        if ($clientip === null)
        {
            $clientip = $_SERVER['REMOTE_ADDR'];
        }

        $result = $this->auth->sessionmgr->create_trusted_login_session($username, $clientip);

        if (!$result)
        {
            // The callee will log errors at this point.
            return false;
        }

        $this->session_id = $result['session_id'];
        $this->user = $result['user'];

        $this->_on_login_session_created();
        return true;
    }

    /**
     * This event handler is called immediately after the successful creation of a new login
     * session. The authentication driver has to ensure that the login identifier stays
     * available during subsequent requests.
     */
    abstract function _on_login_session_created();

    /**
     * The logout function should delete the currently active login session,
     * which has been loaded by a previous call to read_login_session.
     *
     * You should throw midcom_error if anything goes wrong here.
     */
    function logout()
    {
        if (!$this->session_id)
        {
            debug_add('You were not logged in, so we do nothing.', MIDCOM_LOG_INFO);
            return;
        }

        if (! $this->auth->sessionmgr->delete_session($this->session_id))
        {
            throw new midcom_error('The system could not log you out, check the log file for details.');
        }

        $this->_on_login_session_deleted();

        $this->session_id = null;
    }

    /**
     * This event handler is called immediately after the successful deletion of a login
     * session. Use this to drop any session identifier store you might have created during
     * _on_login_session_created.
     */
    abstract function _on_login_session_deleted();
}
