function infer(type) {
    $.get(baseUrl + "/users/get-info/andrew-id/" + $('input[name="andrew-id"]').val() + "/type/" + type, function(result) {
        var input;

        if (type == 'name') {
            input = $('input[name="name"]');
        }
        else if (type == 'major') {
            input = $('input[name="major"]');
        }

        if (result.length > 80 || result.length == 0) {
            var andrewIdInput = $('input[name="andrew-id"]');
            andrewIdInput.parent().addClass('has-error');
            setTimeout(function () {
                andrewIdInput.parent().removeClass('has-error');
                andrewIdInput.popover('hide');
            }, 2333);

            andrewIdInput.popover({
                html: true,
                placement: 'top',
                content: "Failed to look up the Andrew ID",
                trigger: 'manual'
            });
            andrewIdInput.popover('show');
            return;
        }

        input.val(result);
        input.parent().addClass('has-success');
        setTimeout(function () {
            input.parent().removeClass('has-success');
        }, 2333);
    });
}

function clearFilter() {
    $('#show-graduated').prop('checked', false);
    $('#filter-year-lower-bound').val('');
    $('#filter-year-upper-bound').val('');
    $('#show-enrolled').prop('checked', true);
    showOutstandingOnlyOption.prop('checked', false);
    showMessagesOnlyOption.prop('checked', false);
    showOutstandingAndMessagesOnly.prop('checked', false);

    loadStudents();
}

function selectStudentRow(andrewId) {
    $('tr[andrewid="' + andrewId + '"]').addClass('user-selected');
}

/**
 * Make an AJAX call to get students based on the specified filter.
 * Update the Users list on the left of the screen.
 * @param  {string} studentToShow   The student to load after AJAX call is completed
 * @param {boolean} goToCoursesTab  Whether to switch to courses tab after student is loaded
 * @param {boolean} selectStudent   Whether to select that student row after loaded
 */
function loadStudents(studentToShow, goToCoursesTab, selectStudent) {
    clearUserInfoFields();
    $('#loading-users').css({ 'display': 'block' });
    $('#no-users').setGone();
    var showGraduated = $('#show-graduated').is(":checked") ? 1 : 0;
    var showEnrolled = $('#show-enrolled').is(":checked") ? 1 : 0;
    var showOutstandingOnly = showOutstandingOnlyOption.is(":checked") ? 1 : 0;
    var showUnreadMessagesOnly = showMessagesOnlyOption.is(':checked') ? 1 : 0;
    var showOutstandingAndMessagesOnly =
        showOutstandingAndMessagesOption.is(':checked') ? 1 : 0;
    var startYear = $('#filter-year-lower-bound').val();
    var startYear = startYear.substr(startYear.length - 4);
    var endYear = $('#filter-year-upper-bound').val();
    var endYear = endYear.substr(endYear.length - 4);

    var period = "";
    if (startYear != null && endYear != null) {
        period = "/start-year/" + startYear + "/end-year/" + endYear;
    }

    var url = baseUrl + "/admin/get-students/program/" + currentProgram +
        "/include-graduated/" + showGraduated +
        "/outstanding-only/" + showOutstandingOnly +
        "/messages-only/" + showUnreadMessagesOnly +
        "/outstanding-and-messages-only/" + showOutstandingAndMessagesOnly +
        "/include-enrolled/" + showEnrolled + period;
    $.get(url, function(result) {
        try {
            var json = $.parseJSON(result);
        } catch (e) {
            alert("Server error");
            return;
        }
        $('body').data('users-info', json);

        /* Reload users table */
        var table = $('#users-table tbody');
        table.html('');

        json.sort(lastNameSorter);
        /* No need to append users now; searchStudents() will be called automatically */

        $('#loading-users').setGone();

        if (json.length == 0) {
            $('#no-users').css({ 'display': 'block' });
            adjustTableStyle();
            return;
        }

        /* Attach events handlers */
        $('#users-table tbody tr').each(function () {
            var numAwaiting = $(this).find('.badge').text();
            if (numAwaiting.length != 0) {
                $(this).popover({
                    html: true,
                    placement: 'top',
                    content: "<b>" + numAwaiting + "</b> course(s) awaiting approval",
                    trigger: 'hover',
                    container: 'body' /* Prevent being attached to tbody and affect stripes */
                });
            }
        });

        searchStudents();
        if (studentToShow != null) {
            fillInfoCoursesWithAndrewId(studentToShow);

            if (goToCoursesTab) {
                $('#courses-tab a').tab('show');
            }

            if (selectStudent) {
                selectStudentRow(studentToShow);
            }
        }
    });
}

function loadAdministrators() {
    clearUserInfoFields();
    $.get(baseUrl + "/admin/get-administrators", function(result) {
        var json = $.parseJSON(result);
        $('body').data('users-info', json);

        var table = $('#users-table tbody');
        table.html('');

        $.each(json, function (i, e) {
            table.append('<tr><td id="td-andrew-id">' + e['andrew_id'] + "</td><td>" + e['name'] + "</td></tr>");
        });

        adjustTableStyle();
        attachUserClickHandler();
    });
}

/**
 * Align th's with td's in tbody
 */
function adjustTableStyle() {
    var tbodyWidth = $('#users-table tbody').width();
    var rowWidth = $('#users-table tbody tr:first').width();

    var table = $('#users-table');
    var tbody = $('#users-table tbody');

    var sumTrHeights = 0;
    table.find('tr').each(function (e) {
        sumTrHeights += $(this).height();
    });

    var idealHeight = $('body').height() - 200;
    if (sumTrHeights < idealHeight) {
        tbody.height(sumTrHeights);
    }
    else {
        tbody.height(idealHeight);
    }

    if (tbody.find('tr').length == 0) {
        tbody.height(0);
    }
}

