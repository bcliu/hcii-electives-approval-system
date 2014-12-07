$(function () {
	$('#submit-new-course').click(function () {
		var courseNumberObj = $('input[name="course-number"]');
		var courseNameObj = $('input[name="course-name"]');
		var unitsObj = $('input[name="units"]');
		var courseDescriptionObj = $('textarea');
		var takingAsObj = $('input[name="taking-as"]');
        
		var courseNumber = courseNumberObj.val()
		    .match(/^\d{2}-\d{3}$/);
		var courseName = courseNameObj.val();
		var units = unitsObj.val().match(/^[0-9]+$/);
		var courseDescription = courseDescriptionObj.val();
		var takingAs;

		if (takingAsObj.length == 0) {
		    takingAs = "elective";
		}
		else {
		    takingAsObj.val();
		}
        
		if (courseNumber == null) {
		    courseNumberObj.parent().addClass('has-error');
		    courseNumberObj.popover({
			    html: true,
				content: "<h5>Course number must match the<br />pattern XX-XXX.</h5>",
				trigger: 'focus'
				});
		    courseNumberObj.popover('show');
		    return;
		}
		else if (courseName.length == 0) {
		    courseNameObj.parent().addClass('has-error');
		    courseNameObj.popover({
			    html: true,
				content: "<h5>Course name cannot be empty.</h5>",
				trigger: 'focus'
				});
		    courseNameObj.popover('show');
		    return;
		}
		else if (units == null) {
		    unitsObj.parent().addClass('has-error');
		    unitsObj.popover({
			    html: true,
				content: "<h5>Units must be a number.</h5>",
				trigger: 'focus'
				});
		    unitsObj.popover('show');
		    return;
		}
		else if ($('.btn-group .active').attr('class') == undefined) {
		    var btnGroup = $('#buttons-taking-as');
		    if (btnGroup.length != 0) {
			btnGroup.popover({
			    html: true,
				content: "<h5>Choose between " + $('input[name="taking-as"]')[0].value + "<br />and " + $('input[name="taking-as"]')[1].value + "</h5>",
				trigger: 'focus'
				});
			btnGroup.popover('show');
			return;
		    }
		}
		$('form').submit();
	    });
    });