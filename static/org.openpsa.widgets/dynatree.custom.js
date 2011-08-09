var org_openpsa_tree =
{
    setup: function(identifier, prefix)
    {
        $('#' + identifier)
            .css('overflow', 'auto')
            .dynatree(
            {
                minExpandLevel: 1,
                persist: true,
                cookie: {path: prefix},
                cookieId: identifier,
                clickFolderMode: 2,
                autoCollapse: false,
    
                onActivate: function(dtnode) {
                    if (typeof dtnode.data.url !== 'undefined')
                    {
                        window.location.href = dtnode.data.url;
                    }
                },
                onPostInit: function(isReloading, isError)
                {
                    org_openpsa_tree.crop_height($('#' + identifier));
                },
                onExpand: function(flag, dtnode)
                {
                    if (flag === true)
                    {
                        org_openpsa_tree.crop_height($('#' + identifier));
                    }
                }
            })
            .find('.dynatree-container').css('overflow', 'visible');
        $(window).bind('resize', function()
        {
            org_openpsa_tree.crop_height($('#' + identifier));
        });
    },
    crop_height: function(tree)
    {
        var content_height = 0,
        tree_content_height = tree.find('.dynatree-container').outerHeight(true),
        container_height = $('#content-text').height();
        
        tree.closest('#content-text > *').children(':visible').each(function()
        {
            content_height += $(this).outerHeight(true);
        });
        
        var available_height = container_height - (content_height - tree_content_height);

        if (   available_height > tree_content_height
            && tree.height() >= tree_content_height)
        {
            console.log('X');
            return;
        }
        
        tree.height(container_height - (content_height - tree.height()));
    }
}