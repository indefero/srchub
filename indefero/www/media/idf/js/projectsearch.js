$(document).ready(function() {


    $("#prjname").keydown(function(ev) {
        var link = $('.projectitem :visible').first().attr("href");
        if(ev.which === 13 && link != undefined) {
            window.location.replace(link);
        }
    });


    $('#prjname').keyup(function(){
        var valThis = $(this).val().toLowerCase();
        if(valThis == ""){
            $('.prjlistclass > li').show();
        } else {
            $('.prjlistclass > li').each(function(){
                if ($(this).attr('id') && $(this).attr('id') == "searchkeep")
                    return true;
                var text = $(this).text().toLowerCase();
                (text.indexOf(valThis) >= 0) ? $(this).show() : $(this).hide();
            });
        };
    });


    $('#project-list').bind('mouseenter', function(ev) {
        $(this).find('ul').show();
        $("#prjname").focus();
    }).bind('mouseleave', function(ev) {
        $(this).find('ul').hide();
    });
});