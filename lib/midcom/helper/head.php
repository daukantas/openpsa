<?php
/**
 * @package midcom.helper
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Helper functions for managing HTML head
 *
 * @package midcom.helper
 */
class midcom_helper_head
{
    /**
     * Array with all JavaScript declarations for the page's head.
     *
     * @var array
     */
    private $_jshead = array();

    /**
     * Array with all JavaScript file inclusions.
     *
     * @var array
     */
    private $_jsfiles = array();

    /**
     * Array with all prepend JavaScript declarations for the page's head.
     *
     * @var array
     */
    private $_prepend_jshead = array();

    /**
     * Boolean showing if jQuery is enabled
     *
     * @var boolean
     */
    private $_jquery_enabled = false;

    private $_jquery_init_scripts = '';

    /**
     * Array with all JQuery state scripts for the page's head.
     *
     * @var array
     */
    private $_jquery_states = array();

    /**
     * Array with all linked URLs for HEAD.
     *
     * @var Array
     */
    private $_linkhrefs = array();

    /**
     * Array with all methods for the BODY's onload event.
     *
     * @var Array
     */
    private $_jsonload = array();

    /**
     * string with all metatags to go into the page head.
     *
     * @var string
     */
    private $_meta_head = '';

    /**
     * string with all object tags to go into a page's head.
     *
     * @var string
     */
    private $_object_head = '';

    /**
     * String with all css styles to go into a page's head.
     *
     * @var string
     */
    private $_style_head = '';

    /**
     * Array with all link elements to be included in a page's head.
     *
     * @var array
     */
    private $_link_head = array();

    /**
     * Sets the page title for the current context.
     *
     * This can be retrieved by accessing the component context key
     * MIDCOM_CONTEXT_PAGETITLE.
     *
     * @param string $string    The title to set.
     */
    public function set_pagetitle($string)
    {
        midcom_core_context::get()->set_key(MIDCOM_CONTEXT_PAGETITLE, $string);
    }

    /**
     * Register JavaScript File for referring in the page.
     *
     * This allows MidCOM components to register JavaScript code
     * during page processing. The site style code can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced, if you want a JScript
     * clean site, just omit the print calls and you should be fine in almost all
     * cases.
     *
     * The sequence of the add_jsfile and add_jscript commands is kept stable.
     *
     * @param string $url    The URL to the file to-be referenced.
     * @param boolean $prepend Whether to add the JS include to beginning of includes
     * @see add_jscript()
     * @see add_jsonload()
     * @see print_head_elements()
     * @see print_jsonload()
     */
    public function add_jsfile($url, $prepend = false)
    {
        // Adds a URL for a <script type="text/javascript" src="tinymce.js"></script>
        // like call. $url is inserted into src. Duplicates are omitted.
        if (! in_array($url, $this->_jsfiles))
        {
            $this->_jsfiles[] = $url;
            $js_call = array('url' => $url);
            if ($prepend)
            {
                // Add the javascript include to the beginning, not the end of array
                array_unshift($this->_jshead, $js_call);
            }
            else
            {
                $this->_jshead[] = $js_call;
            }
        }
    }

    /**
     * Register JavaScript Code for output directly in the page.
     *
     * This allows components to register JavaScript code
     * during page processing. The site style can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced
     *
     * The sequence of the add_jsfile and add_jscript commands is kept stable.
     *
     * @param string $script    The code to be included directly in the page.
     * @see add_jsfile()
     * @see add_jsonload()
     * @see print_head_elements()
     * @see print_jsonload()
     */
    public function add_jscript($script, $defer = '', $prepend = false)
    {
        $js_call = array('content' => trim($script), 'defer' => $defer);
        if ($prepend)
        {
            $this->_prepend_jshead[] = $js_call;
        }
        else
        {
            $this->_jshead[] = $js_call;
        }
    }

