<?php
/**
 * @package midcom.workflow
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midcom\workflow;

use midcom_response_styled;
use midcom_core_context;
use midcom;

/**
 * @package midcom.workflow
 */
class viewer extends dialog
{
    public function get_button_config()
    {
        return array
        (
            MIDCOM_TOOLBAR_LABEL => midcom::get()->i18n->get_string('view', 'midcom'),
            MIDCOM_TOOLBAR_OPTIONS => array
            (
                'data-dialog' => 'dialog',
            )
        );
    }

    public function run()
    {
        $context = midcom_core_context::get();
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midcom.workflow/dialog.js');
        midcom::get()->style->append_styledir(__DIR__ . '/style');
        return new midcom_response_styled($context, 'POPUP');
    }
}