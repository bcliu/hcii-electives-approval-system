app.controller('StudentCoursesController', ['$scope', '$http', function ($scope, $http) {
	$scope.courses = [];
	
    $scope.loadCourses = function () {
        var url = baseUrl + "/student/get-courses-list";
        
        $http.get(url)
            .success(function (data) {
                $scope.courses = angular.fromJson(data);
                console.log($scope.courses);
                //$scope.courses.splice(1, 0, { large_row: true });
            })
            .error(function () {
                alert("Loading courses failed. Try again later.");
            });
    };
    
    $scope.generateCourseNameNumberText = function (course) {
        var name = course.course_name;
        var num = course.course_number.trim();
        if (name == num || name == "") {
            return num;
        } else if (course.taking_as == 'place-out') {
            return name;
        } else {
            return num + ": " + name;
        }
    };
    
    $scope.ucfirst = function (str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    };
    
    $scope.generateDateText = function (dateTime) {
        return moment(dateTime).format("MM/DD/YYYY");
    };
    
    $scope.loadCourses();
}]);