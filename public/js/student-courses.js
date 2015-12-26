app.controller('StudentCoursesController', ['$scope', '$http', function ($scope, $http) {
	$scope.courses = [];
    $scope.courseDescriptionShown = [];
	
    $scope.loadCourses = function () {
        var url = baseUrl + "/student/get-courses-list";
        
        $http.get(url)
            .success(function (data) {
                $scope.courses = angular.fromJson(data);
                /* Indexes of courses with nonempty advisor comments */
                var courseIdsWithComment = [];
                angular.forEach($scope.courses, function (v, k) {
                    /* Generate index for correct display on the # column */
                    $scope.courses[k].id = k;
                    $scope.courseDescriptionShown.push(false);
                    
                    if (v.comment != "") {
                        courseIdsWithComment.push(k);
                    }
                });
                
                angular.forEach(courseIdsWithComment, function (v, k) {
                    $scope.courses.splice(v + k + 1, 0, {
                        comment: $scope.courses[v + k].comment,
                        large_row_type: "comment"
                    });
                });
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
    
    /* listIndex: index of row on page */
    $scope.toggleCourseDescription = function (listIndex, course) {
        var isShown = $scope.courseDescriptionShown[course.id];
        if (!isShown) {
            $scope.courses.splice(listIndex + 1, 0, {
                course_description: course.course_description,
                large_row_type: "description"
            });
        } else {
            $scope.courses.splice(listIndex + 1, 1);
        }
        $scope.courseDescriptionShown[course.id] = !isShown;
    };
    
    $scope.loadCourses();
}]);