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
		var url = baseUrl + "/admin/get-preapproved-electives/program/" + $scope.selectedProgram[0];
        
        jQuery.ajax({
            url: url,
            success: function (result) {
                $scope.electives = jQuery.parseJSON(result);
            },
            async: false
        });
	};
	
	$scope.loadPrograms();
}]);