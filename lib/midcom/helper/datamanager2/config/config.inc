'default_renderer' => 'default',
'default_renderer_src' => null,
/**
 * Whether to verify that ImageMagick is in fact available (in type_image(s)) every time
 * it's needed or not. This can be adjusted in type_config as well.
 *
 * Setting this to false will win some performance but in case ImageMagick is/goes missing
 * You will also gain really weird and hard-to-debug problems with images handling
 */
'verify_imagemagick' => true,

'schemadb_delete' => "file://midcom/helper/datamanager2/config/schemadb_default_delete.inc",

// TinyMCE Defaults
// The Theme is only used if the config snippet is not set (an invalid snippet name
// will be silently ignored).
'tinymce_default_theme' => 'simple',
'tinymce_default_config_snippet' => "file://midcom/helper/datamanager2/config/tinymce_default",
'tinymce_use_compressor' => false,
'tinymce_url' => MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/tinymce',
//'tinymce_default_config_snippet' => midcom::get()->config->get('midcom_sgconfig_basedir') . "/midcom.helper.datamanager2/tinymce",

'codemirror_config_snippet' => "file://midcom/helper/datamanager2/config/codemirror_default",

'html_purify' => true,
'html_purify_config' => array
(
    'HTML.Attr.Name.UseCDATA' => true,
    'HTML' => array
    (
        /**
         * These two must be changed to something unique to your situation
         * if you change any of the html_purify_xx configs
         *
         * DEVELOPERS: If you change these defaults remember to bump DefinitionRev
         */
        'DefinitionID' => 'DM2 Default definition set',
        'DefinitionRev' => 3,
        /* NOTE: You must use transitional doctype if you want to use <a name> (see html_purify_HTMLDefinition below) */
        'Doctype' => 'XHTML 1.0 Transitional',
        'TidyLevel' => 'light',
        'SafeObject' => true,
    ),
    'Attr' => array
    (
        /**
         * To disable mangling of id/name attributes in content set this to empty string
         */
        'IDPrefix' => 'user_',
        'AllowedFrameTargets' => array
        (
            '_blank',
            '_self',
            '_top',
        ),
        'EnableID' => true,
    ),
    'Cache' => array
    (
        'SerializerPath' => midcom::get()->config->get('cache_base_directory') . 'htmlpurifier',
        /* Disable cache while testing
        'DefinitionImpl' => null,
        */
    ),
    'Output' => array
    (
        'FlashCompat' => true,
    ),
),
'html_purify_HTMLDefinition' => array
(
    /**
     * each value passed in order with call_user_func_array to $def->addAttribute()
     *
     * See http://htmlpurifier.org/docs/enduser-customize.html
     */
    'addAttribute' => array
    (
        array('a', 'name', 'Text'),
        array('a', 'rel', 'Text'),
        array('img', 'hspace', 'Length'),
        array('img', 'vspace', 'Length'),
    ),
    /**
     * each value passed in order with call_user_func_array to $def->addElement()
     *
     * See http://htmlpurifier.org/docs/enduser-customize.html
     */
    'addElement' => array
    (
    ),
),
'html_purify_markdown' => true,

'include_metadata_required' => false,

/* This is passed to sprintf, use %s where file name should be */
'type_blobs_avscan_command' => '',
/* Example command using ClamAV
'type_blobs_avscan_command' => 'clamscan --stdout -i --no-summary %s',
*/

// Lock timeout, which defines how many seconds the object should be locked
'lock_timeout' => 1800,

