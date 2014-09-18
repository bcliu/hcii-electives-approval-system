app.controller('MessageCtrl', [ '$scope', '$http', '$rootScope', function ($scope, $http, $rootScope) {

    /* Weirdly, have to put them in rootScope for them to be reflected in inputs elements */
    $rootScope.messages = [];
    $rootScope.courseId = 0;
    /* Currently editing message */
    $scope.currentMessage = '';

    $scope.send = function () {
        var data = {
            course_id: $rootScope.courseId,
            message: $scope.currentMessage
        };
        $http.post(baseUrl + "/student/send-message", data)
            .success(function (data) {
                if (data != null && data.error != null &&
                    data.error == 1) {
                    alert("Sending message failed. Try again later.");
                } else {
                    $scope.currentMessage = '';
                    $scope.loadMessages();
                }
            })
            .error(function () {
                alert("Sending message failed. Try again later.");
            });
    };

    $scope.showMessages = function (id) {
        $rootScope.courseId = id;
        $scope.loadMessages();
        /* Show Messages modal dialog */
        $('#messages').modal('show');
    };

    $scope.loadMessages = function () {
        $http.get(baseUrl + "/student/get-messages/course_id/" +
                $rootScope.courseId)
            .success(function (data) {
                $rootScope.messages = angular.fromJson(data);
            }
        );
    };
}]);