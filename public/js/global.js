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
    approved: "Approved",
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

var gradesOrdering = {
    ap: 0,
    a: 1,
    am: 2,
    bp: 3,
    b: 4,
    bm: 5,
    cp: 6,
    c: 7,
    cm: 8,
    dp: 9,
    d: 10,
};

/* For BHCI and undergrad minor, there are only letter grades */
var letterGrades = ['A', 'B', 'C', 'D', 'R', 'S', 'P', 'N', 'W', 'I', 'N/A'];

/**
 * Is the given grade >= grade requirement
 * E.g. doesGradeSatisfyReq('ap', 'a')
 * If a pass-fail grade given, return true if 'p'
 */
function doesGradeSatisfyReq(grade, req) {
    if (grade == 'p') return true;
    return gradesOrdering[grade] <= gradesOrdering[req];
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

/* Extend jQuery to support setting element to visible or gone using CSS */
jQuery.fn.extend({
    setVisible: function () {
        return this.each(function () {
            $(this).css(displayDefault);
        });
    },
    setGone: function () {
        return this.each(function () {
            $(this).css(displayNone);
        });
    }
});

function getSemesterFromMonth(month) {
    if (month >= 1 && month < 5) {
        return "Spring";
    } else if (month >= 5 && month < 8) {
        return "Summer";
    } else {
        return "Fall";
    }
}

angular.module('timeFilters', []).filter('formattime', function() {
    /* Assume time string is YYYY-MM-dd HH:mm:ss */
    return function(input) {
        return moment(input, "YYYY-MM-DD HH:mm:ss").format("ddd, MMM Do YYYY, h:mm:ss a");
    };
});

/* Initialize AngularJS module */
var app = angular.module('hcii-easy', [ 'timeFilters' ])
    /* Resolve $http.post not sending data issue */
    .config(function ($httpProvider) {
        $httpProvider.defaults.transformRequest = function (data) {
            if (data === undefined) {
                return data;
            }
            return $.param(data);
        };
        $httpProvider.defaults.headers.post['Content-Type'] =
            'application/x-www-form-urlencoded; charset=UTF-8';
    });