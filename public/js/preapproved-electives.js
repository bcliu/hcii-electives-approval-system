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
	
	$scope.addNewCourse = function () {
		// TODO: validation
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
				$('#btn-add-new-course').popover('show');
			},
            async: false
        });
	};
	
	$scope.loadPrograms();
	
	var addNewCourseButton = $('#btn-add-new-course');
	addNewCourseButton.on('shown.bs.popover', function() {
		setTimeout(function() {
			addNewCourseButton.popover('hide');
		}, 5000);
	});
	addNewCourseButton.popover({
		content: 'Failed to submit the elective.<br />Has it already been added?',
		placement: 'top',
		html: true
	});
}]);