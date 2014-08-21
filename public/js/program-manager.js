function populateYearSelector(requirements) {
    /* Fill in year options. By default, current year - 4 to current year + 4.
     * Extend if years of current requirements are outside of this range.
     */
    var earliest = new Date().getFullYear() - 4;
    var latest = new Date().getFullYear() + 4;
    requirements.forEach(function (e, i) {
        var thisYear = parseInt(e['year']);
        if (thisYear < earliest) {
            earliest = thisYear;
        }

        if (thisYear > latest) {
            latest = thisYear;
        }
    });

    $('#year ul').html('');
    for (var i = earliest; i <= latest; i++) {
        $('#year ul').append('<li><a href="javascript: ;">' + i + '</a></li>');
    }

    var yearItems = $('#year li');
    yearItems.off('click');
    yearItems.click(function () {
        var newYear = $(this).find('a').text();
        $('#year button').text(newYear);
        loadRequirements(getCurrentSemester(), newYear);
    });
}

function getCurrentSemester() {
    return $('#semester button').text();
}

function getCurrentYear() {
    return $('#year button').text();
}

/**
 * Asynchronously get requirements of current program
 */
function getRequirements(semesterToLoad, yearToLoad) {
    var currentProgram = $('#program-manager-page').attr('current-page');
    $.get(baseUrl + "/admin/get-requirements/program/" + currentProgram,
        function (result) {
            var jsonRequirements = $.parseJSON(result);
            $('body').data('requirements', jsonRequirements);
            populateYearSelector(jsonRequirements);

            if (jsonRequirements.length > 0) {
                var semester;
                var year;
                /* If semester- or year-to-load is not defined, load
                 * the one closest to current semester
                 */
                if (semesterToLoad == undefined || yearToLoad == undefined) {
                    semester = jsonRequirements[0]['semester'];
                    year = jsonRequirements[0]['year'];
                /* Otherwise load the specified semester and year */
                } else {
                    semester = semesterToLoad;
                    year = yearToLoad;
                }
                $('#semester button').text(semester);
                $('#year button').text(year);
                loadRequirements(semester, year);
            }
        }
    );
}

/* Run the following when document is ready on Program Manager page */
$(function () {

    /* Add number of electives click handler */
    $('#num-electives li').click(function () {
        var newNumber = $(this).find('a').text();
        $('#num-electives button').text(newNumber);
        onContentChanged();
    });

    $('#num-free-electives li').click(function () {
        var newNumber = $(this).find('a').text();
        $('#num-free-electives button').text(newNumber);
        onContentChanged();
    });

    $('#num-application-electives li').click(function () {
        var newNumber = $(this).find('a').text();
        $('#num-application-electives button').text(newNumber);
        onContentChanged();
    });

    var modalRemove = $('#modal-remove-semester');
    /* When Remove semester clicked, show modal */
    $('#a-remove-semester').click(function () {
        modalRemove.find('#txt-semester-to-remove').text(
            getCurrentSemester() + " " + getCurrentYear());
        modalRemove.modal('show');
    });

    /* When Remove button in the modal is clicked */
    $('#btn-remove-semester').click(function () {
        var data = {
            action: 'remove',
            semester: getCurrentSemester(),
            year: getCurrentYear()
        };

        $.post(baseUrl + "/admin/update-semester", data).done(function (ret) {
            getRequirements();
            modalRemove.modal('hide');
        }).fail(function (ret) {
            alert('Failed to remove the semester specified. Please try again later.');
        });
    });

    /* Add semester modal events */
    var modalAddSemester = $('#modal-add-semester');
    $('#a-new-semester').click(function () {
        modalAddSemester.modal('show');
    });

    modalAddSemester.find('li').click(function () {
        var newSemester = $(this).find('a').text();
        $('#btn-new-semester').text(newSemester);
    });

    /* Create a new semester */
    $('#btn-add-semester').click(function () {
        var year = modalAddSemester.find('input').val();
        var semester = $('#btn-new-semester').text();
        $('#year button').text(year);
        $('#semester button').text(semester);
        loadRequirements(semester, year);
        modalAddSemester.modal('hide');
    });

    /* Duplicate semester events */
    var modalDuplicate = $('#modal-duplicate-semester');

    modalDuplicate.find('li').click(function () {
        var newSemester = $(this).find('a').text();
        $('#btn-dup-semester').text(newSemester);
    });

    $('#a-duplicate-semester').click(function () {
        var currentYear = getCurrentYear();
        modalDuplicate.find('#txt-semester-to-dup').text(
            getCurrentSemester() + " " + currentYear);
        modalDuplicate.find('input').val(currentYear);
        modalDuplicate.modal('show');
    });

    $('#btn-duplicate-semester').click(function () {
        var toSemester = $('#btn-dup-semester').text();
        var toYear = parseInt(modalDuplicate.find('input').val());

        if (toYear <= 2000 || toYear >= 2100) {
            alert("Use a year between 2000 and 2100");
            return;
        }

        var data = {
            action: 'duplicate',
            program: $('#program-manager-page').attr('current-page'),
            fromSemester: getCurrentSemester(),
            fromYear: getCurrentYear(),
            toSemester: toSemester,
            toYear: toYear
        };

        $.post(baseUrl + "/admin/update-semester", data).done(function (ret) {
            /* Switch to the newly duplicated semester after finished */
            getRequirements(toSemester, toYear);
            modalDuplicate.modal('hide');
        }).fail(function (ret) {
            alert('Failed to duplicate the semester. Please try again later.');
        });
    });

    getRequirements();

    /* Attach option selected handlers */
    $('#semester li').click(function () {
    	var newSemester = $(this).find('a').text();
    	$('#semester button').text(newSemester);
    	loadRequirements(newSemester, getCurrentYear());
    });

    $('#course-number-notice').popover({
        html: true,
        content: "<h5>Use & to separate course numbers that<br />all need to be taken to fulfill a<br />requirement.<br />E.g. 11-111&12-111</h5>",
        trigger: 'hover',
        placement: 'right'
    });

    $('.tr-add-course input').click(function () {
        var addCourseRow = $(this).parents('.tr-add-course');
        var nextIndex;
        var prevRow = addCourseRow.prev();
        if (prevRow.hasClass('tr-course') == false) {
            /* If there are no courses in this table yet */
            nextIndex = 1;
        } else {
            /* Otherwise, pick the next number available */
            nextIndex = parseInt(prevRow.find('.index').html()) + 1;
        }

        var tableType = $(this).parents('.table').attr('type');
        if (tableType == 'place-out') {
            var newRow = '<tr class="tr-course">\
                            <td class="index">' + nextIndex + '</td>\
                            <td><input type="text" class="form-control course-name" /></td>\
                          </tr>';
        } else {
        	var newRow = '<tr class="tr-course">\
                            <td class="index">' + nextIndex + '</td>\
                            <td><input type="text" class="form-control course-name" /></td>\
                            <td>\
                                <input type="text" class="form-control tags" />\
                            </td>\
                          </tr>';
        }
    	addCourseRow.before(newRow);
        var addedRow = addCourseRow.prev();
    	addedRow.find('.course-name').focus();
        if (tableType != 'place-out') {
            addedRow.find('.tags').tokenfield()
                                  .on('afterCreateToken', afterCreateTokenEvent)
                                  .on('beforeCreateToken', beforeCreateTokenEvent);
        }
        addRemoveRowHandler(addedRow);
        addTextChangeHandler(addedRow);
    });

    $('#save-profile button').click(saveRequirements);
});

