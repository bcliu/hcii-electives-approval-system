app.controller('StudentPreapprovedController', ['$scope', '$http', function ($scope, $http) {
    $scope.electives = [];

    $scope.loadElectives = function () {
        jQuery.ajax({
            url: baseUrl + "/student/get-preapproved-electives/",
            success: function (result) {
                $scope.electives = jQuery.parseJSON(result);
            },
            async: false
        });
    };

    $scope.loadElectives();
}]);