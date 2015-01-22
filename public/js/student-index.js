var coresTaken = 0;
var coresTotal = 0;
var coresTaking = 0;
var coresLeft = 0;

var coresTakenList = [];
var coresTakingList = [];
var coresLeftList = [];

var prerequisitesTaken = 0;
var prerequisitesTotal = 0;
var prerequisitesTaking = 0;
var prerequisitesLeft = 0;

var prerequisitesTakenList = [];
var prerequisitesTakingList = [];
var prerequisitesLeftList = [];

var courses = null;
var forcedValues = [];

var courseReg = /\d{2}-\d{3}/g;

function getForcedValue(reqName, type) {
    var ret = null;
    $.each(forcedValues, function (i, v) {
        if (v.key == reqName && v['type'] == type) {
            ret = v['value'];
            return false;
        }
    });
    return ret;
}

function loadCoursesList() {
    /* Load all courses of this student */
    if (courses == null)
        courses = jQuery.parseJSON($('#courses-list').text());

    var forcedText = $('#forced-values').text().trim();
    if (forcedText.length != 0)
        forcedValues = jQuery.parseJSON(forcedText);
}

function processCourseNumbers(numbers) {
    var reqs = '(' + numbers + ')';
    reqs = replaceAll(',', ') || (', reqs);
    reqs = replaceAll('&', ' && ', reqs);
    return reqs;
}

function generateProcessedCourse(rawCourse) {
    var processedNumbers = processCourseNumbers(rawCourse['course_numbers']);
    return {
        name: rawCourse['course_name'],
        numbersForSatisfied: processedNumbers,
        numbersForTaking: processedNumbers,
        satisfied: false,
        taking: false
    };
}

/* TODO Two functions below are extremely repetitive -- try to simplify */
function computeCoresTakenTaking() {
    loadCoursesList();
    var cores = [];
    /* Core requirements */
    var coreReqs = jQuery.parseJSON($('#cores-reqs').text());
    var coreGradeReq = $('#cores-grade-req').text();
    if (coreGradeReq.length == 0)
        coreGradeReq = 'd';

    $.each(coreReqs, function (i, val) {
        cores.push(generateProcessedCourse(val));
    });

    var validTakenList = [];
    var validTakingList = [];

    $.each(courses, function (courseI, courseVal) {
        var courseType = courseVal['taking_as'],
            courseStatus = courseVal['status'],
            courseNumber = courseVal['course_number'].trim(),
            courseGrade = courseVal['grade'];

        if (!courseReg.test(courseNumber)) {
            return;
        }
        courseReg.lastIndex = 0;

        if (courseStatus == 'taken') {
            if (doesGradeSatisfyReq(courseGrade, coreGradeReq) || courseGrade == 'na')
                validTakenList.push({ number: courseNumber, used: false });
        } else if (courseStatus == 'taking') {
            validTakingList.push({ number: courseNumber, used: false });
        }
    });

    $.each(cores, function (i) {
        var forcedVal = getForcedValue(cores[i]['name'], 'core');
        if (forcedVal == 'satisfied') {
            cores[i]['satisfied'] = true;
            return;
        } else if (forcedVal == 'not-satisfied') {
            cores[i]['satisfied'] = false;
            return;
        }

        $.each(validTakenList, function (takenI, takenE) {
            var numbers = cores[i]['numbersForSatisfied'];
            if (!takenE['used'] && numbers.indexOf(takenE['number']) >= 0) {
                validTakenList[takenI]['used'] = true;
                cores[i]['numbersForSatisfied'] =
                    replaceAll(takenE['number'], ' true ', numbers);
            }
        });

        var satExp = cores[i]['numbersForSatisfied'].replace(courseReg, " false ");
        cores[i]['satisfied'] = eval(satExp);

        console.log(cores[i]);

        $.each(validTakingList, function (takingI, takingE) {
            var numbers = cores[i]['numbersForTaking'];
            /* Only search if it's currently false */
            if (!cores[i]['taking'] &&
                /* Don't try to make the requirement "Taking" with this course
                 * if it is already marked as 'Taken'; use this course to
                 * mark others as "Taking" instead
                 */
                !cores[i]['satisfied'] &&
                !takingE['used'] &&
                numbers.indexOf(takingE['number']) >= 0) {
                validTakingList[takingI]['used'] = true;
                /* If one course is taking, whole expression is "Taking" */
                if (numbers.search(takingE) != -1)
                    cores[i]['taking'] = true;
            }
        });

        if (cores[i]['satisfied'])
            cores[i]['taking'] = false;
    });

    $.each(cores, function (i, e) {
        if (e['satisfied'])
            coresTakenList.push(e['name']);
        else if (e['taking'])
            coresTakingList.push(e['name']);
        else
            coresLeftList.push(e['name']);
    });

    coresTaken = coresTakenList.length;
    coresTaking = coresTakingList.length;
}