function attachUserClickHandler() {
    var usersTableRows = $('#users-table tbody tr');
    usersTableRows.unbind('click');
    usersTableRows.click(function () {
        /* When clicking on some user, update the inputs with data */
        $('tr.user-selected').removeClass('user-selected');
        $(this).addClass('user-selected');
        if ($(this).find('#td-andrew-id').length == 0) {
            return;
        }
        var andrewId = $(this).find('#td-andrew-id').text();
        fillInfoCoursesWithAndrewId(andrewId);
    });
}

/**
 * Helper function sort() takes to sort the students list by last name.
 */
function lastNameSorter(a, b) {
    var lastNameA = a['name'].split(" ").pop();
    var lastNameB = b['name'].split(" ").pop();
    return lastNameA.localeCompare(lastNameB);
}

var filterClearButton;
var filterInput;

function searchStudents() {
    $('#users-table tbody').html('');
    var text = filterInput.val().toLowerCase();
    var search;

    if (text.length > 0) {
        filterClearButton.setVisible();
    } else {
        filterClearButton.setGone();
    }

    var lastNameMatches = [];
    var otherMatches = [];
    $('body').data('users-info').forEach(function (e, i) {
        var lastName = e['name'].split(" ").pop();
        if (lastName.toLowerCase().indexOf(text) != -1) {
            lastNameMatches.push(i);
        }
        else if ((e['andrew_id'].toLowerCase().indexOf(text) != -1 ||
             e['name'].toLowerCase().indexOf(text) != -1)) {
            otherMatches.push(i);
            /* If it's the same one as previously spotted, don't animate again */
            /*  (typeof previousLocated == 'undefined' || previousLocated != e['andrew_id'])) {
                search = $('tr[andrewid="' + e['andrew_id'] + '"]');                                                                                                         
                $('#users-table td').css('background-color', '');             
                previousLocated = e['andrew_id'];                                                                                                                            
                search.find('td').css('background-color', '#F0AD4E');                                                                                                        
                setTimeout(function () {                                                                                                                                     
                    search.find('td').css('background-color', '');                                                
                    }, 1000);*/
        }
    });

    var i = 0;
    var numLastNameMatches = lastNameMatches.length;
    var numOtherMatches = otherMatches.length;

    var getUserRowString = function (e) {
        var enrollSeason = e.enroll_date.substring(0, 1);
        var enrollYear = e.enroll_date.substring(2);
        var graduationSeason = e['graduation_date'].substring(0, 1);
        var graduationYear = e['graduation_date'].substring(2);
        
        return '<tr andrewid="' + e['andrew_id'] +
        '"><td id="td-andrew-id">' + e['andrew_id'] +
        "</td><td>" + e['name'] +
        "</td><td class='td-enroll-date'>" + seasonToString(enrollSeason) + ' ' + enrollYear +
        "</td><td>" + seasonToString(graduationSeason) + ' ' + graduationYear +
        "</td><td class='nowrap-line'>" +
        "<span class='badge'>" + (e['number_awaiting_approval'] > 0 ? e['number_awaiting_approval'] : "") +
        "</span>" + (e['has_unread_msg'] == 1 ? "<span class='glyphicon glyphicon-envelope has_unread_msg'></span>" : "") +
        "</td></tr>";
    }

    for (; i < numLastNameMatches; i++) {
        var e = $('body').data('users-info')[lastNameMatches[i]];
        $('#users-table tbody').append(getUserRowString(e));
    }

    for (var p = 0; p < numOtherMatches; p++) {
        var e = $('body').data('users-info')[otherMatches[p]];
        $('#users-table tbody').append(getUserRowString(e));
        i++;
    }
    adjustTableStyle();
    attachUserClickHandler();
}

var currentProgram;
var showOutstandingOnlyOption;
var showMessagesOnlyOption;
var showOutstandingAndMessagesOption;

