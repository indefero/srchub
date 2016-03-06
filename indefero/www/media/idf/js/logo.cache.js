$(function () {
    var logoCache = {};
    var waiting = 0;
    function logoUpdate() {
        if (waiting == 0) {
            $(".logo").each(function () {
                var self = $(this);
                var logo = $(this).data("logo");
                if (logo == "") {
                    logo = undefined;
                }
                self.attr("src", logoCache[logo]);
            });
        } else {
            waiting -= 1;
        }
    }
    $(".logo").each(function () {
        var logoSrc = $(this).data("src");
        var logo = $(this).data("logo");
        var self = $(this);
        if (!logoSrc) {
            return;
        }

        if (logo == "") {
            logo = undefined;
        }

        if (!(logo in logoCache)) {
            logoCache[logo] = logoSrc;
        }
    });

    waiting = Object.keys(logoCache).length - 1;
    $.each(logoCache, function (index, element) {
        $.ajax({
            url: logoCache[index],
            success: function(data) {
                logoCache[index] = data;
                logoUpdate();
            },
            async: true
        });
    });
});