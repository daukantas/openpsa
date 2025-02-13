<?php
/**
 * @package midcom
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Setup helper
 *
 * @package midcom
 */
class midcom_core_setup
{
    public function __construct($message)
    {
        midcom::get()->auth->require_admin_user($message);
    }

    /**
     * Write config file
     *
     * @param midcom_db_topic $topic
     */
    public function write_config(midcom_db_topic $topic)
    {
        $conf = '<?php' . "\n";
        $conf .= "//AUTO-GENERATED on " . strftime('%x %X') . "\n";
        $conf .= '$GLOBALS[\'midcom_config_local\'][\'midcom_root_topic_guid\'] = "' . $topic->guid . '";' . "\n";

        $project_dir = dirname(dirname(dirname(__DIR__)));
        if (strpos($project_dir, '/vendor/'))
        {
            $project_dir = dirname(dirname(dirname($project_dir)));
        }

        if (!@file_put_contents($project_dir . '/config.inc.php', $conf))
        {
            echo "Please save the following under <code>" . $project_dir . '/config.inc.php</code><br>';
            echo '<textarea rows="5" cols="100">' . $conf . "</textarea>";
            midcom::get()->finish();
        }
    }

    /**
     * @return midcom_db_topic
     */
    public function find_topic($autocreate = false)
    {
        $qb = midcom_db_topic::new_query_builder();
        $qb->add_constraint('up', '=', 0);
        $qb->add_constraint('component', '<>', '');
        $topics = $qb->execute();

        if (count($topics) > 0)
        {
            return $topics[0];
        }
        if (!$autocreate)
        {
            throw new midcom_error('Fatal error: Unable to find website root folder');
        }

        $topic = new midcom_db_topic;
        $topic->component = 'midcom.core.nullcomponent';
        if (!$topic->create())
        {
            throw new midcom_error('Fatal error: Failed to create root folder: ' . midcom_connection::get_error_string());
        }
        $this->write_config($topic);
        return $topic;
    }
}