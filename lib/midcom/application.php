<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Main controlling instance of the MidCOM Framework
 *
 * @property midcom_helper_serviceloader $serviceloader
 * @property midcom_services_i18n $i18n
 * @property midcom_helper__componentloader $componentloader
 * @property midcom_services_dbclassloader $dbclassloader
 * @property midcom_helper__dbfactory $dbfactory
 * @property midcom_helper_head $head
 * @property midcom_helper__styleloader $style
 * @property midcom_services_auth $auth
 * @property midcom_services_permalinks $permalinks
 * @property midcom_services_tmp $tmp
 * @property midcom_services_toolbars $toolbars
 * @property midcom_services_uimessages $uimessages
 * @property midcom_services_metadata $metadata
 * @property midcom_services_rcs $rcs
 * @property midcom_services__sessioning $session
 * @property midcom_services_indexer $indexer
 * @property midcom_config $config
 * @property midcom_services_cache $cache
 * @property midcom\events\dispatcher $dispatcher
 * @property midcom_debug $debug
 * @package midcom
 */
class midcom_application
{
    /**
     * Integer constant resembling the current MidCOM state.
     *
     * See the MIDCOM_STATUS_... constants
     *
     * @var int
     */
    private $_status = null;

    /**
     * Host prefix cache to avoid computing it each time.
     *
     * @var string
     * @see get_host_prefix()
     */
    private $_cached_host_prefix = '';

    /**
     * Page prefix cache to avoid computing it each time.
     *
     * @var string
     * @see get_page_prefix()
     */
    private $_cached_page_prefix = '';

    /**
     * Host name cache to avoid computing it each time.
     *
     * @var string
     * @see get_host_name()
     */
    private $_cached_host_name = '';

    /**
     * Set this variable to true during the handle phase of your component to
     * not show the site's style around the component output. This is mainly
     * targeted at XML output like RSS feeds and similar things. The output
     * handler of the site, excluding the style-init/-finish tags will be executed
     * immediately after the handle phase, and midcom->finish() is called
     * automatically afterwards, thus ending the request.
     *
     * Changing this flag after the handle phase or for dynamically loaded
     * components won't change anything.
     *
     * @var boolean
     */
    public $skip_page_style = false;

    public function __construct()
    {
        midcom_compat_environment::initialize();
        midcom_exception_handler::register();
    }

    /**
     * Magic getter for service loading
     */
    public function __get($key)
    {
        return midcom::get($key);
    }

    /**
     * Magic setter
     */
    public function __set($key, $value)
    {
        return midcom::get()->$key = $value;
    }

    /**
     * Main MidCOM initialization.
     *
     * Initialize the Application class. Sets all private variables to a predefined
     * state.
     */
    public function initialize()
    {
        $this->_status = MIDCOM_STATUS_PREPARE;

        // Start-up some of the services
        $this->dbclassloader->load_classes('midcom', 'legacy_classes.inc');
        $this->dbclassloader->load_classes('midcom', 'core_classes.inc');

        $this->componentloader->load_all_manifests();

        // Initialize Context Storage
        $context = new midcom_core_context(0);
        $context->set_current();
        // Initialize the UI message stack from session
        $this->uimessages->initialize();
    }

    /* *************************************************************************
     * Control framework:
     * codeinit      - Handle the current request
     * content        - Show the current pages output
     * dynamic_load   - Dynamically load and execute a URL
     * finish         - Cleanup Work
     */

    /**
     * Initialize the URL parser and process the request.
     *
     * This function must be called before any output starts.
     *
     * @see _process()
     */
    public function codeinit()
    {
        $context = midcom_core_context::get();

        // Parse the URL
        $context->parser = $this->serviceloader->load('midcom_core_service_urlparser');
        $context->parser->parse(midcom_connection::get_url('argv'));

        $this->_process($context);
    }

    /**
     * Display the output of the component
     *
     * This function must be called in the content area of the
     * Style template, usually <(content)>.
     */
    public function content()
    {
        $this->_output(midcom_core_context::get(0), !$this->skip_page_style);
    }

