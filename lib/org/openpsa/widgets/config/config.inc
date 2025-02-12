//the name of page used for jquery ui-tab, the style is passed in templates/OpenPsa2
'ui_page' => 'uitab',

/* search box */
'search_providers' => array
(
    'midcom.helper.search' => 'result/',
    'org.openpsa.contacts' => array
    (
        'route' => 'search/',
        'autocomplete' => true
    ),
    'org.openpsa.documents' => 'search/',
    'org.openpsa.invoices' => 'goto/',
),

/* contact widget */
'jabber_enable_presence' => true,
'jabber_edgar_url'       => '',
'gravatar_enable'        => false,
'gravatar_size'          => 40,
'skype_enable_presence'  => true,
/* This needs a pbx that can you can send links to. For example speakroute http://www.speakroute.net */
'click_to_dial'		=> false,
'click_to_dial_url'	=> 'https://yourdialurl.com/dodial.php?to=',