    /**
     * Register JavaScript snippets to jQuery states.
     *
     * This allows components to register JavaScript code to the jQuery states.
     * Possible ready states: document.ready
     *
     * @param string $script    The code to be included in the state.
     * @param string $state    The state where to include the code to. Defaults to document.ready
     * @see print_jquery_statuses()
     */
    public function add_jquery_state_script($script, $state = 'document.ready')
    {
        $js_call = "\n" . trim($script) . "\n";

        if (!isset($this->_jquery_states[$state]))
        {
            $this->_jquery_states[$state] = $js_call;
        }
        else
        {
            $this->_jquery_states[$state] .= $js_call;
        }
    }

    /**
     * Register some object tags to be added to the head element.
     *
     * This allows components to register object tags to be placed in the
     * head section of the page.
     *
     * @param  string $script    The input between the <object></object> tags.
     * @param  array  $attributes Array of attribute=> value pairs to be placed in the tag.
     * @see print_head_elements()
     */
    public function add_object_head ($script, $attributes = null)
    {
        $this->_object_head .= '<object' . $this->_get_attribute_string($attributes) . '>' . $script . "</object>\n";
    }

    /**
     *  Register a metatag to be added to the head element.
     *  This allows components to register metatags to be placed in the
     *  head section of the page.
     *
     *  @param  array  $attributes Array of attribute => value pairs to be placed in the tag.
     *  @see print_head_elements()
     */
    public function add_meta_head($attributes = null)
    {
         $this->_meta_head .= '<meta' . $this->_get_attribute_string($attributes) . ' />' . "\n";
    }

    /**
     * Register a styleblock / style link  to be added to the head element.
     * This allows components to register extra CSS sheets they wants to include.
     * in the head section of the page.
     *
     * @param  string $script    The input between the <style></style> tags.
     * @param  array  $attributes Array of attribute=> value pairs to be placed in the tag.
     * @see print_head_elements()
     */
    public function add_style_head($script, $attributes = null)
    {
        $this->_style_head .= '<style type="text/css"' . $this->_get_attribute_string($attributes) . '>' . $script . "</style>\n";
    }

    private function _get_attribute_string($attributes)
    {
        $string = '';
        if (null === $attributes)
        {
            return $string;
        }
        foreach ($attributes as $key => $val)
        {
            $string .= ' ' . $key . '="' . htmlspecialchars($val, ENT_COMPAT) . '"';
        }
        return $string;
    }

    /**
     * Register a link element to be placed in the page head.
     *
     * This allows components to register extra CSS links.
     * Example to use this to include a CSS link:
     * <code>
     * $attributes = array ('rel' => 'stylesheet',
     *                      'type' => 'text/css',
     *                      'href' => '/style.css'
     *                      );
     * midcom::get()->head->add_link_head($attributes);
     * </code>
     *
     * Each URL will only be added once. When trying to add the same URL a second time,
     * it will be moved to the end of the stack, so that CSS overrides behave as the developer
     * intended
     *
     * @param  array $attributes Array of attribute => value pairs to be placed in the tag.
     * @see print_head_elements()
     */
    public function add_link_head(array $attributes)
    {
        if (!array_key_exists('href', $attributes))
        {
            return false;
        }

        // Register each URL only once
        if ($key = array_search($attributes['href'], $this->_linkhrefs))
        {
            if (end($this->_linkhrefs) != $attributes['href'])
            {
                unset($this->_linkhrefs[$key]);
                $this->_linkhrefs[] = $attributes['href'];
                reset($this->_linkhrefs);
            }
            return false;
        }
        $this->_linkhrefs[] = $attributes['href'];
        $this->_link_head[$attributes['href']] = $attributes;
    }

    /**
     * Convenience shortcut for adding CSS files
     *
     * @param string $url The stylesheet URL
     * @param string $media The media type(s) for the stylesheet, if any
     */
    public function add_stylesheet($url, $media = false)
    {
        $attributes = array
        (
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => $url,
        );
        if ($media)
        {
            $attributes['media'] = $media;
        }
        $this->add_link_head($attributes);
    }

