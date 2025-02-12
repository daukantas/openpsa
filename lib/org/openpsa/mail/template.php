<?php
/**
 * @package org.openpsa.mail
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\introspection\helper;

/**
 * This class contains an email template engine. It can take a template and fill
 * it in with the parameters that have been passed.
 *
 * <b>E-Mail template language</b>
 *
 * Three types of variables can be inserted into the email's subject or body.
 * Every value has an associated key, which is searched as array key in the
 * parameter array. Key names are matched case sensitive.
 *
 * 1. String values
 *
 * They are identified by "__KEY__" and are inserted directly.
 *
 * 2. Associative arrays
 *
 * If you want to pass an array as parameter, ensure that both key and value are
 * convertible to a string by PHP implicitly. Ideally, you have only strings, of
 * course. In the following example, "KEY" refers to the key of the array within
 * the parameter array, and "SUBKEY" refers to the key of a value within the
 * actual array.
 *
 * Again, you can access the (whole) array using "__KEY__". In that case you will
 * get a formatted output of all keys and values, consisting of "SUBKEY: VALUE"
 * entries. The value gets word-wrapped and indented automatically at about 76
 * chars to keep the output easily readable.
 *
 * If you want to access a specific value from this array, you have to use
 * "__KEY_SUBKEY__" to identify it. This syntax is treated like a string value.
 *
 * 3. Generic objects
 *
 * You can pass any object as a value. In this case, the same semantic as with an
 * Array can be used to access the object: "__KEY__" will give you a complete
 * dump, while "__KEY_SUBKEY__" accesses a specific property.
 *
 * The complete dump will omit all properties that are prefixed with an "_";
 * according to the MidCOM namespace conventions, these are private members
 * of a class and should not be touched. You can still access them with the
 * direct index, though this is strongly discouraged within a MidCOM context.
 * Also note that variables with more than one underscore as a prefix might cause
 * trouble with the regular expression used to parse the template.
 *
 * <b>Example usage code</b>
 *
 * <code>
 * $mail = new org_openpsa_mail();
 * $parameters = array
 * (
 *     "RESOURCE" => $this->_resource,
 *     "RESERVATION" => $this->reservation,
 *     "ISOSTART" => $this->dm->data["start"]["strfulldate"],
 *     "ISOEND" => $this->dm->data["end"]["strfulldate"],
 *     "LOCALSTART" => $this->dm->data["start"]["local_strfulldate"],
 *     "LOCALEND" => $this->dm->data["end"]["local_strfulldate"],
 * );
 * $mail->parameters = $parameters;
 * $mail->body = $this->_config_dm->data["mail_newreservation"];
 * $mail->to = $this->dm->data["email"];
 *
 * if (!$mail->send())
 * {
 *     debug_add("Email could not be sent: " . $mail->get_error_string(), MIDCOM_LOG_WARN);
 * }
 * </code>
 *
 * This code could for example use a Template subject / body like this:
 *
 * <pre>
 * Subject: New Reservation for __RESOURCE_name__
 *
 * Your reservation has been received, you will receive a confirmation E-Mail shortly:
 *
 * Start: __ISOSTART__
 * End: __ISOEND__
 * __RESERVATION__
 * </pre>
 *
 * @package org.openpsa.mail
 */
class org_openpsa_mail_template
{
    /**
     * The parameters to use for the Mail template.
     *
     * @var array
     */
    private $_parameters = array();

    private $_patterns = array();

    /**
     * Constructs the template engine and parses the passed parameters
     *
     * @param array $parameters The parameters to replace
     */
    public function __construct (array $parameters)
    {
        $this->_parameters = $parameters;

        foreach ($this->_parameters as $key => $value)
        {
            $this->_patterns[] = "/__({$key})__/";

            if (   is_array($value)
                || is_object($value))
            {
                $this->_patterns[] = "/__({$key})_([^ \.>\"-]*?)__/";
            }
        }
        debug_print_r("Complete list of patterns:", $this->_patterns);
    }

    /**
     * Parses the template and generates the message body and subject.
     *
     * Internally, it relies heavily on Perl Regular Expressions to
     * replace the template parameters with their values.
     *
     * @param string $input The string to parse
     * @return string The parsed string
     */
    function parse($input)
    {
        return preg_replace_callback($this->_patterns, array($this, '_replace_callback'), $input);
    }

    private function _replace_callback($matches)
    {
        $key = $matches[1];
        $value = $this->_parameters[$key];
        if (is_array($value))
        {
            if (empty($matches[2]))
            {
                return $this->_format_array($value);
            }
            return $value[$matches[2]];
        }
        else if (is_object($value))
        {
            if (empty($matches[2]))
            {
                return $this->_format_object($value);
            }
            return $value->{$matches[2]};
        }
        return $value;
    }

    /**
     * Helper function to convert an object into a string representation.
     *
     * Uses word wrapping and skips members beginning with an underscore
     * (which are private per definition). Relies on reflection to parse
     * the object.
     *
     * @param object $obj    Any PHP object that can be parsed with get_object_vars().
     * @return string        String representation.
     */
    private function _format_object($obj)
    {
        $helper = new helper;
        $result = "";
        foreach ($helper->get_object_vars($obj) as $key => $value)
        {
            if (substr($key, 0, 1) == "_")
            {
                continue;
            }
            $key = trim($key);
            if (is_object($value))
            {
                $value = get_class($value) . " object";
                debug_add("The key {$key} contains another object of type {$value}, can't dump this.");
            }
            if (is_array($value))
            {
                $value = "Array";
                debug_add("The key {$key} contains an array, can't dump this.");
            }
            $value = trim($value);
            $result .= "$key: ";
            $result .= wordwrap($value, 74 - strlen($key), "\n" . str_repeat(" ", 2 + strlen($key)));
            $result .= "\n";
        }
        return $result;
    }

    /**
     * Helper function to convert an array into a string representation
     *
     * Uses word wrapping and skips recursive Arrays or objects.
     *
     * @param array $array    The array to be dumped.
     * @return string        String representation.
     */
    private function _format_array(array $array)
    {
        $result = "";
        foreach ($array as $key => $value)
        {
            $key = trim($key);
            if (is_object($value))
            {
                $value = get_class($value) . " object";
                debug_add("The key {$key} contains another object of type {$value}, can't dump this.");
            }

            if (is_array($value))
            {
                $value = "Array";
                debug_add("The key {$key} contains an array, can't dump this.");
            }
            $value = trim($value);
            $result .= "{$key}: ";
            $result .= wordwrap($value, 74 - strlen($key), "\n" . str_repeat(" ", 2 + strlen($key)));
            $result .= "\n";
        }
        return $result;
    }
}
