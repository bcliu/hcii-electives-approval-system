app.controller('StatisticsController', ['$scope', function ($scope) {
    $scope.electives = [];
    
    $scope.programs = [ "", "Loading..." ];
    
    $scope.selectedProgram = $scope.programs[0];
    
    $scope.numElectivesToShow = 30;
    
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
    
    /**
     * ====================
     * TODO: store all previously loaded program electives to an array
     * Don't reload if it's already loaded before, to save time
     * ====================
     */
    $scope.loadElectives = function () {
        var url = baseUrl + "/admin/get-all-submitted-electives/program/" + $scope.selectedProgram[0];
        
        jQuery.ajax({
            url: url,
            success: function (result) {
                $scope.electives = jQuery.parseJSON(result);
            },
            async: false
        });
    };
    
    $scope.preapprove = function (elective) {
        jQuery.ajax({
            url: baseUrl + "/admin/add-preapproved-elective/",
			data: {
				program: $scope.selectedProgram[0],
				courseNumber: elective.course_number,
				courseName: elective.course_name
			},
            success: function (result) {
                $scope.loadElectives();
            },
			error: function (result) {
				alert("Failed to add elective. Please try again later");
                console.log(result);
			},
			async: false
        });
    };
    
    $scope.removePreapproval = function (elective) {
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
                console.log(result);
			},
			async: false
		});
    };
    
    $scope.loadPrograms();
}]);