    /**
     * Register a JavaScript method for the body onload event
     *
     * This allows components to register JavaScript code
     * during page processing. The site style can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note that these suggestions are not enforced.
     *
     * @param string $method    The name of the method to be called on page startup, including parameters but excluding the ';'.
     * @see add_jsfile()
     * @see add_jscript()
     * @see print_head_elements()
     * @see print_jsonload()
     */
    public function add_jsonload($method)
    {
        // Adds a method name for <body onload=".."> The string must not end with a ;, it is added automagically
        $this->_jsonload[] = $method;
    }

    /**
     * Echo the registered javascript code.
     *
     * This allows components to register JavaScript code
     * during page processing. The site style code can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY-tag. Note, that these suggestions are not enforced
     *
     * The sequence of the add_jsfile and add_jscript commands is kept stable.
     *
     * This is usually called during the BODY region of your style:
     *
     * <code>
     * <html>
     *     <body <?php midcom::get()->head->print_jsonload();?>>
     *            <!-- your actual body -->
     *     </body>
     * </html>
     * </code>
     *
     * @see add_jsfile()
     * @see add_jscript()
     * @see add_jsonload()
     * @see print_head_elements()
     */
    public function print_jsonload()
    {
        if (count ($this->_jsonload) > 0) {
            $calls = implode("; ", $this->_jsonload);
            echo " onload=\"$calls\" ";
        }
    }

    /**
     * Echo the _head elements added.
     * This function echos the elements added by the add_(style|meta|link|object)_head
     * methods.
     *
     * Place the method within the <head> section of your page.
     *
     * This allows components to register HEAD elements
     * during page processing. The site style can then query this queued-up code
     * at anytime it likes. The queue-up SHOULD be done during the code-init phase,
     * while the print_head_elements output SHOULD be included in the HTML HEAD area and
     * the HTTP onload attribute returned by print_jsonload SHOULD be included in the
     * BODY tag. Note that these suggestions are not enforced
     *
     * @see add_link_head
     * @see add_object_head
     * @see add_style_head
     * @see add_meta_head
     * @see add_jsfile()
     * @see add_jscript()
     */
    public function print_head_elements()
    {
        echo $this->_meta_head;
        foreach ($this->_linkhrefs as $url)
        {
            $attributes = $this->_link_head[$url];
            $is_conditional = false;

            if (array_key_exists('condition', $attributes))
            {
                echo "<!--[if {$attributes['condition']}]>\n";
                $is_conditional = true;
                unset($attributes['condition']);
            }

            echo "<link" . $this->_get_attribute_string($attributes) . " />\n";

            if ($is_conditional)
            {
                echo "<![endif]-->\n";
            }
        }

        echo $this->_object_head;
        echo $this->_style_head;

        if ($this->_jquery_enabled)
        {
            echo $this->_jquery_init_scripts;
        }

        if (!empty($this->_prepend_jshead))
        {
            array_map(array($this, '_print_js'), $this->_prepend_jshead);
        }

        array_map(array($this, '_print_js'), $this->_jshead);
        $this->print_jquery_statuses();
    }

    private function _print_js(array $js_call)
    {
        if (array_key_exists('url', $js_call))
        {
            echo '<script type="text/javascript" src="' . $js_call['url'] . "\"></script>\n";
        }
        else
        {
            echo '<script type="text/javascript"' . $js_call['defer'] . ">\n";
            echo  $js_call['content'] . "\n";
            echo "</script>\n";
        }
    }

    public function get_jshead_elements()
    {
        return $this->_prepend_jshead + $this->_jshead;
    }

    public function get_link_head()
    {
        return $this->_link_head;
    }

