$(function () {

    var reqsPopOver = function (elemId, prompt) {
        $(elemId).popover({
            content: prompt,
            trigger: 'hover',
            placement: 'top'
        });
    };

    var setBarCss = function (elemId, val, total) {
        $(elemId).css({
            width: val * 100 / total + "%"
        });
    };

    /* Cores and electives progress bar */
    var coresTaken = parseInt($('#cores-taken').text());
    var coresTotal = parseInt($('#cores-total').text());
    var coresTaking = parseInt($('#cores-taking').text());
    var coresLeft = coresTotal - coresTaking - coresTaken;

    waitForAnimationComplete('#cores-taken-bar', function () {
        setBarCss('#cores-taking-bar', coresTaking, coresTotal);
    });
    setBarCss('#cores-taken-bar', coresTaken, coresTotal);
    if (coresLeft > 0) {
        setBarCss('#cores-left-bar', coresLeft, coresTotal);
        $('#cores-left-bar').html(coresLeft + ' remaining');
    }

    reqsPopOver('#cores-taken-bar', coresTaken + " core requirement(s) satisfied");
    reqsPopOver('#cores-taking-bar', coresTaking + ' core course(s) in progress');

    var electivesTaken = parseInt($('#electives-taken').text());
    var electivesTotal = parseInt($('#electives-total').text());
    var electivesTaking = parseInt($('#electives-taking').text());
    var electivesLeft = electivesTotal - electivesTaking - electivesTaken;

    if (electivesTotal > 0) {
        waitForAnimationComplete('#electives-taken-bar', function () {
            setBarCss('#electives-taking-bar', electivesTaking, electivesTotal);
        });
        setBarCss('#electives-taken-bar', electivesTaken, electivesTotal);
        if (electivesLeft > 0) {
            setBarCss('#electives-left-bar', electivesLeft, electivesTotal);
            $('#electives-left-bar').html(electivesLeft + ' remaining');
        }

        reqsPopOver('#electives-taken-bar', electivesTaken + ' elective(s) taken');
        reqsPopOver('#electives-taking-bar', electivesTaking + ' elective(s) in progress');
    }

    if ($('#placeouts-taken').length > 0) {
        var placeOutsTaken = parseInt($('#placeouts-taken').text());
        var placeOutsTotal = parseInt($('#placeouts-total').text());
        var placeOutsLeft = placeOutsTotal - placeOutsTaken;
        setBarCss('#placeouts-taken-bar', placeOutsTaken, placeOutsTotal);
        if (placeOutsLeft > 0) {
            setBarCss('#placeouts-left-bar', placeOutsLeft, placeOutsTotal);
            $('#placeouts-left-bar').html(placeOutsLeft + ' remaining');
        }

        reqsPopOver('#placeouts-taken-bar', placeOutsTaken + ' place-out course(s) taken');
    } else {
        var prerequisitesTaken = parseInt($('#prerequisites-taken').text());
        var prerequisitesTotal = parseInt($('#prerequisites-total').text());
        var prerequisitesTaking = parseInt($('#prerequisites-taking').text());
        var prerequisitesLeft = prerequisitesTotal - prerequisitesTaken - prerequisitesTaking;
        waitForAnimationComplete('#prerequisites-taken-bar', function () {
            setBarCss('#prerequisites-taking-bar', prerequisitesTaking, prerequisitesTotal);
        });
        setBarCss('#prerequisites-taken-bar', prerequisitesTaken, prerequisitesTotal);
        if (prerequisitesLeft > 0) {
            setBarCss('#prerequisites-left-bar', prerequisitesLeft, prerequisitesTotal);
            $('#prerequisites-left-bar').html(prerequisitesLeft + ' remaining');
        }

        reqsPopOver('#prerequisites-taking-bar', prerequisitesTaking + ' prerequisite course(s) in progress');
        reqsPopOver('#prerequisites-taken-bar', prerequisitesTaken + ' prerequisite requirement(s) satisfied');
    }    

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