app.controller('StatisticsController', ['$scope', function ($scope) {
    $scope.electives = [];
    
    $scope.programs = [
        [ 'mhci', 'MHCI' ],
        [ 'bhci', 'BHCI' ],
        [ 'ugminor', 'Undergraduate Minor' ],
        [ 'learning-media', 'Learning Media' ],
        [ 'metals', 'METALS' ]
    ];
    
    $scope.selectedProgram = $scope.programs[0];
    
    $scope.numElectivesToShow = 30;
    
    $scope.programSelected = function (program) {
        $scope.selectedProgram = program;
        $scope.loadElectives();
    };
    
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
    
    (function() {
        $scope.loadElectives();
    })();
}]);