$(function () {
    $('#show-graduated, #show-enrolled').click(loadStudents);
    showOutstandingOnlyOption = $('#show-outstanding-elective-requests');
    showMessagesOnlyOption = $('#show-unread-messages');
    showOutstandingAndMessagesOption = $('#show-elective-requests-messages');

    showOutstandingOnlyOption.click(function () {
        showMessagesOnlyOption.prop('checked', false);
        showOutstandingAndMessagesOption.prop('checked', false);
        loadStudents();
    });

    showMessagesOnlyOption.click(function () {
        showOutstandingOnlyOption.prop('checked', false);
        showOutstandingAndMessagesOption.prop('checked', false);
        loadStudents();
    });

    showOutstandingAndMessagesOption.click(function () {
        showOutstandingOnlyOption.prop('checked', false);
        showMessagesOnlyOption.prop('checked', false);
        loadStudents();
    });

    adjustTableStyle();

    filterInput = $('#search-student');
    filterInput.on('input', searchStudents);

    filterClearButton = $('#search-student-clear');
    filterClearButton.setGone();
    filterClearButton.on('click', function () {
        filterInput.val('');
        searchStudents();
    });

    $('#filter-options .checkbox, #filter-options input').on('click', function (e) {
        e.stopPropagation();
    });

    currentProgram = $('input[name="type"]').val();

    $('#new-user-email-notice').popover({
        html: true,
        content: "<h5>When a new user is added, an email<br />will be sent to his/her Andrew email<br />address with a temporary password.</h5>",
        trigger: 'hover',
        placement: 'top'
    });

    $('#btn-infer-major').popover({
        html: true,
        content: "<h5>Infer primary major<br />using Andrew ID</h5>",
        trigger: 'hover',
        placement: 'top'
    });

    $('#btn-infer-name').popover({
        html: true,
        content: "<h5>Infer name<br />using Andrew ID</h5>",
        trigger: 'hover',
        placement: 'top'
    });

    /* Form submitted successfully: load students again */
    $(document).ajaxComplete(function (e) {
        if (typeof(reloadList) != 'undefined' && reloadList) {
            reloadList = false;

            if (currentProgram == 'admin') {
                setTimeout(loadAdministrators, 400);
            }
            else {
                setTimeout(loadStudents, 400);
            }
        }
    });

    /* Submit form button event */
    $('#submit-new-user').click(function () {
        var andrewId = $('input[name="andrew-id"]').val();
        
        if (andrewId.length == 0) {
            alert("Andrew ID cannot be empty");
            return;
        }
        
        /* Don't check dates if on Admin management page */
        if ($('#enroll-year').length > 0) {
            if ($('#enroll-year').val().length == 0) {
                alert('Year entered program cannot be empty');
                return;
            }
            
            if ($('#graduation-year').val().length == 0) {
                alert('Graduation cannot be empty');
                return;
            }
        }
        
        $.get(baseUrl + "/users/user/andrewid/" + andrewId + "/program/" + currentProgram, function(result) {
            if (result == '0') {
                /* If user does not exist, quietly submit form */
                reloadList = true;
                $('#user-info-form').submit();
            } else {
                /* Otherwise, ask if user wants to continue */
                var override = confirm('User "' + andrewId + '" already exists. Update the current user?');
                if (override == true) {
                    reloadList = true;
                    $('#user-info-form').submit();
                }
            }
        });
    });
    
    /* Toggle default buttons: enrolled and full time */
    $('input[value="enrolled"]').parent().button('toggle');
    $('input[name="is-full-time"][value="1"]').parent().button('toggle');
    
    $('input[name="andrew-id"]').on('change keydown paste input', function () {
        $('#delete-user').setGone();
        $('#submit-new-user').text("Add or Update");
        $('#courses-tab').setGone();
        $('#span-after-add').setVisible();
        $('#not-activated-notice').setGone();
        
        /* Clear data stored in Angular controller when Andrew Id is changed */
        $('#andrew-id-to-load-courses').val('');
        $('#andrew-id-to-load-courses').trigger('change');
    });
    
    $('#delete-user').click(function () {
        var andrewId = $('input[name="andrew-id"]').val();
        
        $.get(baseUrl + "/users/user/andrewid/" + andrewId + "/program/" + currentProgram, function(result) {
            if (result == '0') {
                alert("User '" + andrewId + "' has not been created yet");
            }
            else {
                var del = confirm("Are you sure to remove " + andrewId + "?");
                if (del == true) {
                    $.get(baseUrl + "/users/remove/andrewid/" + andrewId + "/program/" + currentProgram, function () {
                        if (currentProgram != 'admin') {
                            loadStudents();
                        } else {
                            loadAdministrators();
                        }
                    });
                }
            }
        });
    });
    
    if (currentProgram != 'admin') {
        loadStudents();
    }
    else {
        loadAdministrators();
    }

    $('#div-ordering label').click(function () {
        $('tr.user-selected').trigger('click');
    });

    /* Bind two notes textareas in two panes */
    var coursesNotes = $('#courses-pane-notes');

    $('textarea[name="notes"]').on('keydown paste input', function () {
        coursesNotes.val($(this).val());
    });

    var timeOut;
    coursesNotes.on('keydown paste input', function () {
        $('textarea[name="notes"]').val($(this).val());
        /* Make sure that only when there is no typing for .5 sec will the popover show */
        clearTimeout(timeOut);
        timeOut = setTimeout(function () {
            /* Save the notes, update .data, and show popover */
            var newNotes = coursesNotes.val();
            var data = {
                andrew_id: $('.user-selected #td-andrew-id').text(),
                program: currentProgram,
                notes: newNotes
            };
            $.post(baseUrl + "/admin/update-notes", data).done(function (ret) {
                coursesNotes.popover({ content: "Notes saved", placement: "left", trigger: "manual" });
                coursesNotes.popover('show');
                setTimeout(function () {
                    coursesNotes.popover('hide');
                }, 1000);

                var oldData = $('body').data('users-info');
                oldData.forEach(function (e, i) {
                    if (e['andrew_id'] == $('input[name="andrew-id"]').val()) {
                        /* Found the user */
                        oldData[i]['notes'] = newNotes;
                    }
                });
            }).fail(function (ret) {
                coursesNotes.popover({
                    html: true,
                    content: "<h5 style='color: red'>Failed to save notes</h5>",
                    placement: "left",
                    trigger: "manual"
                });
                coursesNotes.popover('show');
                setTimeout(function () {
                    coursesNotes.popover('hide');
                }, 1000);
            });
        }, 500);
    });

    /* Process the program requirements */
    var jsonRequirements = $.parseJSON($('#requirements-storage').text());
    $('body').data('requirements', jsonRequirements);
});

/**
 * Switch back to User info pane if not currently,
 * then clear all fields in User Info pane and hide Courses pane.
 */
function clearUserInfoFields() {
    $('#user-info-tab a').tab('show');
    $('input[name="andrew-id"]').val("");
    $('#andrew-id-to-load-courses').val('');
    $('#andrew-id-to-load-courses').trigger('change');
    $('input[name="name"]').val("");
    $('input[name="name"]').trigger('change');
    $('textarea[name="notes"]').val("");
    $('#courses-pane-notes').val('');
    $('input[name="andrew-id"]').trigger('change');
    /* Above are shared inputs between admin and students */

    if ($('#receive-from-mhci').attr('id') != undefined) {
        $('#receive-from-mhci input').prop('checked', false);
        $('#receive-from-metals input').prop('checked', false);
        $('#receive-from-bhci input').prop('checked', false);
        $('#receive-from-ugminor input').prop('checked', false);
        $('#receive-from-learning-media input').prop('checked', false);
    }
    
    if ($('input[name="major"]').attr('name') != undefined) {
        /* If major exists, then it is a student page */
        $('input[name="major"]').val("");
        
        $('#enroll-year').val("");
        $('#enroll-season').val('0');
        /* Angular listens to the input event to update model */
        $('#enroll-season').trigger('input');
        
        $('#graduation-year').val("");
        $('#graduation-season').val('0');
        $('#graduation-season').trigger('input');
        
        $('input[value="enrolled"]').parent().button('toggle');
        $('input[name="is-full-time"][value="1"]').parent().button('toggle');
    }
}