/* TODO One course should satisfy only one course in prereqs too */
function computePrerequisitesTakenTaking() {
    loadCoursesList();
    var prerequisites = [];
    /* Prereq requirements */
    var prereqReqs = jQuery.parseJSON($('#prerequisites-reqs').text());
    var prereqGradeReq = $('#prerequisites-grade-req').text();
    if (prereqGradeReq.length == 0)
        prereqGradeReq = 'd';

    $.each(prereqReqs, function (i, val) {
        prerequisites.push(generateProcessedCourse(val));
    });

    var validTakenList = [];
    var validTakingList = [];

    $.each(courses, function (courseI, courseVal) {
        var courseType = courseVal['taking_as'],
            courseStatus = courseVal['status'],
            courseNumber = courseVal['course_number'],
            courseGrade = courseVal['grade'];

        if (!courseReg.test(courseNumber))
            return;

        courseReg.lastIndex = 0;

        if (courseStatus == 'taken') {
            if (doesGradeSatisfyReq(courseGrade, prereqGradeReq) || courseGrade == 'na')
                validTakenList.push(courseNumber);
        } else if (courseStatus == 'taking') {
            validTakingList.push(courseNumber);
        }
    });

    $.each(prerequisites, function (i) {
        var forcedVal = getForcedValue(prerequisites[i]['name'], 'prerequisite');
        if (forcedVal == 'satisfied') {
            prerequisites[i]['satisfied'] = true;
            return;
        } else if (forcedVal == 'not-satisfied') {
            prerequisites[i]['satisfied'] = false;
            return;
        }

        $.each(validTakenList, function (takenI, takenE) {
            prerequisites[i]['numbersForSatisfied'] =
                replaceAll(takenE, ' true ', prerequisites[i]['numbersForSatisfied']);
        });
        $.each(validTakingList, function (takingI, takingE) {
            /* Only search if it's currently false */
            if (prerequisites[i]['taking'] == false) {
                /* If one course is taking, whole expression is "Taking" */
                if (prerequisites[i]['numbersForTaking'].search(takingE) != -1)
                    prerequisites[i]['taking'] = true;
            }
        });
        var satExp = prerequisites[i]['numbersForSatisfied'].replace(courseReg, " false ");
        prerequisites[i]['satisfied'] = eval(satExp);

        if (prerequisites[i]['satisfied'])
            prerequisites[i]['taking'] = false;
    });

    $.each(prerequisites, function (i, e) {
        if (e['satisfied'])
            prerequisitesTakenList.push(e['name']);
        else if (e['taking'])
            prerequisitesTakingList.push(e['name']);
        else
            prerequisitesLeftList.push(e['name']);
    })

    prerequisitesTaken = prerequisitesTakenList.length;
    prerequisitesTaking = prerequisitesTakingList.length;
}

