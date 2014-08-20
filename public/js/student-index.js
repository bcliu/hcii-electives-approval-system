$(function () {
    /* Cores and electives progress bar */
    var coresTaken = parseInt($('#cores-taken').text());
    var coresLeft = parseInt($('#cores-left').text());
    $('#cores-progress-bar .progress-bar').css({
        width: coresTaken * 100 / (coresLeft + coresTaken) + "%" /* Calculate the percentage */
    });
    
    var electivesTaken = parseInt($('#electives-taken').text());
    var electivesLeft = parseInt($('#electives-left').text());
    $('#electives-progress-bar .progress-bar').css({
        width: electivesTaken * 100 / (electivesLeft + electivesTaken) + "%"
    });

    var freeElectivesTaken = parseInt($('#free-electives-taken').text());
    var freeElectivesLeft = parseInt($('#free-electives-left').text());
    $('#free-electives-progress-bar .progress-bar').css({
        width: freeElectivesTaken * 100 / (freeElectivesLeft + freeElectivesTaken) + "%"
    });

    var applicationElectivesTaken = parseInt($('#application-electives-taken').text());
    var applicationElectivesLeft = parseInt($('#application-electives-left').text());
    $('#application-electives-progress-bar .progress-bar').css({
        width: applicationElectivesTaken * 100 / (applicationElectivesLeft + applicationElectivesTaken) + "%"
    });

    $('#link-problem').popover({
        html: true,
        content: "<h5>If any of the above information is<br />incorrect, you can write to the advisor<br />here and request a correction.</h5>",
        trigger: 'hover',
        placement: 'right'
    });
    
    /* Show the textarea for requesting correction on click */
    $('#link-problem').click(function () {
        $('#div-request-correction textarea').val("");
        $('#div-request-correction').setVisible();
    });
    
    /* Submit the request */
    $('#div-request-correction button').click(function () {
        $.post(baseUrl + "/student/correction", {
            content: $('#div-request-correction textarea').val()
        }).done(function () {
            $('#div-student-info').append("<div class='alert alert-block alert-info'><a class='close' data-dismiss='alert' href='#'>X</a>Request sent successfully.</div>");
        }).fail(function () {
            $('#div-student-info').append("<div class='alert alert-block alert-danger'><a class='close' data-dismiss='alert' href='#'>X</a>Failed to send request. Please try again later.</div>");
        });
        
        $('#div-request-correction').setGone();
    });
});