function findGradeRequirements(year, semester, program) {
    var ret = {};
    $('body').data('requirements').forEach(function (e) {
        if (e['grade_requirement'] != null && e['grade_requirement'].length > 0
            && e['year'] == year && e['semester'] == semester) {
            ret[e['type']] = e['grade_requirement'];
        }
    });
    return ret;
}

function fillInfoCoursesWithAndrewId(andrewId) {
    $('body').data('users-info').forEach(function (e) {
        if (e['andrew_id'] == andrewId) {
            /* Update the inputs using e */
            $('input[name="andrew-id"]').val(andrewId);            
            $('#delete-user').setVisible();
            $('#delete-user').text('Delete "' + andrewId + '"');
            $('input[name="name"]').val(e['name']);
            /* Update the value in Angular controller */
            $('input[name="name"]').trigger('change');
            $('textarea[name="notes"]').val(e['notes']);
            $('#courses-pane-notes').text(e['notes']);
            $('#not-activated-notice').css({
                display: (e['is_activated'] == '0' ? "" : "none")
            });

            $('#submit-new-user').text("Update User");
            $('#span-after-add').setGone();
            /* Above are shared inputs between admin and students */

            /* For admin page, receive updates from which students */
            if ($('#receive-from-mhci').attr('id') != undefined) {
                var receiveFrom = e['receive_from'];
                if (receiveFrom.indexOf('mhci') != -1) {
                    $('#receive-from-mhci input').prop('checked', true);
                } else {
                    $('#receive-from-mhci input').prop('checked', false);
                }

                if (receiveFrom.indexOf('metals') != -1) {
                    $('#receive-from-metals input').prop('checked', true);
                } else {
                    $('#receive-from-metals input').prop('checked', false);
                }

                if (receiveFrom.indexOf('bhci') != -1) {
                    $('#receive-from-bhci input').prop('checked', true);
                } else {
                    $('#receive-from-bhci input').prop('checked', false);
                }

                if (receiveFrom.indexOf('ugminor') != -1) {
                    $('#receive-from-ugminor input').prop('checked', true);
                } else {
                    $('#receive-from-ugminor input').prop('checked', false);
                }

                if (receiveFrom.indexOf('learning-media') != -1) {
                    $('#receive-from-learning-media input').prop('checked', true);
                } else {
                    $('#receive-from-learning-media input').prop('checked', false);
                }
            }
            
            if ($('input[name="major"]').attr('name') != undefined) {
                /* If major exists, then it is a student page */

                $('#courses-tab').setVisible();
                if (e['number_awaiting_approval'] > 0) {
                    $('#courses-tab .badge').text(e['number_awaiting_approval']);
                }
                else {
                    $('#courses-tab .badge').text('');
                }

                $('input[name="major"]').val(e['major']);
                
                var enrollSeason = e.enroll_date.substring(0, 1);
                var enrollYear = e.enroll_date.substring(2);
                $('#enroll-season').val(enrollSeason);
                $('#enroll-season').trigger('input');
                $('#enroll-year').val(enrollYear);
                
                var graduationSeason = e.graduation_date.substring(0, 1);
                var graduationYear = e.graduation_date.substring(2);
                $('#graduation-season').val(graduationSeason);
                $('#graduation-season').trigger('input');
                $('#graduation-year').val(graduationYear);
                
                $('input[value="' + e['status'] + '"]').parent().button('toggle');
                $('input[name="is-full-time"][value="' + e['is_full_time'] + '"]').parent().button('toggle');

                /* Fill in the table in Courses pane */
                
                /* Trigger loading student courses in Angular */
                $('#andrew-id-to-load-courses').val(andrewId);
                $('#andrew-id-to-load-courses').trigger('change');

                $.get(baseUrl + "/admin/get-student-courses/andrew-id/" + andrewId + "/program/" + currentProgram, function (result) {
                    var tmp = $.parseJSON(result);
                    var courses = tmp['courses'];
                    forcedValues = tmp['forced_values'];

                    /* Fill in the Core Requirements / Prereqs / Place-outs panels */
                    /* Load requirements for the year the student entered program */
                    $('#panel-cores tbody').html('');
                    if ($('#panel-place-outs').attr('id') != undefined) {
                        $('#panel-place-outs tbody').html('');
                    }
                    if ($('#panel-prereqs').attr('id') != undefined) {
                        $('#panel-prereqs tbody').html('');
                    }

                    var reqs = $('body').data('requirements');
                    var enrollYear = $('#enroll-year').val();
                    var enrollSemester = seasonToString($('#enroll-season').val());

                    var numElectivesNeeded;
                    var coresFound = false, placeOutsFound = false, prereqsFound = false;
                    var coresIdx = 1, placeOutsIdx = 1, prereqsIdx = 1;

                    /* Set grade requirements display */
                    var gradeReqs = findGradeRequirements(enrollYear, enrollSemester, e['program']);
                    ['core', 'prerequisite', 'elective']
                        .forEach(function (e) {
                            var reqDisplay = reqDisplay = $('#' + e + '-grade-req');
                            if (reqDisplay.length > 0 && gradeReqs[e] != undefined &&
                                gradeReqs[e] != 'd') {
                                reqDisplay.text("(" + grade2Text[gradeReqs[e]] + " or above required)");
                            } else {
                                reqDisplay.text("");
                            }
                        });

                    reqs.forEach(function (e, i, arr) {
                        if (e['program'] == $('input[name="type"]').val() &&
                            e['semester'] == enrollSemester &&
                            e['year'] == enrollYear) {
                            /* Found the requirement */
                            var type = e['type'];

                            /* If grade_requirement column exists, then it's grade req */
                            if (e['grade_requirement'] != null && e['grade_requirement'].length > 0) {
                                return;
                            }

                            /* If it is the electives requirements */
                            if (type == 'elective') {
                                numElectivesNeeded = e['number'];
                                return;
                            }

                            var reg = /\d{2}-\d{3}/g;
                            if (type == 'core' || type == 'prerequisite') {
                                /* Check if the specified course is taken, and grade >= .. */

                                /* Rewrite the requirements into a boolean expression and evaluate it */
                                var reqs = '(' + e['course_numbers'] + ')';
                                reqs = replaceAll(',', ') || (', reqs);
                                reqs = replaceAll('&', ' && ', reqs);
                                
                                var isTaking = false;
                                var isSatisfied = false;

                                /* Evaluate if it is satisfied first.
                                 * If it is, no need to test if it is "Taking" or satisfied
                                 * since we had the problem that a course is used to count
                                 * towards 'Taking' of a already 'Satisfied' course,
                                 * thus we are not able to use it to count towards another requirement
                                 */
                                var forcedValue = getForcedStatus(e['course_name'], type);
                                var status = forcedValue['value'];                                
                                if (status == 'infer') {

                                    courses.forEach(function (eC, eI) {
                                        if (isSatisfied) return;

                                        var courseType = eC['taking_as'],
                                            courseStatus = eC['status'],
                                            courseNum = eC['course_number'];

                                        /* Return if course number is malformed */
                                        if (!reg.test(courseNum)) {
                                            //console.log("Course number " + courseNum + " didn't pass regex test");
                                            return;
                                        }
                                        /* Reset next index to search for reg, otherwise it
                                         * will give the wrong result (always false) !
                                         * http://stackoverflow.com/questions/1520800/why-regexp-with-global-flag-in-javascript-give-wrong-results
                                         */
                                        reg.lastIndex = 0;

                                        if (/*courseType == e['type'] &&*/ courseStatus == 'taken') {
                                            var gradeReq = gradeReqs[type];
                                            if (gradeReq == null || gradeReq.length == 0) {
                                                gradeReq = 'd';
                                            }
                                            if ((type == 'core' && (doesGradeSatisfyReq(eC['grade'], gradeReq) || eC['grade'] == 'na')) ||
                                                (type == 'prerequisite')) {
                                                if (courses[eI].usedToSatisfy != true) {
                                                    /* If one course has been used to satisfy one requirement, shouldn't be used again */
                                                    if (reqs.indexOf(courseNum) >= 0)
                                                        courses[eI].usedToSatisfy = true;

                                                    reqs = replaceAll(courseNum, ' true ', reqs);

                                                    /* Immediately try to evaluate; if already 'Satisfied', no need to waste another course
                                                     * on it */
                                                    var tempReqs = reqs;
                                                    tempReqs = tempReqs.replace(reg, " false ");
                                                    if (eval(tempReqs)) {
                                                        isSatisfied = true;
                                                    }
                                                }
                                            }
                                        }
                                    });

                                    /* If not satisfied, continue to check if it is in progress */
                                    if (!isSatisfied) {
                                        /* Look for 'Taking' */
                                        courses.forEach(function (eC, eI) {
                                            var courseType = eC['taking_as'],
                                                courseStatus = eC['status'],
                                                courseNum = eC['course_number'];

                                            if (!reg.test(courseNum)) return;
                                            reg.lastIndex = 0;

                                            if (/*courseType == e['type'] &&*/ courseStatus == 'taking' &&
                                                       e['course_numbers'].search(courseNum) != -1) {
                                                if (courses[eI].usedToTaking != true) {
                                                    /* If one course has been used in one requirement to count towards
                                                     * "taking", should not be used again in another requirement
                                                     */
                                                    courses[eI].usedToTaking = true;
                                                    isTaking = true;
                                                }
                                            }
                                        });
                                    }

                                } else if (status == 'satisfied') {
                                    isSatisfied = true;
                                /* Forced to 'not satisfied' */
                                } else {
                                    isSatisfied = false;
                                    isTaking = false;
                                }

                                var str = '<tr><td>' + (e['type'] == 'core' ? coresIdx : prereqsIdx) +
                                            '</td><td class="column-course-name">' + e['course_name'] + '</td>' +
                                            (isSatisfied ?
                                                "<td class='text-success'>Satisfied" :
                                                (isTaking ?
                                                    "<td class='text-info'>In Progress" :
                                                    "<td>Not satisfied")) +
                                            '</td></tr>';
                                if (e['type'] == 'core') {
                                    coresFound = true;
                                    coresIdx++;
                                    $('#panel-cores tbody').append(str);
                                } else {
                                    prereqsFound = true;
                                    prereqsIdx++;
                                    $('#panel-prereqs tbody').append(str);
                                }
                            }
                            else if (e['type'] == 'place-out') {
                                placeOutsFound = true;

                                var satisfied = '<td>Not satisfied';
                                var courseComment = '';
                                courses.forEach(function (eC) {
                                    if (eC['course_name'] == e['course_name']) {
                                        /* Retrieve comment */
                                        courseComment = eC['comment'];

                                        if (courseComment == null || courseComment == undefined) {
                                            courseComment = "";
                                        }

                                        if (eC['status'] == 'satisfied') {
                                            satisfied = '<td class="text-success">Satisfied';
                                        }
                                    }
                                });

                                var str = '<tr><td>' + placeOutsIdx + '</td><td class="place-out-name">' + e['course_name'] +
                                            '</td>' + satisfied + '</td><td>' + courseComment + '</td></tr>';
                                $('#panel-place-outs tbody').append(str);
                                placeOutsIdx++;
                            }
                        }

                    });

                    var emptyNotice = 'No requirements are specified for ' + enrollSemester + ' ' + enrollYear + ' semester.';
                    if (!coresFound) {
                        $('#table-cores').setGone();
                        $('#panel-cores .no-courses').setVisible();
                        $('#panel-cores .no-courses').text(emptyNotice);
                        $('#panel-electives').setGone();
                    } else {
                        $('#table-cores').setVisible();
                        $('#panel-cores .no-courses').setGone();

                        /* If cores are found, then requirements for this semester are defined. Show courses summary */
                        var summaryElectives = $('#summary-electives');
                        summaryElectives.html('');
                        var numElectivesCompleted = 0;

                        courses.forEach(function (eC) {
                            var takingAs = eC['taking_as'];
                            /* Only counting electives in here */
                            if (takingAs != 'elective')
                                return;

                            var gradeReq = gradeReqs[takingAs];

                            if (gradeReq == null || gradeReq.length == 0) {
                                gradeReq = 'd';
                            }
                            if (doesGradeSatisfyReq(eC['grade'], gradeReq) || eC['grade'] == 'na') {
                                if (eC['status'] == 'taken') {
                                    if (takingAs == 'elective') {
                                        numElectivesCompleted++;
                                    }
                                }
                            }
                        });

                        if (numElectivesNeeded != null) {
                            if (numElectivesNeeded != -1) {
                                summaryElectives.append(numElectivesCompleted + " out of " + numElectivesNeeded + " required electives are completed.");
                                summaryElectives.append('<div class="progress"><div class="progress-bar" role="progressbar" aria-valuenow="' +
                                    numElectivesCompleted + '" aria-valuemin="0" aria-valuemax="' +
                                    numElectivesNeeded + '" style="width: ' +
                                    (numElectivesCompleted / numElectivesNeeded * 100) + '%;"></div></div>');
                            }
                            else {
                                summaryElectives.append(numElectivesCompleted + " electives are completed.");
                            }
                        }

                    }

                    if (!placeOutsFound && $('#panel-place-outs').attr('id') != undefined) {
                        $('#panel-place-outs table').setGone();
                        $('#panel-place-outs .no-courses').setVisible();
                        $('#panel-place-outs .no-courses').text(emptyNotice);
                    }
                    else {
                        $('#panel-place-outs table').setVisible();
                        $('#panel-place-outs .no-courses').setGone();
                    }

                    if (!prereqsFound && $('#panel-prereqs').attr('id') != undefined) {
                        $('#panel-prereqs table').setGone();
                        $('#panel-prereqs .no-courses').setVisible();
                        $('#panel-prereqs .no-courses').text(emptyNotice);
                    }
                    else {
                        $('#panel-prereqs table').setVisible();
                        $('#panel-prereqs .no-courses').setGone();
                    }

                    attachPlaceoutHandler();
                    attachPrerequisiteHandler();
                    attachCoreClickHandler();
                });
            }
        }
    });
}