function saveRequirements() {
    var data = {
        year: getCurrentYear(),
        semester: getCurrentSemester(),
        program: $('#program-manager-page').attr('current-page'),
        requirements: []
    };

    $('table').each(function (i, e) {
        var type = $(this).attr('type');
        $(this).find('.tr-course').each(function (iC, eC) {
            data['requirements'].push({
                course_name: $(this).find('.course-name').val(),
                course_numbers: ($(this).find('.tags').attr('class') == undefined) ? ''
                                            : $(this).find('.tags').tokenfield('getTokensList'),
                type: type
            });
        });
    });

    var numFreeElectives = $('#num-free-electives button').text();
    var numElectives = $('#num-electives button').text();
    var numApplicationElectives = $('#num-application-electives button').text();
    if (numFreeElectives.length != 0) {
        data['requirements'].push({
            course_name: '',
            course_numbers: '',
            type: 'free-elective',
            number: isNaN(numFreeElectives) ? -1 : numFreeElectives
        });
    }
    if (numApplicationElectives.length != 0) {
        data['requirements'].push({
            course_name: '',
            course_numbers: '',
            type: 'application-elective',
            number: isNaN(numApplicationElectives) ? -1 : numApplicationElectives
        });
    }
    if (numElectives.length != 0) {
        data['requirements'].push({
            course_name: '',
            course_numbers: '',
            type: 'elective',
            number: isNaN(numElectives) ? -1 : numElectives
        });
    }
    
    var saveButton = $('#save-profile button');
    $.post(baseUrl + "/admin/update-program", data).done(function (ret) {
        saveButton.popover({ content: "Saved", placement: "left", trigger: "manual" });
        saveButton.popover('show');
        setTimeout(function () {
            saveButton.popover('hide');
        }, 1000);
        saveButton.attr('disabled', 'disabled');
        /* Reload requirements for the current year and semester */
        getRequirements(getCurrentSemester(), getCurrentYear());
    }).fail(function (ret) {
        saveButton.popover({
            html: true,
            content: "<h5 style='color: red'>Failed to save requirements</h5>",
            placement: "left",
            trigger: "manual"
        });
        saveButton.popover('show');
        setTimeout(function () {
            saveButton.popover('hide');
        }, 1000);
        alert(JSON.stringify(ret));
    });
}

