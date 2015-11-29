app.controller('PreapprovedElectivesController', ['$scope', function ($scope) {
	$scope.electives = [];
	
	$scope.programs = [];
	
	$scope.selectedProgram = [ "", "Loading..." ];
	
	$scope.programSelected = function (program) {
		$scope.selectedProgram = program;
		$scope.loadElectives();
	};
	
	$scope.loadPrograms = function () {
		jQuery.ajax({
			url: baseUrl + "/admin/get-programs",
			success: function (result) {
				$scope.programs = jQuery.parseJSON(result);
				$scope.selectedProgram = $scope.programs[0];
				$scope.loadElectives();
			},
			async: false
		});
	};
	
	$scope.loadElectives = function () {
        jQuery.ajax({
            url: baseUrl + "/admin/get-preapproved-electives/",
			data: { program: $scope.selectedProgram[0] },
            success: function (result) {
                $scope.electives = jQuery.parseJSON(result);
            },
            async: false
        });
	};
	
	$scope.newCourseNumber = "";
	$scope.newCourseName = "";
	$scope.newCourseEditing = false;
	
	$scope.enterNewCourseEditing = function () {
		$scope.newCourseEditing = true;
	};
	
	$scope.exitNewCourseEditing = function () {
		if ($scope.newCourseNumber.length == 0 && $scope.newCourseName.length == 0) {
			$scope.newCourseEditing = false;
		}
	};
	
	$scope.clearNewCourse = function () {
		$scope.newCourseNumber = "";
		$scope.newCourseName = "";
		$scope.newCourseEditing = false;
	};
	
	$scope.showPopover = function (obj, msg) {
		obj.popover('destroy');
		obj.popover({
			content: msg,
			placement: 'top',
			html: true
		});
		obj.popover('show');
		setTimeout(function() {
			obj.popover('hide');
			obj.popover('destroy');
		}, 3000);
	};
	
	$scope.keyPressed = function (event) {
		if (event.which == 13) {
			$scope.addNewCourse();
		}
	};
	
	/* Add and delete have to be synchronized -- otherwise new electives will be loaded before
	 * request is completed!
	 */
	$scope.addNewCourse = function () {
		if ($scope.newCourseNumber.match(/^\d{2}-\d{3}$/) == null) {
			$scope.showPopover($('#new-course-number-input'), 'Course number must be in<br />the form of xx-xxx');
			return;
		}
		if ($scope.newCourseName.length == 0) {
			$scope.showPopover($('#new-course-name-input'), 'Course name cannot be empty');
			return;
		}
		
        jQuery.ajax({
            url: baseUrl + "/admin/add-preapproved-elective/",
			data: {
				program: $scope.selectedProgram[0],
				courseNumber: $scope.newCourseNumber,
				courseName: $scope.newCourseName
			},
            success: function (result) {
                $scope.loadElectives();
				$scope.clearNewCourse();
            },
			error: function (result) {
				$scope.showPopover($('#btn-add-new-course'), 'Failed to submit the elective.<br />Has it already been added?');
			},
			async: false
        });
	};
	
	$scope.delete = function (elective) {
		jQuery.ajax({
			url: baseUrl + "/admin/delete-preapproved-elective",
			data: {
				program: $scope.selectedProgram[0],
				courseNumber: elective.course_number
			},
			success: function () {
				$scope.loadElectives();
			},
			error: function (result) {
				alert("Failed to delete elective. Please try again later");
			},
			async: false
		});
	};
	
	$scope.loadPrograms();
}]);