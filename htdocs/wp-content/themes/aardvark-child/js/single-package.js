jQuery(document).ready(function () {

    jQuery("#lightgallery").lightGallery({
        thumbnail: true,
        currentPagerPosition: 'left',
        exThumbImage: "data-exthumbimage"
    });

    jQuery('.pkg-endorse').click(function () {
        let post_id = jQuery(this).data('postid');
        if(jQuery(this).hasClass("pkg-login")){
            document.location.href = document.location.href + "/#login/";
        } else {
            let count = jQuery(".pkg-endorse-count");
            count.text(parseInt(count.text(), 10) + 1);
            jQuery('.pkg-endorse').attr("disabled", "disabled").text("Endorsed!");
            jQuery.ajax({
                url: DATA.ajaxUrl,
                type: "POST",
                data: {
                    'action': 'endorse',
                    'post_id': post_id,
                    'nonce': DATA.nonce
                }
            });
        }
    });

    if (document.location.hash == "#download-forge") {
        window.forge = new Forge();
        jQuery(".popup-tab").removeClass("tab-active");
        jQuery("#tab-download-forge").addClass("tab-active");
        jQuery(".popup-content").removeClass("popup-content-active");
        jQuery("#content-download-forge").addClass("popup-content-active");
        jQuery("#pkg-download-modal").modal();
    }

    jQuery('.pkg-download').click(function () {
        window.forge = new Forge();
        jQuery(".popup-tab").removeClass("tab-active");
        jQuery("#tab-download-classic").addClass("tab-active");
        jQuery(".popup-content").removeClass("popup-content-active");
        jQuery("#content-download-classic").addClass("popup-content-active");
        jQuery("#pkg-download-modal").modal();
    });

    jQuery('.manifest-copy').click(function () {
        let copyText = jQuery('.manifest-url')[0];
        copyText.select();
        document.execCommand("copy");
        let icon = jQuery(this).find(".fas").addClass("fa-check").removeClass("fa-copy");
        setTimeout(function (icon) {
            icon.removeClass("fa-check").addClass("fa-copy");
        }, 2000, icon);
    });

    jQuery(".popup-tab").click(function () {
        if (jQuery(this).hasClass("tab-active"))
            return;
        let contentID = jQuery(this)[0].id;
        contentID = contentID.replace('tab-', 'content-');
        jQuery(".popup-content-active").removeClass("popup-content-active");
        jQuery("#" + contentID).addClass("popup-content-active");
        jQuery(".popup-tab").removeClass("tab-active");
        jQuery(this).addClass("tab-active");
    });

    jQuery("#forge-oauth-login").click(function () {
        let url = encodeURIComponent(`https://www.foundryvtt-hub.com/package/${DATA.singlePackage.package.name}/#download-forge`);
        document.location.href = `https://www.foundryvtt-hub.com/api/forge/oauth_callback.php?hub_redirect=${url}`;
    });

    jQuery("#forge-download").click(function () {
        let icon = jQuery(this).find(".fas").removeClass("fa-cloud-download-alt").addClass("fa-spinner fa-spin");
        jQuery(this).find("span").text("Installing...");
        
        forge.installPackage().then((success)=>{
            if(success){
                icon.removeClass("fa-spinner fa-spin").addClass("fa-check-circle");
                jQuery(this).attr("disabled","disabled").find("span").text("Successfully installed!");
            }
            else{
                icon.removeClass("fa-spinner fa-spin").addClass("fa-exclamation-triangle");
                jQuery(this).find("span").text("Error! Please see the message below.");
            }
        });
    });

    jQuery('#tabs li a:not(:first)').addClass('inactive');
    jQuery('.tab_container').hide();
    jQuery('.tab_container:first').show();
    jQuery('#tabs li a').click(function(){
        var t = jQuery(this).attr('href');
        jQuery('#tabs li a').addClass('inactive');        
        jQuery(this).removeClass('inactive');
        jQuery('.tab_container').hide();
        jQuery(t).fadeIn('slow');
        return false;
    });

    jQuery("#wpd-bubble-wrapper").click(function(){
        jQuery("#tab_header_comments").trigger("click");
    });

    if(jQuery(this).hasClass('inactive')){ //this is the start of our condition 
        jQuery('#tabs li a').addClass('inactive');         
        jQuery(this).removeClass('inactive');
        jQuery('.tab_container').hide();
        jQuery(t).fadeIn('slow');    
    }

    jQuery('.lazyload_markdown').click(function(){
        let ID = jQuery(this).attr("href");
        if(!jQuery(ID).data("loaded")){
            jQuery(ID).html('<p style="text-align:center"><img src="/wp-includes/images/wpspin.gif" /></p>');
            jQuery.ajax({
                url: DATA.ajaxUrl,
                type: "POST",
                data: {
                    'action': 'load_markdown',
                    'url': jQuery(ID).data("url")
                }
            }).done(function (response) {
                jQuery(ID).html(response);
                jQuery(ID).data("loaded",true);
            });
        }
    });
});