    /**
     * Dynamically execute a subrequest and insert its output in place of the
     * function call.
     *
     * <b>Important Note</b> As with the Midgard Parser, dynamic_load strips a
     * trailing .html from the argument list before actually parsing it.
     *
     * It tries to load the component referenced with the URL $url and executes
     * it as if it was used as primary component. Additional configuration parameters
     * can be appended through the parameter $config.
     *
     * This is only possible if the system is in the Page-Style output phase. It
     * cannot be used within code-init or during the output phase of another
     * component.
     *
     * Example code, executed on a site's homepage, it will load the news listing from
     * the given URL and display it using a substyle of the node style that is assigned
     * to the loaded one:
     *
     * <code>
     * $blog = '/blog/latest/3/';
     * $substyle = 'homepage';
     * midcom::get()->dynamic_load("/midcom-substyle-{$substyle}/{$blog}");
     * </code>
     *
     * Results of dynamic_loads are cached, by default with the system cache strategy
     * but you can specify separate cache strategy for the DL in the config array like so
     * <code>
     * midcom::get()->dynamic_load("/midcom-substyle-{$substyle}/{$newsticker}", array('cache_module_content_caching_strategy' => 'public'))
     * </code>
     *
     * You can use only less specific strategy than the global strategy, ie basically you're limited to 'memberships' and 'public' as
     * values if the global strategy is 'user' and to 'public' the global strategy is 'memberships', failure to adhere to this
     * rule will result to weird cache behavior.
     *
     * @param string $url                The URL, relative to the Midgard Page, that is to be requested.
     * @param array $config              A key=>value array with any configuration overrides.
     * @return int                       The ID of the newly created context.
     */
    public function dynamic_load($url, $config = array(), $pass_get = false)
    {
        debug_add("Dynamic load of URL {$url}");

        if (substr($url, -5) == '.html')
        {
            $url = substr($url, 0, -5);
        }

        if ($this->_status < MIDCOM_STATUS_CONTENT)
        {
            throw new midcom_error("dynamic_load content request called before content output phase.");
        }

        // Determine new Context ID and set current context,
        // enter that context and prepare its data structure.
        $oldcontext = midcom_core_context::get();
        $context = new midcom_core_context(null, $oldcontext->get_key(MIDCOM_CONTEXT_ROOTTOPIC));
        $uri = midcom_connection::get_url('self') . $url;
        if ($pass_get)
        {
            // Include GET parameters into cache URL
            $uri .= '?GET=' . serialize($_GET);
        }
        $context->set_key(MIDCOM_CONTEXT_URI, $uri);

        $context->set_current();
        /* "content-cache" for DLs, check_hit */
        if ($this->cache->content->check_dl_hit($context->id, $config))
        {
            // The check_hit method serves cached content on hit
            return $context->id;
        }

        // Parser Init: Generate arguments and instantiate it.
        $context->parser = $this->serviceloader->load('midcom_core_service_urlparser');
        $argv = $context->parser->tokenize($url);
        $context->parser->parse($argv);

        $this->_process($context);

        if ($this->_status == MIDCOM_STATUS_ABORT)
        {
            debug_add("Dynamic load _process() phase ended up with 404 Error. Aborting...", MIDCOM_LOG_ERROR);

            // Leave Context
            $oldcontext->set_current();

            return;
        }

        // Start another buffer for caching DL results
        ob_start();

        $this->_output($context, false);

        $dl_cache_data = ob_get_contents();
        ob_end_flush();
        /* Cache DL the content */
        $this->cache->content->store_dl_content($context->id, $config, $dl_cache_data);

        // Leave Context
        $oldcontext->set_current();
        $this->style->enter_context($oldcontext->id);

        return $context->id;
    }

    /**
     * Exit from the framework, execute after all output has been made.
     *
     * Does all necessary clean-up work. Must be called after output is completed as
     * the last call of any MidCOM Page. Best Practice: call it at the end of the ROOT
     * style element.
     *
     * <b>WARNING:</b> Anything done after calling this method will be lost.
     */
    public function finish()
    {
        $this->_status = MIDCOM_STATUS_CLEANUP;

        // Shutdown content-cache (ie flush content to user :) before possibly slow DBA watches
        // done this way since it's slightly less hacky than calling shutdown and then mucking about with the cache->_modules etc
        $this->cache->content->_finish_caching();

        // Shutdown rest of the caches
        $this->cache->shutdown();

        debug_add("End of MidCOM run: {$_SERVER['REQUEST_URI']}");
        _midcom_stop_request();
    }

