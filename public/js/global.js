var baseUrl = "/easy"; /* /easy or empty string */

var displayNone = {
    display: 'none'
};

var displayDefault = {
    display: ''
};

var status2Text = {
    "need-clarification": "Clarification needed",
    submitted: "Awaiting approval",
    approved: "Approved but not taken",
    rejected: "Rejected",
    taking: "In progress",
    taken: "Taken"
};

var takingAs2Text = {
    elective: "Elective",
    core: "Core",
    "free-elective": "Free elective",
    "application-elective": "Restricted application elective",
    "place-out": "Place-out",
    'prerequisite': 'Prerequisite'
};

var grade2Text = {
    ap: 'A+',
    a: 'A',
    am: 'A-',
    bp: 'B+',
    b: 'B',
    bm: 'B-',
    cp: 'C+',
    c: 'C',
    cm: 'C-',
    dp: 'D+',
    d: 'D',
    r: 'R',
    s: 'S',
    p: 'P',
    n: 'N',
    w: 'W',
    i: 'I',
    na: 'N/A'
};

function isBAbove(grade) {
    return grade == 'b' || grade == 'bp' || grade == 'am' || grade == 'a' || grade == 'ap';
}

function isCAbove(grade) {
    return isBAbove(grade) || grade == 'c' || grade == 'cp' || grade == 'bm';
}

function replaceAll(find, replace, str) {
    return str.replace(new RegExp(find, 'g'), replace);
}

function getColoredStatusText(status) {
    var statusText = status2Text[status];
    switch (status) {
        case "need-clarification":
        return "<span class='text-warning'>" + statusText + "</span>";
        break;

        case "submitted":
        return "<span class='text-danger'>" + statusText + "</span>";
        break;

        case "approved":
        return "<span class='text-success'>" + statusText + "</span>";
        break;

        default:
        return "<span>" + statusText + "</span>";
    }
}

$(function () {
    if ($('#new-user-password-notice').attr('id') != undefined) {
        $('#new-user-password-notice').popover({
            html: true,
            content: "<h5>For new users, use the temporary<br />password sent to your Andrew<br />mail address to log in and create an<br />EASy-specific password.</h5><h5>If you have lost your password,<br />use Reset to send the email again.</h5>",
            trigger: 'hover'
        });
    }

});

/**
 * Given a human readable grade text (e.g. A+),
 * find the database representation (e.g. ap) by
 * searching for the key with the given value
 * in a key-value pair variable obj.
 */
function getKey(obj, v) {
    for (var key in obj) {
        if(obj[key] == v){
            return key;
        }
    }
    return null;
}

/**
 * Generate li element with a link as its only child.
 * (<li><a href='#'>[string]</a></li>)
 */
function generateDropdownItem(text) {
    return "<li><a href='#'>" + text + "</a></li>";
}