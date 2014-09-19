<?php

/**
 * Controller for all administrator views
 */
class AdminController extends Zend_Controller_Action {
    
    /* Public initialization function to check if it's a valid admin user */
    public function init() {
        $this->session_user = new Zend_Session_Namespace('user');
        /* If this is not an admin user, redirect to / */
        if ($this->session_user->loginType != 'administrator' || !isset($this->session_user->andrewId)) {
            $this->_redirect("/users/logout");
        }
        $db = new Application_Model_DbTable_Users();
        $this->view->andrewId = $this->session_user->andrewId;
        $this->view->name = $db->getNameByAndrewId($this->session_user->andrewId);
        $this->_helper->layout->setLayout('admin-layout');
    }

    /**
     * Homepage for administrator's view
     */
    public function indexAction() {
        $this->view->title = 'EASy Administrator';
        $this->_redirect("/admin/user-manager");
    }

    public function updateAwaitingCountAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $dbUsers = new Application_Model_DbTable_Users();
        $dbCourses = new Application_Model_DbTable_Courses();

        $userRows = $dbUsers->fetchAll("role = 'student' AND (program = 'mhci' OR program = 'bhci' OR program = 'ugminor')");
        $usersArr = $userRows->toArray();

        for ($i = 0; $i < count($usersArr); $i++) {
            $andrewId = $usersArr[$i]['andrew_id'];
            $awaitingApproval = $dbCourses->getCoursesByStatus($andrewId, "submitted")->toArray();
            $dbUsers->updateAwaitings($andrewId, count($awaitingApproval));
        }
    }

    /**
     * Return array of students based on specified filter.
     * If specified startYear is later than endYear, return empty array.
     * 
     * @param  bool $includeGraduated Whether to include graduated and inactive students
     * @param  bool $includeEnrolled  Whether to include enrolled students
     * @param  int  $startYear        Lower bound of students' enrollment year
     * @param  int  $endYear          Upper bound of students' enrollment year
     * @return array                  Students with all database fields and number of awaiting approval courses
     */
    function getStudents($program, $includeGraduated, $includeEnrolled, $startYear, $endYear) {
        if ($includeGraduated == 0 && $includeEnrolled == 0) {
            return array();
        }

        $db = new Application_Model_DbTable_Users();
        $dbCourses = new Application_Model_DbTable_Courses();
        $filter = $includeGraduated == 1 && $includeEnrolled == 1 ? "" :
                    ($includeEnrolled == 1 ? "AND status = 'enrolled'" : "AND (status = 'graduated' OR status = 'inactive')");

        $allUsers = array();

        if ($startYear == NULL && $endYear == NULL) {
            $rows = $db->fetchAll("role = 'student' AND `program` = '$program' $filter");
            $allUsers = $rows->toArray();
        }
        else {
            $startYear = intval($startYear);
            $endYear = intval($endYear);

            if ($startYear > $endYear) {
                return array();
            }

            for ($year = $startYear; $year <= $endYear; $year++) {
                $rows = $db->fetchAll("role = 'student' AND `program` = '$program' AND `enroll_date` LIKE '%$year%' $filter");
                $rowsArr = $rows->toArray();
                $allUsers = array_merge($allUsers, $rowsArr);
            }
        }

        return $allUsers;
    }
    
    function getForcedValues($andrew_id) {
        $dbForcedValues = new Application_Model_DbTable_ForcedValues();
        return $dbForcedValues->fetchAll("user_andrew_id = '$andrew_id' AND type = 'prerequisite'");
    }

    function updateForcedValueAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $andrewId = $this->getRequest()->getParam('andrew-id');
        $type = $this->getRequest()->getParam('type');
        $key = $this->getRequest()->getParam('key');
        $value = $this->getRequest()->getParam('value');
        $notes = $this->getRequest()->getParam('notes');
        $dbForcedValues = new Application_Model_DbTable_ForcedValues();
        $dbForcedValues->updateValue($andrewId, $type, $key, $value, $notes);
    }

    function getStudentCoursesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $andrewId = $this->getRequest()->getParam('andrew-id');
        $forcedValues = $this->getForcedValues($andrewId)->toArray();
        $dbCourses = new Application_Model_DbTable_Courses();
        $courses = $dbCourses->getAllCoursesOfUser($andrewId, 'advisor');
        echo Zend_Json::encode(array('courses' => $courses, 'forced_values' => $forcedValues));
    }

    /**
     * Serves as AJAX call handler: passes students that meet the specified filter.
     */
    public function getStudentsAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $includeGraduated = $this->getRequest()->getParam('include-graduated');
        $includeEnrolled = $this->getRequest()->getParam('include-enrolled');
        $startYear = $this->getRequest()->getParam('start-year');
        $endYear = $this->getRequest()->getParam('end-year');
        $program = $this->getRequest()->getParam('program');

        if ($program == NULL) {
            echo Zend_Json::encode(array());
            return;
        }

        /* TODO: Change this to support partial range */
        if (($startYear == NULL && $endYear != NULL) ||
            ($startYear != NULL && $endYear == NULL)) {
            echo Zend_Json::encode(array());
            return;
        }

        echo Zend_Json::encode($this->getStudents($program, $includeGraduated, $includeEnrolled, $startYear, $endYear));
    }

    public function getAdministratorsAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $db = new Application_Model_DbTable_Users();
        echo Zend_Json::encode($db->getAdministrators()->toArray());
    }

    /**
     * Get all messages from a thread
     * @return Void
     */
    public function getMessagesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $courseId = $this->getRequest()->getParam('course_id');
        $db = new Application_Model_DbTable_Chats();
        echo Zend_Json::encode($db->getMessages($courseId, 'advisor'));
    }

    /**
     * A REST API to submit student message to the advisors
     * @return Void
     */
    public function sendMessageAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $courseId = $this->getRequest()->getPost('course_id');
            $message = $this->getRequest()->getPost('message');

            $db = new Application_Model_DbTable_Chats();
            $data = array();
            if ($db->addMessage($courseId, $message, "advisor") == -1) {
                $data['error'] = 1;
            }
            echo Zend_Json::encode($data);
        }
    }
    
    public function userManagerAction() {
        $this->view->title = 'EASy - User Manager';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/user-manager.js');
        $type = $this->getRequest()->getParam('type');
        $db = new Application_Model_DbTable_Users();
        
        $this->view->type = $type;

        $dbPrograms = new Application_Model_DbTable_Programs();
        
        if ($type == 'bhci') {
            /* Load core and prerequisite requirements for BHCI of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('bhci');
        } else if ($type == 'ugminor') {
            /* Load core and prerequisite requirements for UGMinor, of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('ugminor');
        } else if ($type == 'admin') {
            /* Show administrators */
            $this->view->users = $db->getAdministrators()->toArray();
        } else if ($type == 'metals') {
            /* Load core, prereq and electives requirements for METALS, of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('metals');
        } else {
            /* Show MHCI users for all other cases */
            $this->view->type = 'mhci';

            /* Load core and place-out requirements for MHCI, of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('mhci');
        }

        if (isset($reqs)) {
            $this->view->requirements = $reqs->toArray();
        }
    }

    /**
     * An AJAX call which returns requirements of some specified program
     */
    public function getRequirementsAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $type = $this->getRequest()->getParam('program');
        $db = new Application_Model_DbTable_Programs();
        echo Zend_Json::encode($db->getRequirementsByProgram($type)->toArray());
    }

    public function programManagerAction() {
        $this->view->title = 'EASy - Program Manager';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/program-manager.js');
        $type = $this->getRequest()->getParam('type');

        /* Pass all requirements of current program (of all semesters) to the view,
           let javascript further process and show them
         */
        if ($type != 'mhci' && $type != 'bhci' && $type != 'metals'
             && $type != 'ugminor') {
            $type = 'mhci'; /* Default to MHCI */
        }

        $this->view->program = $type;
    }

    public function updateStatusAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $id = $this->getRequest()->getPost('course_id');
            $status = $this->getRequest()->getPost('status');
            $comment = $this->getRequest()->getPost('comment');
            $semester = $this->getRequest()->getPost('semester');
            $year = $this->getRequest()->getPost('year');
            $grade = $this->getRequest()->getPost('grade');

            $dbCourses = new Application_Model_DbTable_Courses();
            $dbCourses->updateCourse($id, $status, $comment, $semester, $year, $grade);
        }
    }

    public function updateNotesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $andrewId = $this->getRequest()->getPost('andrew_id');
            $notes = $this->getRequest()->getPost('notes');

            $dbCourses = new Application_Model_DbTable_Users();
            $dbCourses->updateNotes($andrewId, $notes);
        }
    }

    /**
     * Update requirements of a program, given program,
     * year, semester, and the course requirements
     */
    public function updateProgramAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $dbPrograms = new Application_Model_DbTable_Programs();
            $program = $this->getRequest()->getPost('program');
            $semester = $this->getRequest()->getPost('semester');
            $year = $this->getRequest()->getPost('year');
            $reqs = $this->getRequest()->getPost('requirements');

            $dbPrograms->updateReqsByProgramSemester($year, $semester, $program, $reqs);
        }
    }

    /**
     * An AJAX call to remove or duplicate a semester
     */
    public function updateSemesterAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $action = $this->getRequest()->getPost('action');
            $dbPrograms = new Application_Model_DbTable_Programs();

            if ($action == 'remove') {
                /* Remove a semester */
                $semester = $this->getRequest()->getPost('semester');
                $year = $this->getRequest()->getPost('year');

                $dbPrograms->removeSemester($semester, $year);
            } else if ($action == 'duplicate') {
                $program = $this->getRequest()->getPost('program');
                $fromSemester = $this->getRequest()->getPost('fromSemester');
                $fromYear = $this->getRequest()->getPost('fromYear');
                $toSemester = $this->getRequest()->getPost('toSemester');
                $toYear = $this->getRequest()->getPost('toYear');
                $toCopy = $dbPrograms->getReqsByProgramSemester($program, $fromSemester, $fromYear);
                $dbPrograms->removeSemester($toSemester, $toYear);
                $dbPrograms->updateReqsByProgramSemester($toYear, $toSemester, $program, $toCopy->toArray());
            }
        }
    }

    public function addCourseAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $andrewId = $this->getRequest()->getPost('andrew_id');
            $courseNumber = $this->getRequest()->getPost('course_number');
            $courseName = $this->getRequest()->getPost('course_name');
            $units = $this->getRequest()->getPost('units');
            $takingAs = $this->getRequest()->getPost('taking_as');
            $status = $this->getRequest()->getPost('status');
            $comment = $this->getRequest()->getPost('comment');
            $semester = $this->getRequest()->getPost('semester');
            $year = $this->getRequest()->getPost('year');
            $grade = $this->getRequest()->getPost('grade');

            $dbCourses = new Application_Model_DbTable_Courses();
            $dbCourses->adminAddCourse($andrewId, $courseNumber, $courseName, $units, '',
                 $takingAs, $status, $semester, $year, $grade, $comment);
        }
    }

    public function removeCourseAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $courseId = $this->getRequest()->getPost('course_id');

            $dbCourses = new Application_Model_DbTable_Courses();
            $dbCourses->deleteCourse($courseId);
        }
    }

    public function importUsersAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $db = new Application_Model_DbTable_Cores(); /* placeholder */
        $arr = $db->fetchAll()->toArray();

        $dbUsers = new Application_Model_DbTable_Users();

        foreach ($arr as $row) {
            echo $row['UserId'] . "\n";
            $role = ($row['UserType'] == 'S' ? 'student' : 'administrator');
            $status = "";

            switch ($row['SysStatus']) {
                case 'Active':
                $status = "enrolled";
                break;

                case 'Inactive':
                $status = 'inactive';
                break;

                case 'Graduated':
                $status = 'graduated';
            }
            $isFullTime = ($row['Status'] == 'PT' ? 0 : 1);

            if ($row['GradDate'] == null || strpos($row['GradDate'], '/') == -1) {
                $graduationDate = "";
            }
            else {
                $firstSlash = strpos($row['GradDate'], '/');
                $month = substr($row['GradDate'], 0, $firstSlash);
                $year = substr($row['GradDate'], strpos($row['GradDate'], '/', $firstSlash + 1) + 1, 4);

                if (strlen($month) == 1) {
                    $month = "0$month";
                }
                $graduationDate = "$month/$year";
            }

            if ($row['EnterDate'] == null || strpos($row['EnterDate'], '/') == -1) {
                $enrollDate = "";
            }
            else {
                $firstSlash = strpos($row['EnterDate'], '/');
                $month = substr($row['EnterDate'], 0, $firstSlash);
                if (strlen($month) == 1) {
                    $month = "0$month";
                }
                $year = substr($row['EnterDate'], strpos($row['EnterDate'], '/', $firstSlash + 1) + 1, 4);
                $enrollDate = "$month/$year";
            }

            $dbUsers->newUser($row['UserId'], $row['FirstName'] . ' ' . $row['LastName'], md5($row['Password']), $role, $status, "bhci", $isFullTime, 
                            $enrollDate, $graduationDate, "", $row['acNote'], "", 0);
        }

        echo "completed";
    }

    public function importCoresAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $db = new Application_Model_DbTable_Cores();
        $arr = $db->fetchAll()->toArray();

        $dbCourses = new Application_Model_DbTable_Courses();

        foreach ($arr as $row) {
            if ($row['NotSatisfied'] == 1 || $row['Scheduled'] == 1) {
                continue;
            }

            $andrewId = $row['UserId'];
            $courseNumber = $row['ClassNumber'];
            $courseName = $courseNumber;
            $units = 0;
            $description = "";
            $takingAs = "core";
            $status = "taken";
            $semester = "";

            if ($row['Semester'] == 'M') {
                $semester = "Summer";
            }
            else if ($row['Semester'] == 'F') {
                $semester = "Fall";
            }
            else if ($row['Semester'] == "S") {
                $semester = "Spring";
            }

            $year = $row['sYear'];

            $grade = "na";
            $grade0 = $row['Grade'];

            switch ($grade0) {
                case 'S':
                $grade = 's';
                break;

                case 'A':
                $grade = 'a';
                break;

                case 'A-':
                $grade = 'am';
                break;

                case 'B+':
                $grade = 'bp';
                break;

                case 'B':
                $grade = 'b';
                break;

                case 'A+':
                $grade = 'ap';
                break;

                case 'I':
                $grade = 'I';
                break;

                case 'B-':
                $grade = 'bm';
            }

            $comment = "";

            $dbCourses->adminAddCourse($andrewId, $courseNumber, $courseName, $units, $description,
                           $takingAs, $status, $semester, $year, $grade, $comment);
        }

        echo "Done";

    }


    public function importPlaceoutsAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $dbPlaceouts = new Application_Model_DbTable_Placeouts();
        $arr = $dbPlaceouts->fetchAll()->toArray();

        $dbCourses = new Application_Model_DbTable_Courses();

        foreach ($arr as $row) {
            if ($row['Satisfied'] == 0) {
                continue;
            }

            $andrewId = $row['UserID'];
            $courseName = $row['ClassName'];
            $commentStr = $row['Description'];
            $dbCourses->adminAddCourse($andrewId, "", $courseName, "", "", "place-out", "taken", "", "", "", $commentStr);
        }
    }

    public function importElectivesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $dbElectives = new Application_Model_DbTable_Electives();
        $arr = $dbElectives->fetchAll()->toArray();

        $dbComments = new Application_Model_DbTable_Comments();
        $dbCourses = new Application_Model_DbTable_Courses();

        foreach ($arr as $row) {
            $andrewId = $row['UserId'];
            $courseNumber = $row['ElectNumber'];
            $courseName = $row['CourseName'];
            $units = $row['NumUnits'];
            $description = $row['Description'];
            $takingAs = 'free-elective';

            if ($row['SubmittedAs'] == 'P') {
                $takingAs = 'application-elective';
            }

            $status = 'submitted';

            if ($row['Taken'] == 1) {
                $status = 'taken';
            }
            else if ($row['Approved'] == 1) {
                $status = 'approved';
            }
            else if ($row['Rejected'] == 1) {
                $status = 'rejected';
            }
            else if ($row['Confirmation'] == 1) {
                $status = 'need-clarification';
            }

            $semester = 'Spring';
            if ($row['Semester'] == 'F') {
                $semester = 'Fall';
            }
            else if ($row['Semester'] == 'M') {
                $semester = 'Summer';
            }

            $year = $row['sYear'];
            $grade = 'na';
            switch ($grade) {
                case 'A+':
                $grade = 'ap';
                break;

                case 'A':
                $grade = 'a';
                break;

                case 'A-':
                $grade = 'am';
                break;

                case 'B+':
                $grade = 'bp';
                break;

                case 'B':
                $grade = 'b';
                break;

                case 'B-':
                $grade = 'bm';
                break;

                case 'C+':
                $grade = 'cp';
                break;

                case 'C':
                $grade = 'c';
                break;

                case 'C-':
                $grade = 'cm';
                break;

                case 'D':
                $grade = 'd';

            }

            /* Get comment from CmntHistory */
            $commentRow = $dbComments->fetchRow("ElectID = ${row['ID']} AND Advisor = 1");
            $commentArr;
            $commentStr;
            if (!$commentRow) {
                $commentStr = "";
            }
            else {
                $commentArr = $commentRow->toArray();
                $commentStr = $commentArr['Comments'];

                echo "Found comment: $commentStr<br /><br />";
                $commentRow->delete();
            }

            $submissionTime = new Zend_Date($row['RequestDate'], 'MM/dd/yy hh:mm:ss');
            $submissionTime = $submissionTime->toString("MM/dd/YYYY HH:mm:ss");

            $dbCourses->adminAddCourse($andrewId, $courseNumber, $courseName, $units, $description,
                           $takingAs, $status, $semester, $year, $grade, $commentStr, $submissionTime);
        }

        echo "Done";
    }

    public function metalsViewAction() {
        $this->session_user->loginType = "student";
        $this->session_user->andrewId = "metalsstudent";
        $this->_redirect("/");
    }

    public function mhciViewAction() {
        $this->session_user->loginType = "student";
        $this->session_user->andrewId = "mhcistudent";
        $this->_redirect("/");
    }

    public function bhciViewAction() {
        $this->session_user->loginType = "student";
        $this->session_user->andrewId = "bhcistudent";
        $this->_redirect("/");
    }

    public function ugminorViewAction() {
        $this->session_user->loginType = "student";
        $this->session_user->andrewId = "minorstudent";
        $this->_redirect("/");
    }
}