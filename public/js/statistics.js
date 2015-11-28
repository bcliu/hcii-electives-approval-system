app.controller('StatisticsController', ['$scope', function ($scope) {
    $scope.electives = [];
    
    $scope.programs = [
        [ 'bhci', 'BHCI' ],
        [ 'ugminor', 'Undergraduate Minor' ],
        [ 'learning-media', 'Learning Media' ],
        [ 'mhci', 'MHCI' ],
        [ 'metals', 'METALS' ]
    ];
    
    $scope.selectedProgram = $scope.programs[0];
    
    $scope.numElectivesToShow = 30;
    
    $scope.programSelected = function (program) {
        $scope.selectedProgram = program;
        $scope.loadElectives();
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
    
    (function() {
        $scope.loadElectives();
    })();
}]);