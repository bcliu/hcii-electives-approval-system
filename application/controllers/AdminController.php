<?php

/**
 * Controller for all administrator views
 */
class AdminController extends Zend_Controller_Action {
    
    /* Public initialization function to check if it's a valid admin user */
    public function init() {
        $this->session_user = new Zend_Session_Namespace('user');
        /* If this is not an admin user, redirect to / */
        if ($this->session_user->loginType != 'administrator' || !isset($this->session_user->andrewId) ||
            !isset($this->session_user->userId)) {
            $this->_redirect("/users/logout");
        }
        $db = new Application_Model_DbTable_Users();
        assert($this->session_user->andrewId != null && $this->session_user->userId != null);

        $this->view->andrewId = $this->session_user->andrewId;
        $this->view->name = $db->getUserById($this->session_user->userId)->name;
        $this->_helper->layout->setLayout('admin-layout');

        $this->config = array(
            'auth' => 'login',
            'username' => 'cmu.hcii.easy@gmail.com',
            'password' => Zend_Registry::get('AndrewPassword'),
            'ssl' => 'tls',
            'port' => 587
        );

        $this->transport = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $this->config);
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
            $student_id = $usersArr[$i]['id'];
            $awaitingApproval = $dbCourses->getCoursesByStatus($student_id, "submitted")->toArray();
            $dbUsers->updateAwaitings($student_id, count($awaitingApproval));
        }
    }

    /**
     * Return array of students based on specified filter.
     * If specified startYear is later than endYear, return empty array.
     * 
     * @param  int $includeGraduated Whether to include graduated and inactive students
     * @param  int $includeEnrolled  Whether to include enrolled students
     * @param  int $outstandingOnly  Whether should only show those with outstanding requests
     * @param  int  $startYear        Lower bound of students' enrollment year
     * @param  int  $endYear          Upper bound of students' enrollment year
     * @return array                  Students with all database fields and number of awaiting approval courses
     */
    function getStudents($program, $includeGraduated, $includeEnrolled,
        $outstandingOnly, $messagesOnly, $outstandingAndMessagesOnly,
        $startYear, $endYear) {

        if ($includeGraduated == 0 && $includeEnrolled == 0) {
            return array();
        }

        $startYear = intval($startYear);
        $endYear = intval($endYear);

        if ($startYear > $endYear) {
            return array();
        }

        $enrollDateFilter = "And (";
        if ($startYear != NULL && $endYear != NULL) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                if ($year != $startYear)
                    $enrollDateFilter .= " OR ";

                $enrollDateFilter .= "`enroll_date` LIKE '%$year%'";
            }
            $enrollDateFilter .= ")";
        } else
            $enrollDateFilter = " "; /* Invalid dates, set filter to empty */

        $db = new Application_Model_DbTable_Users();
        $dbCourses = new Application_Model_DbTable_Courses();
        $dbChats = new Application_Model_DbTable_Chats();
        $allUsers = array();
        $filter = $includeGraduated == 1 && $includeEnrolled == 1 ? "" :
                    ($includeEnrolled == 1 ? "AND users.status = 'enrolled'" : "AND (users.status = 'graduated' OR users.status = 'inactive')");

        if ($messagesOnly || $outstandingAndMessagesOnly) {
            $allUsers = $db->fetchAll(
                $db->select()
                   ->distinct()
                   ->from('users', array("users.*"))
                   ->join('courses', 'users.id = courses.student_id', NULL)
                   ->join('chats', 'chats.course_id = courses.id', NULL) /* Set to NULL so that columns from this table won't be returned */
                   ->where("chats.read_by_advisor = 0 AND users.role = 'student' AND users.program = '$program' $enrollDateFilter $filter")
                   ->setIntegrityCheck(false))
                ->toArray();

            // $count = count($allUsers);
            // for ($i = 0; $i < $count; $i++)
            //     $allUsers[$i]['has_unread_msg'] = 1;

            if ($outstandingAndMessagesOnly) {
                /* Find outstanding requests and add it too */
                $filter .= " AND number_awaiting_approval > 0";
                $outstandingOnes = $db->fetchAll(
                    "role = 'student' AND program = '$program' $enrollDateFilter $filter")
                    ->toArray();

                /* Merge these two arrays; delete repetitions first */
                $hasUnreadMsgUserIds = array();
                $countMessages = count($allUsers);
                for ($i = 0; $i < $countMessages; $i++) {
                    array_push($hasUnreadMsgUserIds, $allUsers[$i]['id']);
                }

                $outstandingArrayObject = new ArrayObject($outstandingOnes);
                for ($iterator = $outstandingArrayObject->getIterator();
                     $iterator->valid();
                     $iterator->next()) {
                    if (in_array($iterator->current()['id'], $hasUnreadMsgUserIds)) {
                        $iterator->offsetUnset($iterator->key());
                    }
                }

                $outstandingOnes = $outstandingArrayObject->getArrayCopy();
                $allUsers = array_merge($allUsers, $outstandingOnes);
            }
        } else {
            if ($outstandingOnly)
                $filter .= " AND number_awaiting_approval > 0";

            $allUsers = $db->fetchAll("role = 'student' AND program = '$program' $enrollDateFilter $filter")
                ->toArray();
        }

        /* Find list of students that have unread messages, attach to above array */
        $arrWithUnread = array();
        foreach ($dbChats->getStudentsWithUnread() as $student) {
            array_push($arrWithUnread, $student['student_id']);
        }

        $count = count($allUsers);
        for ($i = 0; $i < $count; $i++) {
            if (in_array($allUsers[$i]['id'], $arrWithUnread)) {
                $allUsers[$i]['has_unread_msg'] = 1;
            } else {
                $allUsers[$i]['has_unread_msg'] = 0;
            }
        }

        return $allUsers;
    }

    function updateForcedValueAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $andrewId = $this->getRequest()->getParam('andrew-id');
        $program = $this->getRequest()->getParam('program');
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getId($andrewId, $program);

        $type = $this->getRequest()->getParam('type');
        $key = $this->getRequest()->getParam('key');
        $value = $this->getRequest()->getParam('value');
        $notes = $this->getRequest()->getParam('notes');
        $dbForcedValues = new Application_Model_DbTable_ForcedValues();
        $dbForcedValues->updateValue($studentId, $type, $key, $value, $notes);
    }

    function getStudentCoursesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $andrewId = $this->getRequest()->getParam('andrew-id');
        $program = $this->getRequest()->getParam('program');
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getId($andrewId, $program);

        $dbForcedValues = new Application_Model_DbTable_ForcedValues();
        $forcedValues = $dbForcedValues->getValuesOfUser($studentId)->toArray();

        $dbCourses = new Application_Model_DbTable_Courses();
        $courses = $dbCourses->getAllCoursesOfUser($studentId, 'advisor');
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
        $outstandingOnly = $this->getRequest()->getParam('outstanding-only');
        $messagesOnly = $this->getRequest()->getParam('messages-only');
        $outstandingAndMessagesOnly = $this->getRequest()->
            getParam('outstanding-and-messages-only');
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

        $students = $this->getStudents($program, $includeGraduated, $includeEnrolled,
            $outstandingOnly, $messagesOnly, $outstandingAndMessagesOnly,
            $startYear, $endYear);

        echo Zend_Json::encode($students);
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

            /* Send an email notifying the student */
            if (!Zend_Registry::get('EmailEnabled')) {
                return;
            }

            $coursesDb = new Application_Model_DbTable_Courses();
            $usersDb = new Application_Model_DbTable_Users();

            $course = $coursesDb->getCourseById($courseId);
            $studentAndrewId = $usersDb->getUserById($course->student_id)->andrew_id;
            $courseName = $course->course_name;

            $mail = new Zend_Mail();
            $mail->setBodyHtml("<html><body><p>An advisor sent you a new message regarding the course \"$courseName\":</p>
                <p>$message</p>
                <div>&nbsp;</div>
                <div>Best,</div>
                <div>EASy Robot</div>
                </body></html>");
            $mail->setFrom('hciieasy@andrew.cmu.edu', 'HCII EASy');
            $mail->addTo($studentAndrewId . "@andrew.cmu.edu");

            $mail->setSubject("New message from advisor");
            $mail->send($this->transport);

            echo Zend_Json::encode($data);
        }
    }

    public function getSocDescriptionAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $rawCourseNumber = $this->getRequest()->getParam('course-number');
        $rawYear = $this->getRequest()->getParam('year');
        $rawSemester = $this->getRequest()->getParam('semester');

        if ($rawCourseNumber == NULL || $rawYear == NULL || $rawSemester == NULL) {
            return;
        }

        $courseNumber = trim(str_replace("-", "", $rawCourseNumber));
        $semester = substr($rawSemester, 0, 1);
        if ($rawSemester == 'Summer')
            $semester = 'M';
        $year = substr($rawYear, 2, 2);

        $prefix = "https://enr-apps.as.cmu.edu/open/SOC/SOCServlet/courseDetails?";

        $ch = curl_init();
        $suffix = "COURSE=$courseNumber&SEMESTER=$semester$year";

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $prefix . $suffix
        ));
        $result = curl_exec($ch);
        //error_log(strpos($result, "with-data"));
        if (curl_errno($ch) || strpos($result, "with-data") === false) {
            error_log("attempting to query again");
            /* Attempt to query again by changing to the current year */
            $currentYear = date("Y");
            $currentYearSuffix = substr($currentYear, 2, 2);
            $suffix = "COURSE=$courseNumber&SEMESTER=$semester$currentYearSuffix";
            error_log($suffix);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $prefix . $suffix
            ));
            $result = curl_exec($ch);

            if (!curl_errno($ch)) {
                echo $result;
            }
        } else {
            echo $result;
        }
        curl_close($ch);
    }
    
    public function userManagerAction() {
        $this->view->title = 'EASy - User Manager';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/user-manager.js');
        $type = $this->getRequest()->getParam('type');
        $db = new Application_Model_DbTable_Users();
        
        $this->view->type = $type;

        $dbPrograms = new Application_Model_DbTable_Programs();
        
        if ($type == 'mhci') {
            /* Load core and prerequisite requirements for MHCI of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('mhci');
        } else if ($type == 'ugminor') {
            /* Load core and prerequisite requirements for UGMinor, of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('ugminor');
        } else if ($type == 'learning-media') {
            $reqs = $dbPrograms->getRequirementsByProgram('learning-media');
        } else if ($type == 'admin') {
            /* Show administrators */
            $this->view->users = $db->getAdministrators()->toArray();
        } else if ($type == 'metals') {
            /* Load core, prereq and electives requirements for METALS, of all years */
            $reqs = $dbPrograms->getRequirementsByProgram('metals');
        } else {
            /* Show BHCI users for all other cases */
            $this->view->type = 'bhci';
            $reqs = $dbPrograms->getRequirementsByProgram('bhci');
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
             && $type != 'ugminor' && $type != 'learning-media') {
            $type = 'bhci'; /* Default to BHCI */
        }

        $this->view->program = $type;
    }

    public function updateStatusAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->getRequest()->getMethod() == 'POST') {
            $req = $this->getRequest();
            $id = $req->getPost('course_id');
            $status = $req->getPost('status');
            $comment = $req->getPost('comment');
            $semester = $req->getPost('semester');
            $year = $req->getPost('year');
            $grade = $req->getPost('grade');

            $dbCourses = new Application_Model_DbTable_Courses();
            $dbCourses->updateCourse($id, $status, $comment, $semester, $year, $grade);
        }
    }

    public function updateNotesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $req = $this->getRequest();

        if ($req->getMethod() == 'POST') {
            $andrewId = $req->getPost('andrew_id');
            $program = $req->getPost('program');
            $notes = $req->getPost('notes');

            $db = new Application_Model_DbTable_Users();
            $studentId = $db->getId($andrewId, $program);

            $db->updateNotes($studentId, $notes);
        }
    }
    
    public function getProgramsAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        echo Zend_Json::encode(array(
            array('bhci', 'BHCI'),
            array('ugminor', 'Undergraduate Minor'),
            array('learning-media', 'Learning Media Minor'),
            array('mhci', 'MHCI'),
            array('metals', 'METALS')
        ));
    }

    public function preapprovedElectivesAction() {
        $this->view->title = 'EASy - Preapproved Electives';
        
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/preapproved-electives.js');
    }
    
    public function getPreapprovedElectivesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $dbPreapproved = new Application_Model_DbTable_PreapprovedElectives();
        $program = $this->getRequest()->getParam('program');
        echo Zend_Json::encode($dbPreapproved->getAll($program)->toArray());
    }
    
    public function addPreapprovedElectiveAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $dbPreapproved = new Application_Model_DbTable_PreapprovedElectives();
        $req = $this->getRequest();
        $program = $req->getParam('program');
        $courseNumber = $req->getParam('courseNumber');
        $courseName = $req->getParam('courseName');
        
        try {
            $dbPreapproved->add($courseNumber, $courseName, $program);
            echo "Success";
        } catch (Exception $e) {
            $this->getResponse()
                ->setHttpResponseCode(500)
                ->appendBody($e->getMessage());
        }
    }
    
    public function deletePreapprovedElectiveAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $dbPreapproved = new Application_Model_DbTable_PreapprovedElectives();
        $req = $this->getRequest();
        $program = $req->getParam('program');
        $courseNumber = $req->getParam('courseNumber');
        
        try {
            $dbPreapproved->deleteElective($courseNumber, $program);
            echo "Success";
        } catch (Exception $e) {
            $this->getResponse()
                ->setHttpResponseCode(500)
                ->appendBody($e->getMessage());
        }
    }
    
    /**
     * Used in Statistics page to get submitted electives under a program sorted by frequency
     */
    public function getAllSubmittedElectivesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $dbCourses = new Application_Model_DbTable_Courses();
        $program = $this->getRequest()->getParam('program');
        echo Zend_Json::encode($dbCourses->getAllSubmittedElectives($program)->toArray());
    }

    public function statsAction() {
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/statistics.js');
        $this->view->title = 'EASy - Statistics';
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

        $req = $this->getRequest();

        if ($req->getMethod() == 'POST') {
            $action = $req->getPost('action');
            $dbPrograms = new Application_Model_DbTable_Programs();

            if ($action == 'remove') {
                /* Remove a semester */
                $semester = $req->getPost('semester');
                $year = $req->getPost('year');
                $program = $req->getPost('program');

                $dbPrograms->removeSemester($semester, $year, $program);
            } else if ($action == 'duplicate') {
                $program = $req->getPost('program');
                $fromSemester = $req->getPost('fromSemester');
                $fromYear = $req->getPost('fromYear');
                $toSemester = $req->getPost('toSemester');
                $toYear = $req->getPost('toYear');
                $toCopy = $dbPrograms->getReqsByProgramSemester($program, $fromSemester, $fromYear);
                $dbPrograms->removeSemester($toSemester, $toYear, $program);
                $dbPrograms->updateReqsByProgramSemester($toYear, $toSemester, $program, $toCopy->toArray());
            }
        }
    }

    public function addCourseAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $req = $this->getRequest();

        if ($this->getRequest()->getMethod() == 'POST') {
            $andrewId = $req->getPost('andrew_id');
            $program = $req->getPost('program');
            $dbUsers = new Application_Model_DbTable_Users();
            $userId = $dbUsers->getId($andrewId, $program);
            
            $courseNumber = $req->getPost('course_number');
            $courseName = $req->getPost('course_name');
            $units = $req->getPost('units');
            $takingAs = $req->getPost('taking_as');
            $status = $req->getPost('status');
            $comment = $req->getPost('comment');
            $semester = $req->getPost('semester');
            $year = $req->getPost('year');
            $grade = $req->getPost('grade');

            $dbCourses = new Application_Model_DbTable_Courses();
            $dbCourses->adminAddCourse($userId, $courseNumber, $courseName, $units, '',
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
            } else {
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
            $takingAs = 'elective';

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

    public function studentViewAction() {
        $this->session_user->loginType = "student";
        $this->session_user->andrewId = "teststudent";
        $this->_redirect("/users/select-program");
    }

    /* Secret action for viewing any student's page */
    public function cuyahogaAction() {
        $this->session_user->loginType = 'student';
        $this->session_user->andrewId = $this->getRequest()->getParam('enter');
        $this->_redirect("/users/select-program");
    }
    
    /**
     * Migration 11/27/2015: removing application elective and free elective types
     * Merge them to just one type of elective
     */
    public function migrateMergingElectivesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $dbCourses = new Application_Model_DbTable_Courses();
        $dbPrograms = new Application_Model_DbTable_Programs();
        
        $dbCourses->migrateMergingElectives();
        $dbPrograms->migrateMergingElectives();
        
        echo "Done";
    }
}