    /**
     * Process the request
     *
     * Basically this method will parse the URL and search for a component that can
     * handle the request. If one is found, it will process the request, if not, it
     * will report an error, depending on the situation.
     *
     * Details: The logic will traverse the node tree and for each node it will load
     * the component that is responsible for it. This component gets the chance to
     * accept the request (this is encapsulated in the _can_handle call), which is
     * basically a call to can_handle. If the component declares to be able to handle
     * the call, its handle function is executed. Depending if the handle was successful
     * or not, it will either display an HTTP error page or prepares the content handler
     * to display the content later on.
     *
     * If the parsing process doesn't find any component that declares to be able to
     * handle the request, an HTTP 404 - Not Found error is triggered.
     */
    private function _process(midcom_core_context $context)
    {
        $resolver = new midcom_core_resolver($context);
        $handler = $resolver->process();

        if (false === $handler)
        {
            /**
             * Simple: if current context is not '0' we were called from another context.
             * If so we should not break application now - just gracefully continue.
             */
            if ($context->id == 0)
            {
                // We couldn't fetch a node due to access restrictions
                if (midcom_connection::get_error() == MGD_ERR_ACCESS_DENIED)
                {
                    throw new midcom_error_forbidden($this->i18n->get_string('access denied', 'midcom'));
                }
                throw new midcom_error_notfound("This page is not available on this server.");
            }

            $this->_status = MIDCOM_STATUS_ABORT;
            return false;
        }
        $context->run($handler);
    }

    /**
     * Execute the output callback.
     *
     * Launches the output of the currently selected component. It is collected in an output buffer
     * to allow for relocates from style elements.
     *
     * It executes the content_handler that has been determined during the handle
     * phase. It fetches the content_handler from the Component Loader class cache.
     */
    private function _output(midcom_core_context $context, $include_template = true)
    {
        $this->_status = MIDCOM_STATUS_CONTENT;

        // Enter Context
        $oldcontext = midcom_core_context::get();
        if ($oldcontext->id != $context->id)
        {
            debug_add("Entering Context {$context->id} (old Context: {$oldcontext->id})");
            $context->set_current();
        }

        $context->send($include_template);

        // Leave Context
        if ($oldcontext->id != $context->id)
        {
            debug_add("Leaving Context {$context->id} (new Context: {$oldcontext->id})");
            $oldcontext->set_current();
        }
    }

    /* *************************************************************************
     * Framework Access Helper functions
     */

    /**
     * Retrieves the name of the current host, fully qualified with protocol and
     * port.
     *
     * @return string Full Hostname (http[s]://www.my.domain.com[:1234])
     */
    function get_host_name()
    {
        if (! $this->_cached_host_name)
        {
            if (   array_key_exists("SSL_PROTOCOL", $_SERVER)
                ||
                   (   array_key_exists('HTTPS', $_SERVER)
                    && $_SERVER['HTTPS'] == 'on')
                || $_SERVER["SERVER_PORT"] == 443)
            {
                $protocol = "https";
            }
            else
            {
                $protocol = "http";
            }

            $port = "";
            if (strpos($_SERVER['SERVER_NAME'], ':') === false)
            {
                if (   ($protocol == "http" && $_SERVER["SERVER_PORT"] != 80)
                    || ($protocol == "https" && $_SERVER["SERVER_PORT"] != 443))
                {
                    $port = ":" . $_SERVER["SERVER_PORT"];
                }
            }

            $this->_cached_host_name = "{$protocol}://{$_SERVER['SERVER_NAME']}{$port}";
        }

        return $this->_cached_host_name;
    }

    /**
     * Return the prefix required to build relative links on the current site.
     * This includes the http[s] prefix, the hosts port (if necessary) and the
     * base url of the Midgard Page. Be aware, that this does *not* point to the
     * base host of the site.
     *
     * e.g. something like http[s]://www.domain.com[:8080]/host_prefix/page_prefix/
     *
     * @return string The current MidCOM page URL prefix.
     */
    function get_page_prefix()
    {
        if (! $this->_cached_page_prefix)
        {
            $host_name = $this->get_host_name();
            $this->_cached_page_prefix = $host_name . midcom_connection::get_url('self');
        }

        return $this->_cached_page_prefix;
    }