//Pre-made chooser configurations
'clever_classes' => array
(
    'contact' => array
    (
        'class' => 'org_openpsa_contacts_person_dba',
        'component' => 'org.openpsa.contacts',
        'result_headers' => array
        (
            array
            (
                'title' => 'name',
                'name' => 'name',
            ),
            array
            (
                'title' => 'email',
                'name' => 'email',
            ),
        ),
        'searchfields' => array
        (
            'firstname',
            'lastname',
            'username',
        ),
        'orders' => array
        (
            array('lastname' => 'ASC'),
            array('firstname' => 'ASC'),
        ),
    ),
    'wikipage' => array
    (
        'class' => 'net_nemein_wiki_wikipage',
        'component' => 'net.nemein.wiki',
        'result_headers' => array
        (
            array
            (
                'title' => 'revised',
                'name' => 'revised',
            ),
            array
            (
                'title' => 'title',
                'name' => 'title',
            ),
        ),
        'searchfields' => array
        (
            'title',
        ),
        'orders' => array
        (
            array('title' => 'ASC'),
            array('metadata.published' => 'ASC'),
        ),
        'creation_default_key' => 'title',
    ),
    'article' => array
    (
        'class' => 'midcom_db_article',
        'component' => 'net.nehmer.static',
        'result_headers' => array
        (
            array
            (
                'title' => 'title',
                'name' => 'title',
            ),
        ),
        'searchfields' => array
        (
            'title'
        ),
        'orders' => array
        (
            array('title' => 'ASC'),
            array('metadata.published' => 'ASC'),
        ),
        'id_field' => 'guid',
    ),
    'topic' => array
    (
        'class' => 'midcom_db_topic',
        'component' => 'midcom.admin.folder',
        'result_headers' => array
        (
            array
            (
                'title' => 'extra',
                'name' => 'extra',
            ),
            array
            (
                'title' => 'component',
                'name' => 'component',
            ),
        ),
        'searchfields' => array
        (
            'extra',
            'name',
            'component',
        ),
        'orders' => array
        (
            array('extra' => 'ASC'),
            array('metadata.published' => 'ASC'),
        ),
        'generate_path_for' => 'extra',
    ),
    'group' => array
    (
        'class' => 'midcom_db_group',
        'component' => 'midgard.admin.asgard',
        'result_headers' => array
        (
            array
            (
                'title' => 'name',
                'name' => 'name',
            ),
            array
            (
                'title' => 'official',
                'name' => 'official',
            ),
        ),
        'searchfields' => array
        (
            'name',
            'official',
        ),
        'orders' => array
        (
            array('extra' => 'ASC'),
            array('metadata.published' => 'ASC'),
        ),
        'id_field' => 'id',
        'generate_path_for' => 'name',
    ),
    'event' => array
    (
        'class' => 'net_nemein_calendar_event',
        'component' => 'net.nemein.calendar',
        'result_headers' => array
        (
            array
            (
                'title' => 'start',
                'name' => 'start',
            ),
            array
            (
                'title' => 'end',
                'name' => 'end',
            ),
            array
            (
                'title' => 'title',
                'name' => 'title',
            ),
            array
            (
                'title' => 'location',
                'name' => 'location',
            ),
        ),
        'searchfields' => array
        (
            'title',
            'location',
        ),
        'orders' => array
        (
            array('title' => 'ASC'),
            array('start' => 'ASC'),
            array('metadata.published' => 'ASC'),
        ),
        'creation_default_key' => 'title',
    ),
    'task' => array
    (
        'class'       => 'org_openpsa_projects_task_dba',
        'component'   => 'org.openpsa.projects',
        'titlefield'  => 'title',
        'id_field'     => 'id',
        'result_headers' => array
        (
            array
            (
                'title' => 'title',
                'name' => 'title',
            ),
        ),
        'searchfields'  => array
        (
            'title',
            'project.title'
        ),
        'orders'        => array
        (
            array('title'    => 'ASC')
        ),
        'categorize_by_parent_label' => true
    ),
    'invoice' => array
    (
        'class' => 'org_openpsa_invoices_invoice_dba',
        'component' => 'org.openpsa.invoices',
        'titlefield' => 'number',
        'id_field' => 'id',

        'result_headers' => array
        (
            array
            (
                'title' => 'number',
                'name' => 'number',
            ),
        ),
        'get_label_for' => 'number',
        'searchfields' => array
        (
            'number',
            'invoiceNumber',
            'description',
        ),
        'orders' => array
        (
            array('number' => 'ASC'),
        ),
    ),
),