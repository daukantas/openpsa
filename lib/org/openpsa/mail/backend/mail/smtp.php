<?php
/**
 * @package org.openpsa.mail
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Send backend for org_openpsa_mail
 *
 * @package org.openpsa.mail
 */
class org_openpsa_mail_backend_mail_smtp extends org_openpsa_mail_backend
{
    public function __construct(array $params)
    {
        $transport = Swift_SmtpTransport::newInstance($params['host'], $params['port']);
        if (isset($params['username']))
        {
            $transport->setUsername($params['username']);
        }
        if (isset($params['password']))
        {
            $transport->setPassword($params['password']);
        }
        $this->_mail = Swift_Mailer::newInstance($transport);
    }

    public function mail(org_openpsa_mail_message $message)
    {
        return $this->_mail->send($message->get_message());
    }
}
