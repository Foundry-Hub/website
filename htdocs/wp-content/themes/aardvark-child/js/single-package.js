$(document).ready(function () {

    $("#lightgallery").lightGallery({
        thumbnail: true,
        currentPagerPosition: 'left',
        exThumbImage: "data-exthumbimage"
    });

    $('.pkg-endorse').click(function () {
        let post_id = $(this).data('postid');
        if($(this).hasClass("pkg-login")){
            document.location.href = document.location.href + "/#login/";
        } else {
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    'action': 'endorse',
                    'post_id': post_id
                }
            }).done(function (response) {
                let count = $(".pkg-endorse-count");
                count.text(parseInt(count.text(), 10) + 1);
                $('.pkg-endorse').attr("disabled", "disabled").text("Endorsed!");
            });
        }
    });

    if (document.location.hash == "#download-forge") {
        window.forge = new Forge(forgeToken);
        $(".popup-tab").removeClass("tab-active");
        $("#tab-download-forge").addClass("tab-active");
        $(".popup-content").removeClass("popup-content-active");
        $("#content-download-forge").addClass("popup-content-active");
        $("#pkg-download-modal").modal();
    }

    $('.pkg-download').click(function () {
        window.forge = new Forge(forgeToken);
        $(".popup-tab").removeClass("tab-active");
        $("#tab-download-classic").addClass("tab-active");
        $(".popup-content").removeClass("popup-content-active");
        $("#content-download-classic").addClass("popup-content-active");
        $("#pkg-download-modal").modal();
    });

    $('.manifest-copy').click(function () {
        let copyText = $('.manifest-url')[0];
        copyText.select();
        document.execCommand("copy");
        let icon = $(this).find(".fas").addClass("fa-check").removeClass("fa-copy");
        setTimeout(function (icon) {
            icon.removeClass("fa-check").addClass("fa-copy");
        }, 2000, icon);
    });

    $(".popup-tab").click(function () {
        if ($(this).hasClass("tab-active"))
            return;
        let contentID = $(this)[0].id;
        contentID = contentID.replace('tab-', 'content-');
        $(".popup-content-active").removeClass("popup-content-active");
        $("#" + contentID).addClass("popup-content-active");
        $(".popup-tab").removeClass("tab-active");
        $(this).addClass("tab-active");
    });

    $("#forge-oauth-login").click(function () {
        let url = encodeURIComponent(`https://www.foundryvtt-hub.com/package/${singlePackage.package.name}/#download-forge`);
        document.location.href = `https://www.foundryvtt-hub.com/api/forge/oauth_callback.php?hub_redirect=${url}`;
    });

    $("#forge-download").click(function () {
        let icon = $(this).find(".fas").removeClass("fa-cloud-download-alt").addClass("fa-spinner fa-spin");
        $(this).find("span").text("Installing...");
        
        forge.installPackage().then((success)=>{
            if(success){
                icon.removeClass("fa-spinner fa-spin").addClass("fa-check-circle");
                $(this).attr("disabled","disabled").find("span").text("Successfully installed!");
            }
            else{
                icon.removeClass("fa-spinner fa-spin").addClass("fa-exclamation-triangle");
                $(this).find("span").text("Error! Please see the message below.");
            }
        });
    });
});