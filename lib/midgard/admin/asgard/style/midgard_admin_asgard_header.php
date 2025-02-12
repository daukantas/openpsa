<?php
// Check the user preference and configuration
if (   midgard_admin_asgard_plugin::get_preference('escape_frameset')
    || (   midgard_admin_asgard_plugin::get_preference('escape_frameset') !== '0'
        && $data['config']->get('escape_frameset')))
{
    midcom::get()->head->add_jsonload('if(top.frames.length != 0 && top.location.href != this.location.href){top.location.href = this.location.href}');
}

$pref_found = false;

if (($width = midgard_admin_asgard_plugin::get_preference('offset')))
{
    $navigation_width = $width - 31;
    $content_offset = $width + 1;
    $pref_found = true;
}

// JavasScript libraries required by Asgard
midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/core.min.js');
midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/widget.min.js');
midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/mouse.min.js');
midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/draggable.min.js');
midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midgard.admin.asgard/ui.js');
midcom::get()->head->add_jscript("var MIDGARD_ROOT = '" . midcom_connection::get_url('self') . "';");
?>
<!DOCTYPE html>
<html lang="<?php echo midcom::get()->i18n->get_current_language(); ?>">
    <head>
    <meta charset="UTF-8">
    <title><?php echo midcom_core_context::get()->get_key(MIDCOM_CONTEXT_PAGETITLE); ?> (<?php echo $data['l10n']->get('asgard for'); ?> <(title)>)</title>
        <link rel="stylesheet" type="text/css" href="<?php echo MIDCOM_STATIC_URL; ?>/midgard.admin.asgard/screen.css" media="screen,projector" />
        <link rel="shortcut icon" href="<?php echo MIDCOM_STATIC_URL; ?>/stock-icons/logos/favicon.ico" />
        <?php
        midcom::get()->head->print_head_elements();
        if ($pref_found)
        {?>
              <style type="text/css">
                #container #navigation
                {
                 width: &(navigation_width);px;
                }

                #container #content
                {
                  margin-left: &(content_offset);px;
                }
            </style>
        <?php } ?>
    </head>
    <body class="asgard"<?php midcom::get()->head->print_jsonload(); ?>>
        <div id="container-wrapper">
            <div id="container">
