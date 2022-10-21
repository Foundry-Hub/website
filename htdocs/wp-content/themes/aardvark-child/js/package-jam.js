jQuery(document).ready(function () {
    //Change tab on click
    jQuery(".jam-category-title").click(function () {
        let category = jQuery(this).data("category");
        jQuery(".jam-category-title").removeClass("jam-category-active");
        jQuery(this).addClass("jam-category-active");
        jQuery(".jam-tab").hide();
        jQuery(".jam-tab[data-category='" + category + "']").show();
    });

    //Vote for a package
    jQuery(".package-vote-button").click(function () {

        if(!DATA.is_logged_in){
            jQuery(".bp-login-nav a").click();
            return;
        }

        let package = jQuery(this).data("name");
        let category = jQuery(this).parents(".jam-tab").data("category");
        let that = this;

        jQuery.ajax({
            url: DATA.restUrl + "package-jam/v1/vote",
            method: "POST",
            beforeSend: function (xhr) {
                xhr.setRequestHeader("X-WP-Nonce", DATA.restNonce);
            },
            data: {
                package: package,
                category: category
            },
            success: function (response) {
                if(response){
                    jQuery(that).find("span").html("Vote submitted");
                    jQuery(that).prop("disabled", true);
                    jQuery(that).find("i").removeClass("jam-can-vote").addClass("fa-check");
                    let votes_left = jQuery(".jam-tab[data-category='" + category + "'] .jam-category-votes-left-count");
                    votes_left.html(parseInt(votes_left.html()) - 1);

                    //If there's not more vote left, disable all buttons
                    if(parseInt(votes_left.html()) === 0){
                        jQuery(".jam-tab[data-category='" + category + "'] .package-vote-button").prop("disabled", true);
                    }
                } else {
                    console.log(response);
                }
            }
        });
    });

    //Open package page in a new tab
    jQuery(".package-row-left").click(function () {
        let name= jQuery(this).data("name");
        let url = "/package/" + name;
        window.open(url, '_blank');
    });
});