$(function () {

    var reqsPopOver = function (elemId, prompt) {
        $(elemId).popover({
            content: "<div>" + prompt + "</div>",
            trigger: 'hover',
            placement: 'top',
            html: true
        });
    };

    var setBarCss = function (elemId, val, total) {
        $(elemId).css({
            width: val * 100 / total + "%"
        });
    };

    /* Cores and electives progress bar */
    computeCoresTakenTaking();
    coresTotal = parseInt($('#cores-total').text());
    coresLeft = coresTotal - coresTaking - coresTaken;

    waitForAnimationComplete('#cores-taken-bar', function () {
        setBarCss('#cores-taking-bar', coresTaking, coresTotal);
    });
    setBarCss('#cores-taken-bar', coresTaken, coresTotal);
    if (coresLeft > 0) {
        setBarCss('#cores-left-bar', coresLeft, coresTotal);
        $('#cores-left-bar').html(coresLeft + ' remaining');
    }

    reqsPopOver('#cores-taken-bar', coresTaken + " core " +
        getPlural(coresTaken, 'requirement') + " satisfied:<ul><li>" +
        coresTakenList.join("</li><li>") + '</li></ul>');
    reqsPopOver('#cores-taking-bar', coresTaking + ' core ' +
        getPlural(coresTaking, 'course') + ' in progress:<ul><li>' +
        coresTakingList.join("</li><li>") + '</li></ul>');
    reqsPopOver('#cores-left-bar', coresLeft + ' core ' +
        getPlural(coresLeft, 'requirement') + ' left:<ul><li>' +
        coresLeftList.join("</li><li>") + '</li></ul>');

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

        reqsPopOver('#electives-taken-bar', electivesTaken + ' ' +
            getPlural(electivesTaken, 'elective') + ' taken');
        reqsPopOver('#electives-taking-bar', electivesTaking + ' ' +
            getPlural(electivesTaking, 'elective') + ' in progress');
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

        reqsPopOver('#placeouts-taken-bar', placeOutsTaken + ' place-out ' +
            getPlural(placeOutsTaken, 'course') + ' taken');
    } else if (prerequisitesTotal > 0) {
        computePrerequisitesTakenTaking();
        prerequisitesTotal = parseInt($('#prerequisites-total').text());
        prerequisitesLeft = prerequisitesTotal - prerequisitesTaken - prerequisitesTaking;
        waitForAnimationComplete('#prerequisites-taken-bar', function () {
            setBarCss('#prerequisites-taking-bar', prerequisitesTaking, prerequisitesTotal);
        });
        setBarCss('#prerequisites-taken-bar', prerequisitesTaken, prerequisitesTotal);
        if (prerequisitesLeft > 0) {
            setBarCss('#prerequisites-left-bar', prerequisitesLeft, prerequisitesTotal);
            $('#prerequisites-left-bar').html(prerequisitesLeft + ' remaining');
        }

        reqsPopOver('#prerequisites-taking-bar', prerequisitesTaking +
            ' prerequisite ' + getPlural(prerequisitesTaking, 'course') +
            ' in progress:<ul><li>' + prerequisitesTakingList.join("</li><li>") + '</li></ul>');
        reqsPopOver('#prerequisites-taken-bar', prerequisitesTaken +
            ' prerequisite ' + getPlural(prerequisitesTaken, 'requirement') +
            ' satisfied:<ul><li>' + prerequisitesTakenList.join("</li><li>") + '</li></ul>');
        reqsPopOver('#prerequisites-left-bar', prerequisitesLeft +
            ' prerequisite ' + getPlural(prerequisitesLeft, 'requirement') +
            ' left:<ul><li>' + prerequisitesLeftList.join("</li><li>") + '</li></ul>');
    }    

    $('#link-problem').popover({
        html: true,
        content: "<h5>If any of the above information is<br />incorrect, \
        you can write to the advisor<br />here and request a correction.</h5>",
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
            $('#div-student-info').append("<div class='alert alert-block alert-info'>\
                <a class='close' data-dismiss='alert' href='#'>X</a>Request sent successfully.</div>");
        }).fail(function () {
            $('#div-student-info').append("<div class='alert alert-block alert-danger'>\
                <a class='close' data-dismiss='alert' href='#'>X</a>Failed to send request. Please try again later.</div>");
        });
        
        $('#div-request-correction').setGone();
    });
});