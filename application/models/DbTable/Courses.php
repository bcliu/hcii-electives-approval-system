<?php

class Application_Model_DbTable_Courses extends Zend_Db_Table_Abstract {

    protected $_name = 'courses';

    public function addCourse($andrewId, $courseNumber, $courseName, $units, $description,
                           $takingAs, $status) {
        $date = new Zend_Date();
        $data = array(
            'student_andrew_id' => $andrewId,
            'course_number' => $courseNumber,
            'course_name' => $courseName,
            'course_description' => $description,
            'units' => $units,
            'taking_as' => $takingAs,
            'status' => $status,
            'submission_time' => $date->toString("MM/dd/YYYY HH:mm:ss"),
            'comment' => "",
            'grade' => 'na'
        );

        if ($status == 'submitted') {
            $dbUsers = new Application_Model_DbTable_Users();
            $dbUsers->addAwaitingCount($andrewId, 1);
        }

        $this->insert($data);
    }

    public function deleteByAndrewId($andrewId) {
        $this->delete("student_andrew_id = '$andrewId'");
    }

    /**
     * Adding course in admin view, which contains all information
     */
    public function adminAddCourse($andrewId, $courseNumber, $courseName, $units, $description,
                           $takingAs, $status, $semester, $year, $grade, $comment, $submissionTime = NULL) {
        $date = new Zend_Date();
        if ($submissionTime == NULL) {
            $submissionTime = $date->toString("MM/dd/YYYY HH:mm:ss");
        }
        
        $currentYear = intval($date->toString("YYYY"));
        $data = array(
            'student_andrew_id' => $andrewId,
            'course_number' => $courseNumber,
            'course_name' => $courseName,
            'course_description' => $description,
            'units' => $units,
            'taking_as' => $takingAs,
            'status' => $status,
            'submission_time' => $submissionTime,
            'comment' => $comment,
            'grade' => ($grade == NULL ? "na" : $grade),
            'semester' => ($semester == NULL ? "Spring" : $semester),
            'year' => ($year == 0 || $year == NULL ? $currentYear : $year)
        );

        if ($status == 'submitted') {
            $dbUsers = new Application_Model_DbTable_Users();
            $dbUsers->addAwaitingCount($andrewId, 1);
        }

        $this->insert($data);
    }
    
    public function getCoursesByStatus($andrewId, $status) {
        $rows = $this->fetchAll("student_andrew_id = '$andrewId' AND status = '$status'");
        return $rows;
    }

    public function getCourseById($id) {
        $row = $this->fetchRow("id = $id");
        return $row;
    }

    public function getAllCoursesOfUser($andrewId) {
        $rows = $this->fetchAll("student_andrew_id = '$andrewId'");
        return $rows;
    }

    public function getNumberSatisfiedByType($andrewId, $type) {
        $grade = "grade = 'b' OR grade = 'bp' OR grade = 'am' OR grade = 'a' OR grade = 'ap' OR grade = 'na'";
        if ($type != 'core') {
            $grade .= " OR grade = 'c' OR grade = 'cp' OR grade = 'bm'";
        }
        $rows = $this->fetchAll("student_andrew_id = '$andrewId' AND status = 'taken' AND taking_as = '$type' AND ($grade)");
        return count($rows);
    }

    public function updateCourse($courseId, $status, $comment, $semester, $year, $grade) {
        $date = new Zend_Date();
        $currentYear = intval($date->toString("YYYY"));
        $data = array(
            'status' => $status,
            'comment' => $comment,
            'semester' => ($semester == NULL ? "Spring" : $semester),
            'year' => ($year == 0 || $year == NULL ? $currentYear : $year),
            'grade' => ($grade == NULL ? "na" : $grade)
        );

        /* Get original status and update number of courses awaiting approval count if necessary */
        $dbUsers = new Application_Model_DbTable_Users();
        $course = $this->getCourseById($courseId);
        $andrewId = $course->student_andrew_id;
        $originalStatus = $course->status;
        if ($originalStatus == 'submitted' && $status != 'submitted') {
            error_log("adding 1 to awaiting count");
            $dbUsers->addAwaitingCount($andrewId, -1);
        }
        else if ($originalStatus != 'submitted' && $status == 'submitted') {
            error_log("adding -1 to awaiting count");
            $dbUsers->addAwaitingCount($andrewId, 1);
        }

        $this->update($data, "id = $courseId");
    }

    public function deleteCourse($courseId) {
        $course = $this->getCourseById($courseId);
        $andrewId = $course->student_andrew_id;
        $status = $course->status;

        if ($status == 'submitted') {
            $dbUsers = new Application_Model_DbTable_Users();
            $dbUsers->addAwaitingCount($andrewId, -1);
        }
        
        $this->delete("id = '$courseId'");
    }

}