function getForcedStatus(name, type) {
    var idx;
    for (idx = 0; idx < forcedValues.length; idx++) {
        if (forcedValues[idx]['key'] == name &&
            forcedValues[idx]['type'] == type) {
            return forcedValues[idx];
        }
    }

    return { value: 'infer' };
}

function attachPrerequisiteHandler() {
    var andrewId = $('.user-selected #td-andrew-id').text();
    var modal = $('#prerequisite-details');

    $('#panel-prereqs table tbody tr').click(function () {
        var prereqName = $(this).find('.column-course-name').text();
        modal.find('#prerequisite-name').text(prereqName);
        var forcedValue = getForcedStatus(prereqName, 'prerequisite');
        var status = forcedValue['value'];
        if (status == 'infer') {
            $('input[value="infer"]').parent().button('toggle');
        } else if (status == 'satisfied') {
            $('input[value="satisfied"]').parent().button('toggle');
        } else if (status == 'not-satisfied') {
            $('input[value="not-satisfied"]').parent().button('toggle');
        }
        $('#prerequisite-details').modal('show');
    });

    var updateButton = $('#prerequisite-update-status');
    /* Unbind the click handler; otherwise the handler will be called multiple times whenever
     * fillInfoCoursesWithAndrewId is called
     */
    updateButton.unbind('click');
    updateButton.click(function () {
            var data = {
                'andrew-id': andrewId,
                program: currentProgram,
                type: 'prerequisite',
                key: modal.find('#prerequisite-name').text(),
                value: $('input[name="prerequisite-status"]:checked').val(),
                notes: ''
            };

            $.post(baseUrl + "/admin/update-forced-value", data).done(function () {
                modal.modal('hide');
                fillInfoCoursesWithAndrewId(andrewId);
            }).fail(function (ret) {
                alert('Failed to update status. Try again later.');
                console.log(ret);
            });
    });
}

