$(document).ready(function() {
    $(":header", "#wiki-content").map(function(index) {
        var $header = $(this);
        var $toc = $('#wiki-toc-content');
        $header.attr('id', 'wikititle_' + index);
        $('<a />').attr('href', '#' + $header.attr('id'))
            .text($header.text())
            .addClass("wiki-" + $header[0].tagName.toLowerCase())
            .appendTo($toc);
    });
    if ($('#wiki-toc-content *').size() < 2)
        $('#wiki-toc').hide();
});

