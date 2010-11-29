<?php
/**
 * @package org.openpsa.mypage
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA Personal Summary component
 *
 * @package org.openpsa.mypage
 */
class org_openpsa_mypage_interface extends midcom_baseclasses_components_interface
{
    public function __construct()
    {
        $this->_autoload_libraries = array
        (
            'org.openpsa.core',
        );
    }
}
?>