function attachCoreClickHandler() {
    var andrewId = $('.user-selected #td-andrew-id').text();
    var modal = $('#core-details');

    $('#panel-cores table tbody tr').click(function () {
        var coreName = $(this).find('.column-course-name').text();
        modal.find('#core-name').text(coreName);
        var forcedValue = getForcedStatus(coreName, 'core');
        var status = forcedValue['value'];
        if (status == 'infer') {
            $('input[value="infer"]').parent().button('toggle');
        } else if (status == 'satisfied') {
            $('input[value="satisfied"]').parent().button('toggle');
        } else if (status == 'not-satisfied') {
            $('input[value="not-satisfied"]').parent().button('toggle');
        }
        $('#core-details').modal('show');
    });

    var updateButton = $('#core-update-status');
    updateButton.unbind('click');
    updateButton.click(function () {
            var data = {
                'andrew-id': andrewId,
                program: currentProgram,
                type: 'core',
                key: modal.find('#core-name').text(),
                value: $('input[name="core-status"]:checked').val(),
                notes: ''
            };

            $.post(baseUrl + "/admin/update-forced-value", data).done(function () {
                modal.modal('hide');
                fillInfoCoursesWithAndrewId(andrewId);
            }).fail(function (ret) {
                alert('Failed to update status. Try again later.');
                console.log(ret);
            });
    });
}

