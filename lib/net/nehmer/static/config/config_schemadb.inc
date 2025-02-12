'config' => array
(
    'description' => 'Default Configuration Schema',
    'fields' => array
    (

        'symlink_topic' => array
        (
            'title' => 'symlink_topic',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'symlink_topic',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'require_corresponding_option' => false,
                'options' => array(),
                'allow_other' => true,
                'allow_multiple' => false,
            ),
            'widget' => 'autocomplete',
            'widget_config' => array
            (
                'class'       => 'midcom_db_topic',
                'component'   => 'net.nehmer.static',
                'titlefield'  => 'extra',
                'idfield'     => 'guid',
                'searchfields' => array
                (
                    'extra',
                    'name',
                ),
                'constraints' => array
                (
                    array
                    (
                        'field' => 'extra',
                        'op' => '<>',
                        'value' => '',
                    ),
                    array
                    (
                        'field' => 'component',
                        'op' => '=',
                        'value' => 'net.nehmer.static',
                    ),
                ),
                'result_headers' => array
                (
                    array
                    (
                        'name' => 'extra',
                        'title' => midcom::get()->i18n->get_string('title', 'midcom'),
                    ),
                    array
                    (
                        'name' => 'name',
                        'title' => midcom::get()->i18n->get_string('name', 'midcom'),
                    ),
                ),
                'categorize_by_parent_label' => true,
                'orders' => array
                (
                    array
                    (
                        'extra' => 'ASC',
                    ),
                    array
                    (
                        'name' => 'ASC',
                    ),
                ),
            ),
            'start_fieldset' => array
            (
                'title' => 'display settings'
            ),
            'hidden' => midcom::get()->config->get('symlinks'),
        ),

        'sort_order' => array
        (
            'title' => 'sort_order',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'sort_order',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    'score' => 'score',
                    'created' => 'created',
                    'reverse created' => 'reverse created',
                    'name' => 'name',
                    'reverse name' => 'reverse name',
                    'reverse score' => 'reverse score',
                    'revised' => 'revised',
                    'reverse revised' => 'reverse revised',
                    'calendar' => 'calendar',
                    'reverse calendar' => 'reverse calendar'
                ),
            ),
            'widget' => 'select',
        ),

        'enable_ajax_editing' => array
        (
            'title' => 'enable_ajax_editing',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'enable_ajax_editing',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
            'hidden' => !midcom::get()->config->get('enable_ajax_editing'),
        ),

        'hide_navigation' => array
        (
            'title' => 'hide_navigation',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'hide_navigation',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
            'end_fieldset' => '',
        ),
        'autoindex' => array
        (
            'title' => 'autoindex',
            'helptext' => 'autoindex-notes',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'autoindex',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
            'start_fieldset' => array
            (
                'title' => 'autoindex settings'
            ),

        ),

        'show_blobs_in_autoindex' => array
        (
            'title' => 'show_blobs_in_autoindex',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'show_blobs_in_autoindex',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
        ),

        'indexinnav' => array
        (
            'title' => 'indexinnav',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'indexinnav',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
            'end_fieldset' => '',
        ),

        'schemadb' => array
        (
            'title' => 'schemadb',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'schemadb',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => midcom_baseclasses_components_configuration::get('net.nehmer.static', 'config')->get('schemadbs'),
            ),
            'widget' => 'select',
            'start_fieldset' => array
            (
                'title' => 'schema settings'
            ),
        ),
        'auto_approve' => array
        (
            'title' => 'auto_approve',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'auto_approve',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
        ),
        'simple_name_handling' => array
        (
            'title' => 'simple_name_handling',
            'storage' => array
            (
                'location' => 'configuration',
                'domain' => 'net.nehmer.static',
                'name' => 'simple_name_handling',
            ),
            'type' => 'select',
            'type_config' => array
            (
                'options' => array
                (
                    '' => 'default setting',
                    '1' => 'yes',
                    '0' => 'no',
                ),
            ),
            'widget' => 'select',
            'end_fieldset' => '',
        ),
    ),
),