    /**
     * Return the prefix required to build relative links on the current site.
     * This includes the http[s] prefix, the hosts port (if necessary) and the
     * base url of the main host. This is not necessarily the currently active
     * MidCOM Page however, use the get_page_prefix() function for that.
     *
     * e.g. something like http[s]://www.domain.com[:8080]/host_prefix/
     *
     * @return string The host's root page URL prefix.
     */
    function get_host_prefix()
    {
        if (! $this->_cached_host_prefix)
        {
            $host_name = $this->get_host_name();
            $host_prefix = midcom_connection::get_url('prefix');
            if ($host_prefix == '')
            {
                $host_prefix = '/';
            }
            else if ($host_prefix != '/')
            {
                if (substr($host_prefix, 0, 1) != '/')
                {
                    $host_prefix = "/{$host_prefix}";
                }
                if (substr($host_prefix, 0, -1) != '/')
                {
                    $host_prefix .= '/';
                }
            }
            $this->_cached_host_prefix = "{$host_name}{$host_prefix}";
        }

        return $this->_cached_host_prefix;
    }

    /**
     * Get the current MidCOM processing state.
     *
     * @return int    One of the MIDCOM_STATUS_... constants indicating current state.
     */
    function get_status()
    {
        return $this->_status;
    }

    /**
     * Manually override the current MidCOM processing state.
     * Don't use this unless you know what you're doing
     *
     * @param int One of the MIDCOM_STATUS_... constants indicating current state.
     */
    function set_status($status)
    {
        $this->_status = $status;
    }

    /* *************************************************************************
     * Generic Helper Functions not directly related with MidCOM:
     *
     * relocate           - executes a HTTP relocation to the given URL
     */

    /**
     * Sends a header out to the client.
     *
     * This function is syntactically identical to
     * the regular PHP header() function, but is integrated into the framework. Every
     * Header you sent must go through this function or it might be lost later on;
     * this is especially important with caching.
     *
     * @param string $header    The header to send.
     * @param integer $response_code HTTP response code to send with the header
     */
    public function header($header, $response_code = null)
    {
        $this->cache->content->register_sent_header($header);

        if (!is_null($response_code))
        {
            // Send the HTTP response code as requested
            _midcom_header($header, true, $response_code);
        }
        else
        {
            _midcom_header($header);
        }
    }

    /**
     * Relocate to another URL.
     *
     * Helper function to facilitate HTTP relocation (Location: ...) headers. The helper
     * actually can distinguish between site-local, absolute redirects and external
     * redirects. If the url does not start with http[s] or /, it is taken as a URL relative to
     * the current anchor prefix, which gets prepended automatically (no other characters
     * as the anchor prefix get inserted).
     *
     * Fully qualified urls (starting with http[s]) are used as-is.
     *
     * Note, that this function automatically makes the page uncacheable, calls
     * midcom_finish and exit, so it will never return. If the headers have already
     * been sent, this will leave you with a partially completed page, so beware.
     *
     * @param string $url    The URL to redirect to, will be preprocessed as outlined above.
     * @param string $response_code HTTP response code to send with the relocation, from 3xx series
     */
    function relocate($url, $response_code = 302)
    {
        $response = new midcom_response_relocate($url, $response_code);
        $response->send();
    }

    /**
     * Helper function that raises some PHP limits for resource-intensive tasks
     */
    public function disable_limits()
    {
        $stat = @ini_set('max_execution_time', $this->config->get('midcom_max_execution_time'));
        if (false === $stat)
        {
            debug_add('ini_set("max_execution_time", ' . $this->config->get('midcom_max_execution_time') . ') returned false', MIDCOM_LOG_WARN);
        }
        $stat = @ini_set('memory_limit', $this->config->get('midcom_max_memory'));
        if (false === $stat)
        {
            debug_add('ini_set("memory_limit", ' . $this->config->get('midcom_max_memory') . ') returned false', MIDCOM_LOG_WARN);
        }
    }
}