function attachPlaceoutHandler() {
    var andrewId = $('.user-selected #td-andrew-id').text();
    var courseId = $('#place-out-id');

    $('#panel-place-outs table tbody tr').click(function () {
        var courseName = $(this).find('.place-out-name').text();
        var studentName = $('input[name="name"]').val();

        $('#modal-place-out-student-name').text(studentName);
        $('#modal-place-out-andrew-id').text(andrewId);
        $('#modal-place-out-name').text(courseName);
        courseId.text('');
        var statusButton = $('#modal-place-out-status button');
        var notes = $('#modal-place-out-comment textarea');
        statusButton.text('Not satisfied');
        notes.val('');

        /* Search in courses array to see if comment exists */
        $.get(baseUrl + "/admin/get-student-courses/andrew-id/" + andrewId + "/program/" + currentProgram, function (result) {
            var courses = $.parseJSON(result);
            courses['courses'].forEach(function (eC) {
                if (eC['course_name'] == courseName) {
                    if (eC['status'] == 'satisfied') {
                        statusButton.text('Satisfied');
                    }
                    notes.val(eC['comment']);
                    courseId.text(eC['id']);
                }
            });

            $('#place-out-details').modal('show');
        });
    });

    $('#modal-place-out-status li').click(function () {
        $('#modal-place-out-status button').text($(this).find('a').text());
    });

    var updateButton = $('#place-out-update-status');
    updateButton.off('click');
    updateButton.click(function () {
        var data = {
            andrew_id: andrewId,
            program: currentProgram,
            course_name: $('#modal-place-out-name').text(),
            status: $('#modal-place-out-status button').text() == 'Satisfied' ? 'satisfied' : 'not-satisfied',
            comment: $('#modal-place-out-comment textarea').val(),
            taking_as: 'place-out',
            course_id: parseInt(courseId.text()),

            units: '0',
            year: '0',
            course_number: '00-000',
            grade: 'na'
        };

        var url;
        if (courseId.text().length == 0) {
            /* The course does not exist yet */
            url = "/admin/add-course";
        } else {
            /* Course exists */
            url = "/admin/update-status";
        }

        $.post(baseUrl + url, data).done(function () {
            $('#place-out-details').modal('hide');
            fillInfoCoursesWithAndrewId(andrewId);
        }).fail(function (ret) {
            alert('Failed to update status. Try again later.');
            console.log(ret);
        });
    });
}

