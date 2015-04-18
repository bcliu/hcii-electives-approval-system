<?php

class Application_Model_DbTable_Courses extends Zend_Db_Table_Abstract {

    protected $_name = 'courses';

    public function addCourse($andrewId, $courseNumber, $courseName, $units, $description,
                           $takingAs, $status) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);

        $date = new Zend_Date();
        $data = array(
            'student_id' => $studentId,
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
            $dbUsers->addAwaitingCount($andrewId, 1);
        }

        $this->insert($data);
    }

    public function deleteByAndrewId($andrewId) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);
        $this->delete("student_id = '$studentId'");
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

        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);
        
        $currentYear = intval($date->toString("YYYY"));
        $data = array(
            'student_id' => $studentId,
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
            $dbUsers->addAwaitingCount($andrewId, 1);
        }

        $this->insert($data);
    }
    
    public function getCoursesByStatus($andrewId, $status) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);

        $rows = $this->fetchAll("student_id = '$studentId' AND status = '$status'");
        return $rows;
    }

    public function getCourseById($id) {
        $row = $this->fetchRow("id = $id");
        return $row;
    }

    /**
     * Get all courses taken/submitted by user
     * @param  String $andrewId Andrew ID of student
     * @param  String $viewer Who will use the courses data. If advisor, will also return if
     *                        there are messages unread by advisor. Similarly for students.
     * @return Array           All courses taken/submitted by student with specified Andrew ID.
     *                         For each course, a has_unread_msg flag is set to 1 if there are
     *                         unread messages for this student from the course.
     *                         The flag is set to 0 if all messages of this course has been read.
     */
    public function getAllCoursesOfUser($andrewId, $viewer) {
        if ($viewer != 'advisor' && $viewer != 'student') {
            throw new Exception("Unrecognized viewer", 1);
        }

        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);

        $rows = $this->fetchAll("student_id = '$studentId'")->toArray();
        $dbChats = new Application_Model_DbTable_Chats();
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            if ($dbChats->hasUnreadMessages($rows[$i]['id'],
                /* Second parameter is origin of message */
                $viewer == 'student' ? 'advisor' : 'student')) {
                $rows[$i]['has_unread_msg'] = 1;
            } else {
                $rows[$i]['has_unread_msg'] = 0;
            }
        }
        return $rows;
    }

    /**
     * Generate SQL query statement that finds grades >= minGrade
     * @param  String $minGrade Minimum grade requirement
     */
    public function generateGradesAbove($minGrade) {
        $allGrades = array(
            "ap", "a", "am", "bp", "b", "bm", "cp", "c", "cm", "dp", "d"
        );

        $query = "grade = 'na' OR ";

        if ($minGrade == null) {
            throw new Exception("No course requirements defined for the semester you were enrolled in. Your advisor has been notified.");
            return "grade = 'na' OR grade = '" . join("' OR grade = '", $allGrades) . "'";
        }

        foreach ($allGrades as $grade) {
            if ($grade != $minGrade)
                $query .= "grade = '$grade' OR ";
            else {
                $query .= "grade = '$grade'";
                break;
            }
        }

        //error_log("Generated query grade string: $query");

        return $query;
    }

    /**
     * @param  String $andrewId Andrew ID of student
     * @param  String $type     Core, prerequisite etc.
     * @param  String $minGrade Minimum grade required
     */
    public function getNumberSatisfiedByType($andrewId, $type, $minGrade) {
        $grades = $this->generateGradesAbove($minGrade);
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);

        $rows = $this->fetchAll("student_id = '$studentId' AND status = 'taken' AND taking_as = '$type' AND ($grades)");
        return count($rows);
    }

    public function getNumberTakingByType($andrewId, $type) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);

        $rows = $this->fetchAll("student_id = '$studentId' AND status = 'taking' AND taking_as = '$type'");
        return count($rows);
    }

    public function getNumSatisfiedPlaceOuts($andrewId) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrewId);

        $rows = $this->fetchAll("student_id = '$studentId' AND status = 'satisfied' and taking_as = 'place-out'");
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
        $studentId = $course->student_id;
        $originalStatus = $course->status;
        if ($originalStatus == 'submitted' && $status != 'submitted') {
            //error_log("adding 1 to awaiting count");
            $dbUsers->addAwaitingCount($studentId, -1);
        }
        else if ($originalStatus != 'submitted' && $status == 'submitted') {
            //error_log("adding -1 to awaiting count");
            $dbUsers->addAwaitingCount($studentId, 1);
        }

        $this->update($data, "id = $courseId");
    }

    public function deleteCourse($courseId) {
        $course = $this->getCourseById($courseId);
        $studentId = $course->student_id;
        $status = $course->status;

        if ($status == 'submitted') {
            $dbUsers = new Application_Model_DbTable_Users();
            $dbUsers->addAwaitingCount($studentId, -1);
        }
        
        $this->delete("id = '$courseId'");
    }

}
