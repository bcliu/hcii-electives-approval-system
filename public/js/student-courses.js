app.controller('StudentCoursesController', ['$scope', '$http', function ($scope, $http) {
	$scope.courses = [];
    $scope.courseDescriptionShown = [];
    
    $scope.editingCourse = {};
    
    /* Update which course to edit */
    $scope.updateEditingCourse = function (course) {
        angular.copy(course, $scope.editingCourse);
    };
    
    /* Update info of the course currently editing */
    $scope.updateEditingCourseInfo = function () {
        /* Only take the description to edit */
        var data = {
            id: $scope.editingCourse.id,
            course_description: $scope.editingCourse.course_description
        };
        var url = baseUrl + "/student/update-course-info";
        
        $http.post(url, data).success(function (data) {
            var json = angular.fromJson(data);
            if (json.success == 1) {
                $scope.loadCourses();
            } else {
                alert('Failed to update course: ' + json.message);
            }
        }).error(function () {
            alert('Failed to update course. Please try again later.');
        });
    };
	
    $scope.loadCourses = function () {
        var url = baseUrl + "/student/get-courses-list";
        /* Clear old values */
        $scope.courseDescriptionShown = [];
        
        $http.get(url).success(function (data) {
            $scope.courses = angular.fromJson(data);
            /* Indexes of courses with nonempty advisor comments */
            var courseIdsWithComment = [];
            angular.forEach($scope.courses, function (v, k) {
                /* Generate index for correct display on the # column */
                $scope.courses[k].display_id = k;
                $scope.courseDescriptionShown.push(false);
                
                if (v.comment != "" && v.comment != null) {
                    courseIdsWithComment.push(k);
                }
            });
            
            angular.forEach(courseIdsWithComment, function (v, k) {
                $scope.courses.splice(v + k + 1, 0, {
                    comment: $scope.courses[v + k].comment,
                    large_row_type: "comment"
                });
            });
        }).error(function () {
            alert("Loading courses failed. Try again later.");
        });
    };
    
    $scope.removeCourse = function (course) {
        var del = confirm("Are you sure to remove " + course.course_number + "?");
        if (del == true) {
            var url = baseUrl + "/student/remove-course";
            $http.get(url, {
                params: { courseId: course.id }
            }).success(function (data) {
                var json = angular.fromJson(data);
                if (json.success == 1) {
                    $scope.loadCourses();
                } else {
                    alert('Removing course failed: ' + json.message);
                }
            });
        }
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
        if (str == null || str == '') return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    };
    
    $scope.generateDateText = function (dateTime) {
        return moment(dateTime).format("MM/DD/YYYY");
    };
    
    /* listIndex: index of row on page */
    $scope.toggleCourseDescription = function (listIndex, course) {
        var isShown = $scope.courseDescriptionShown[course.display_id];
        if (!isShown) {
            $scope.courses.splice(listIndex + 1, 0, {
                course_description: course.course_description,
                large_row_type: "description"
            });
        } else {
            $scope.courses.splice(listIndex + 1, 1);
        }
        $scope.courseDescriptionShown[course.display_id] = !isShown;
    };
    
    $scope.loadCourses();
}]);