app.controller('UserManagerController', ['$scope', '$rootScope', function ($scope, $rootScope) {
    /* 0 = Spring, 1 = Summer, 2 = Fall in the database */
    $scope.seasons = [ 'Spring', 'Summer', 'Fall' ];
    /* Options for semester selectors in course detail display */
    $scope.semesterOptions = [ "Spring", "Fall", "Summer", "N/A" ];
    $scope.selectedEnrolledSeason = 0;
    $scope.selectedGraduationSeason = 0;
    $scope.currentProgram = $('input[name="type"]').val();
    /* TODO: better change this to get data from server instead of hardcoding it */
    $scope.undergradPrograms = [ 'bhci', 'ugminor', 'learning-media' ];
    $scope.isUndergradProgram = $scope.undergradPrograms.indexOf($scope.currentProgram) >= 0;
    /* For options to choose a grade, whether to show all grades or only letter grades */
    $scope.gradeOptions = $scope.isUndergradProgram ? $rootScope.letterGrades : $rootScope.allGrades;
    
    $scope.takingAsOptions = { core: 'Core', elective: 'Elective', prerequisite: 'Prerequisite' };
    
    $scope.preapprovedElectives = [];
    
    /* Values are texts to display */
    $scope.courseSortingMethods = { 'time': 'Submission time', 'status': 'Status' };
    $scope.courseSortingMethod = 'time';
    
    /* Whether to show courses list or "No courses" */
    $scope.showCoursesList = false;
    
    $scope.selectedCourse = {};
    
    $scope.selectEnrollSeason = function (id) {
        $scope.selectedEnrolledSeason = id;
    };
    
    $scope.selectGraduationSeason = function (id) {
        $scope.selectedGraduationSeason = id;
    };
    
    $scope.loadPreapprovedElectives = function () {
        if ($scope.currentProgram != 'admin') {
            jQuery.ajax({
                url: baseUrl + "/admin/get-preapproved-electives/",
                data: { program: $scope.currentProgram },
                success: function (result) {
                    $scope.preapprovedElectives = jQuery.parseJSON(result);
                }
            });
        }
    };
    
    $scope.resetCurrentStudent = function () {
        $scope.currentStudent = {};
        $scope.currentStudent.andrewId = '';
        $scope.currentStudent.name = '';
        $scope.currentStudent.courses = [];
        $scope.currentStudent.forcedValues = [];
    };
    
    $scope.loadCurrentStudentCourses = function () {
        /* If andrewId field is empty (cleared), treat it as a signal to clear data */
        if ($scope.currentStudent.andrewId == '') {
            $scope.resetCurrentStudent();
            return;
        }

        jQuery.ajax({
			url: baseUrl + "/admin/get-student-courses/andrew-id/" + $scope.currentStudent.andrewId +
                           "/program/" + currentProgram,
			success: function (result) {
                var parsed = angular.fromJson(result);
				$scope.currentStudent.courses = parsed.courses;
                $scope.currentStudent.forcedValues = parsed.forced_values;
                $scope.sortCourses();
                $scope.showCoursesList = $scope.getNonPlaceoutCoursesCount() > 0;
			},
            async: false // Async seems to cause problems
		});
    };
    
    $scope.courseSortingMethodSelected = function (method) {
        $scope.courseSortingMethod = method;
        $scope.sortCourses();
    };
    
    $scope.sortCourses = function () {
        if ($scope.courseSortingMethod == 'time') {
            $scope.currentStudent.courses.sort(function (a, b) {
                return parseInt(b.id) - parseInt(a.id);
            });
        } else {
            $scope.currentStudent.courses.sort(function (a, b) {
                /* Otherwise, sort by status */
                /* If a or b is 'submitted' (awaiting approval), always put that in front of the other */
                if (a.status == 'submitted' && b.status == 'submitted') {
                    return 0;
                } else if (a.status == 'submitted') {
                    return -1;
                } else if (b.status == 'submitted') {
                    return 1;
                }
                /* Otherwise, compare by ASCII order */
                return a.status.localeCompare(b.status);
            });
        }
    };
    
    $scope.getNonPlaceoutCoursesCount = function () {
        var nonPlaceoutCount = 0;
        $scope.currentStudent.courses.forEach(function (e, i, arr) {
            if (e.taking_as != 'place-out') {
                nonPlaceoutCount++;
            }
        });
        return nonPlaceoutCount;
    };
    
    $scope.courseSelected = function (course) {
        /* Create a deep copy in case data is modified */
        angular.copy(course, $scope.selectedCourse);
        /* Convert to int so that it can be bound with a numeric input element */
        if (!isNaN(parseInt($scope.selectedCourse.year))) {
            $scope.selectedCourse.year = parseInt($scope.selectedCourse.year);
        }
    };
    
    $scope.courseDetailsGradeSelected = function (gradeOption) {
        $scope.selectedCourse.grade = getKey(grade2Text, gradeOption);
    };
    
    $scope.updateSelectedCourseInfo = function () {
        var data = {
            course_id: $scope.selectedCourse.id,
            status: $scope.selectedCourse.status,
            comment: $scope.selectedCourse.comment,
            semester: null,
            year: null,
            grade: null
        };

        if (data.status == 'taking' || data.status == 'taken') {
            data.semester = $scope.selectedCourse.semester;
            data.year = (data.semester == "N/A") ? null : $scope.selectedCourse.year;
        }
        if (data.status == 'taken') {
            data.grade = $scope.selectedCourse.grade;
        }

        $.post(baseUrl + "/admin/update-status", data).done(function (ret) {
            $('.modal').modal('hide');
            loadStudents($scope.currentStudent.andrewId, true, true);
        }).fail(function (ret) {
            alert('Failed to update status at this time. Please try again later.');
        });
    };
    
    $scope.deleteSelectedCourse = function () {
        var courseId = $scope.selectedCourse.id;
        var answer = confirm("Are you sure to delete the course?");
        if (answer) {
            $.post(baseUrl + '/admin/remove-course', { course_id: courseId }).done(function () {
                $('#course-details').modal('hide');
                loadStudents($scope.currentStudent.andrewId, true, true);
            }).fail(function (ret) {
                alert("Failed to delete course. Please try again later.");
            });
        }
    };
    
    $scope.newCourseGradeSelected = function (gradeOption) {
        $scope.newCourse.grade = getKey(grade2Text, gradeOption);
    };
    
    $scope.resetNewCourse = function () {
        $scope.newCourse = {};
        $scope.newCourse.name = '';
        $scope.newCourse.number = '';
        $scope.newCourse.units = '';
        $scope.newCourse.takingAs = 'core';
        $scope.newCourse.status = 'taken';
        $scope.newCourse.semester = 'Spring';
        $scope.newCourse.year = '';
        $scope.newCourse.grade = 'na';
        $scope.newCourse.comment = '';
    };

    $scope.addNewCourse = function () {
        var data = {
            andrew_id: $scope.currentStudent.andrewId,
            program: $scope.currentProgram,
            course_number: $scope.newCourse.number,
            course_name: $scope.newCourse.name,
            units: $scope.newCourse.units,
            taking_as: $scope.newCourse.takingAs,
            status: $scope.newCourse.status,
            comment: $scope.newCourse.comment,
            semester: null,
            year: null,
            grade: null
        };

        if (data.status == 'taking' || data.status == 'taken') {
            data.semester = $scope.newCourse.semester;
            data.year = (data.semester == "N/A") ? null : $scope.newCourse.year;
        }
        if (data.status == 'taken') {
            data.grade = $scope.newCourse.grade;
        }

        $.post(baseUrl + "/admin/add-course", data).done(function (ret) {
            loadStudents($scope.currentStudent.andrewId, true, true);
            $('.modal').modal('hide');
        }).fail(function (ret) {
            alert('Failed to add course. Please try again later.');
            console.log(ret);
        });
    };

    /* Not quite working; check */
    $scope.showSOCCourseDescription = function () {
        var modal = $('#soc-course-description-modal'),
            modalTitle = modal.find(".modal-title"),
            modalBody = modal.find(".modal-body");

        var courseNumber = $scope.selectedCourse.course_number;
        var semester = $scope.selectedCourse.semester;
        var year = $scope.selectedCourse.year;

        var status = $scope.selectedCourse.status;

        /* If status != taking or taken, semester and year are meaningless */
        if (status != 'taking' && status != 'taken') {
            /* Then just use this year and semester to query */
            var d = new Date();
            var semester = getSemesterFromMonth(d.getMonth() + 1);
            var year = d.getFullYear();
        }

        if (modal.size() > 0) {
            modalTitle.html('Loading course description...');
            modalBody.html('');
            modal.modal();

            var showError = function () {
                modalTitle.html('<h4>An error has occurred</h4>');
                modalBody.html('<p class="text-left">Requesting data failed, or the given combination of course number, year and semester is not/no longer stored on CMU Schedule of Classes website.</p>');
            }
            
            $.get(baseUrl + "/admin/get-soc-description/course-number/" + courseNumber + "/year/" + year + "/semester/" + semester)
                .done(function (data) {
                    var divWithData = $(data).find('div.with-data'),
                        mainTitle = divWithData.attr('data-maintitle'),
                        subTitle = divWithData.attr('data-subtitle');

                    if (divWithData.length == 0) {
                        showError();
                        return;
                    }
                    
                    modalTitle.html('<div><small>' + subTitle + '</small></div><div>' + mainTitle + '</div>');
                    modalBody.html(data);
                    
                    modal.animate({ scrollTop: 0 }, 'fast');
                })
                .fail(showError); 
        }
    };
    
    $scope.resetCurrentStudent();
    $scope.loadPreapprovedElectives();
}]);