    /**
     * Init jQuery
     *
     * This method adds jQuery support to the page
     */
    public function enable_jquery($version = null)
    {
        if ($this->_jquery_enabled)
        {
            return;
        }

        if (!$version)
        {
            $version = midcom::get()->config->get('jquery_version');
        }

        $this->_jquery_init_scripts .= "\n";

        if (midcom::get()->config->get('jquery_load_from_google'))
        {
            // Use Google's hosted jQuery version
            $this->_jquery_init_scripts .= "<script src=\"http://www.google.com/jsapi\"></script>\n";
            $this->_jquery_init_scripts .= "<script>\n";
            $this->_jquery_init_scripts .= "    google.load('jquery', '{$version}');\n";
            $this->_jquery_init_scripts .= "</script>\n";
        }
        else
        {
            $url = MIDCOM_STATIC_URL . "/jQuery/jquery-{$version}.js";
            if (midcom::get()->config->get('jquery_version_oldie'))
            {
                $oldie_url = MIDCOM_STATIC_URL . '/jQuery/jquery-' . midcom::get()->config->get('jquery_version_oldie') . '.js';
                $this->_jquery_init_scripts .= "<!--[if lt IE 9]>\n";
                $this->_jquery_init_scripts .= "<script type=\"text/javascript\" src=\"{$oldie_url}\"></script>\n";
                $this->_jquery_init_scripts .= "<![endif]-->\n";
                $this->_jquery_init_scripts .= "<!--[if gte IE 9]><!-->\n";
                $this->_jquery_init_scripts .= "<script type=\"text/javascript\" src=\"{$url}\"></script>\n";
                $this->_jquery_init_scripts .= "<!--<![endif]-->\n";
            }
            else
            {
                $this->_jquery_init_scripts .= "<script type=\"text/javascript\" src=\"{$url}\"></script>\n";
            }
        }

        if (!defined('MIDCOM_JQUERY_UI_URL'))
        {
            define('MIDCOM_JQUERY_UI_URL', MIDCOM_STATIC_URL . "/jQuery/jquery-ui-" . midcom::get()->config->get('jquery_ui_version'));
        }

        $script  = "var MIDCOM_STATIC_URL = '" . MIDCOM_STATIC_URL . "';\n";
        $script .= "var MIDCOM_PAGE_PREFIX = '" . midcom_connection::get_url('self') . "';\n";

        $this->_jquery_init_scripts .= "<script type=\"text/javascript\">\n";
        $this->_jquery_init_scripts .= trim($script) . "\n";
        $this->_jquery_init_scripts .= "</script>\n";

        $this->_jquery_enabled = true;
    }

    /**
     * Echo the jquery statuses
     *
     * This function echos the scripts added by the add_jquery_state_script
     * method.
     *
     * This method is called from print_head_elements method.
     *
     * @see add_jquery_state_script
     * @see print_head_elements
     */
    public function print_jquery_statuses()
    {
        if (empty($this->_jquery_states))
        {
            return;
        }

        echo '<script type="text/javascript">' . "\n";

        foreach ($this->_jquery_states as $status => $scripts)
        {
            $status_parts = explode('.', $status);
            $status_target = $status_parts[0];
            $status_method = $status_parts[1];
            echo "\njQuery({$status_target}).{$status_method}(function() {\n";
            echo $scripts;
            echo "\n" . '});' . "\n";
        }

        echo '</script>' . "\n";
    }

    /**
     * Add jquery ui components
     *
     * core and widget are loaded automatically. Also loads jquery.ui theme,
     * either the configured theme one or a hardcoded default (base theme)
     *
     * @param array $components The components that should be loaded
     */
    public function enable_jquery_ui(array $components = array())
    {
        $this->enable_jquery();
        $this->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/core.min.js');
        $this->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/widget.min.js');

        foreach ($components as $component)
        {
            $this->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/' . $component . '.min.js');
        }

        if (midcom::get()->config->get('jquery_ui_theme'))
        {
            $this->add_stylesheet(midcom::get()->config->get('jquery_ui_theme'));
        }
        else
        {
            $this->add_stylesheet(MIDCOM_JQUERY_UI_URL . '/themes/base/jquery-ui.min.css');
        }
    }
}