function afterCreateTokenEvent(e) {
    /* Match & separated course numbers */
    var pattern = /^(\d{2}-\d{3})(&\d{2}-\d{3})*$/;
    if (!pattern.test(e.token.value)) {
        $(e.relatedTarget).addClass('invalid'); /* TODO relatedTarget? */
    }
}

function beforeCreateTokenEvent(e) {
    var tokens = e.token.value.split('&');
    if (tokens.length == 2) {
        e.token.label = tokens[0] + " and " + tokens[1];
    }
}

function loadRequirements(semester, year) {
	$('.tr-course').remove();
	var coreIndex = 1;
    var prereqIndex = 1;
    var placeoutIndex = 1;
    $('#num-electives button').text('No requirement');
    $('#num-free-electives button').text('No requirement');
    $('#num-application-electives button').text('No requirement');

	$('body').data('requirements').forEach(function (e, i) {
		if (e['semester'] == semester && e['year'] == year) {
			/* Found the course to add */
            var numElectives = (e['number'] == -1 || e['number'] == null) ? "No requirement" : e['number']

            if (e['type'] == 'elective') {
                $('#num-electives button').text(numElectives);
                return;
            }
            else if (e['type'] == 'application-elective') {
                $('#num-application-electives button').text(numElectives);
                return;
            }
            else if (e['type'] == 'free-elective') {
                $('#num-free-electives button').text(numElectives);
                return;
            }

            var parentTable;
            var index;

            if (e['type'] == 'core') {
                parentTable = $('#table-cores');
                index = coreIndex++;
            }
            else if (e['type'] == 'prerequisite') {
                parentTable = $('#table-prerequisite');
                index = prereqIndex++;
            }
            else if (e['type'] == 'place-out') {
                parentTable = $('#table-place-out');
                index = placeoutIndex++;
            }

			var newRow;
            if (e['type'] == 'place-out') {
                newRow = '<tr class="tr-course">\
                            <td class="index">' + index + '</td>\
                            <td><input type="text" class="form-control course-name" value="' + e['course_name'] + '"/></td>\
                          </tr>';
            }
            else {
                newRow = '<tr class="tr-course">\
                            <td class="index">' + index + '</td>\
                            <td><input type="text" class="form-control course-name" value="' + e['course_name'] + '"/></td>\
                            <td>\
                                <input type="text" class="form-control tags" value="' + e['course_numbers'] + '" />\
                            </td>\
                          </tr>';
            }

           	parentTable.find('.tr-add-course').before(newRow);

            if (e['type'] != 'place-out') {
                /* When an invalid course number is added */
                parentTable.find('.tags:last')
                    .on('afterCreateToken', afterCreateTokenEvent)
                    .on('beforeCreateToken', beforeCreateTokenEvent)
                    .tokenfield();
                /* Note that need to tokenfield() at last (not at first) for the two events to take effect */

                parentTable.find('.tokenfield:last').css({ width: '' }); /* Clear built-in width */
            }

            addRemoveRowHandler(parentTable.find('.tr-course'));
            addTextChangeHandler(parentTable.find('.tr-course'));

            /* TODO: Need a way to add & into it ? */

            /* TODO Better have a separate delete button */
		}
	});
}

function onContentChanged() {
    $('#save-profile button').removeAttr('disabled');
}

function addTextChangeHandler(trs) {
    var reenable = function () {
        /* When text changed, first re-enable the update button */
        onContentChanged();
    };
    trs.find('.course-name, .token-input').on('change input keydown', reenable);
    trs.find('.tags').on('removeToken beforeEditToken', reenable);
}

function addRemoveRowHandler(trs) {
    var parentTable = trs.parents('.table');
    /* For place-outs, only check if course number is empty */
    if (parentTable.attr('type') == 'place-out') {
        trs.find('.course-name').on('blur', function () {
            var parentTr = $(this).parents('tr');
            var courseName = parentTr.find('.course-name').val();

            if (courseName.length == 0) {
                parentTr.remove();
                reIndex(parentTable);
            }
        });
    }
    else {
        /* When inputs lose focus, check if empty */
        trs.find('.course-name, .token-input').on('blur', function () {
            var parentTr = $(this).parents('tr');
            var courseName = parentTr.find('.course-name').val();
            var courseNumbers = parentTr.find('.tags').tokenfield('getTokensList');

            if (courseName.length == 0 && courseNumbers.length == 0) {
                /* Need to get parent table of $(this) before it is deleted */
                parentTr.remove();
                reIndex(parentTable);
            }
        });
    }
}

/**
 * Reindex table rows
 */
function reIndex(table) {
    table.find('tbody .tr-course').each(function (i, e) {
        $(this).find('.index').html(i + 1);
    });
}