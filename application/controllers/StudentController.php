<?php

/**
 * Controller for all student views
 */
class StudentController extends Zend_Controller_Action {
    
    private $dbUsers;
    private $config;
    private $transport;
    private $session_user;
    
    /* Public initialization function to check if it's a valid student user */
    public function init() {
        $this->session_user = new Zend_Session_Namespace('user');
        /* If this is not a student user, redirect to / */
        if ($this->session_user->loginType != 'student' || !isset($this->session_user->andrewId) ||
            !isset($this->session_user->userId)) {
            $this->_redirect("/users/logout");
        }
        
        $this->dbUsers = new Application_Model_DbTable_Users();
        $this->view->andrewId = $this->session_user->andrewId;
        $user = $this->dbUsers->getUserById($this->session_user->userId);
        $this->view->name = $user->name;
        $this->view->type = $user->program;
        $this->_helper->layout->setLayout('student-layout');
        
        $this->config = array(
            'auth' => 'login',
            'username' => 'cmu.hcii.easy@gmail.com',
            'password' => Zend_Registry::get('AndrewPassword'),
            'ssl' => 'tls',
            'port' => 587
        );

        $this->transport = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $this->config);
    }

    public function indexAction() {
        $userId = $this->session_user->userId;

        $this->view->title = 'EASy';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/student-index.js');
        $this->view->info = $this->dbUsers->getUserById($userId);
        
        $db = new Application_Model_DbTable_Courses();
        $andrewId = $this->session_user->andrewId;

        $forcedValues = new Application_Model_DbTable_ForcedValues();
        $programs = new Application_Model_DbTable_Programs();
        $program = $this->view->type;
        $enrollDate = $this->dbUsers->getUserById($userId)->enroll_date;
        $enrollMonth = substr($enrollDate, 0, 2);
        $enrollYear = substr($enrollDate, 3, 4);
        $enrollSemester;

        $this->view->bhciOrMinor = ($program == 'bhci' || $program == 'ugminor');

        if (1 <= $enrollMonth && $enrollMonth <= 4) {
            $enrollSemester = "Spring";
        }
        else if (5 <= $enrollMonth && $enrollMonth <= 7) {
            $enrollSemester = "Summer";
        }
        else if (8 <= $enrollMonth && $enrollMonth <= 12) {
            $enrollSemester = "Fall";
        }

        $this->view->coresTotal = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'core');
        $coreMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'core');
        $this->view->coresTaken = $db->getNumberSatisfiedByType($userId, "core", $coreMinGrade);
        $this->view->coresTaking = $db->getNumberTakingByType($userId, "core");

        $this->view->coursesList = Zend_Json::encode($db->getAllCoursesOfUser($userId, "student"));
        $this->view->coresReqs = Zend_Json::encode($programs->getReqsByType($enrollYear, $enrollSemester, $program, 'core'));
        $this->view->coresGradeReq = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'core');

        $this->view->forcedValues = Zend_Json::encode($forcedValues->getValuesOfUser($userId));

        /* Minor and BHCI have prerequisites; MHCI and METALS have place-outs */
        if ($this->view->bhciOrMinor) {
            $this->view->prerequisitesTotal = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'prerequisite');
            $prereqMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'prerequisite');
            /* TODO How to check forced values?? */
            $this->view->prerequisitesTaken = $db->getNumberSatisfiedByType($userId, "prerequisite", $prereqMinGrade);
            $this->view->prerequisitesTaking = $db->getNumberTakingByType($userId, "prerequisite");

            /* Print out prerequisites requirements */
            $this->view->prerequisitesReqs = Zend_Json::encode(
                $programs->getReqsByType($enrollYear, $enrollSemester, $program, 'prerequisite'));
            $this->view->prerequisitesGradeReq = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'prerequisite');
        } else {
            $this->view->placeOutsTotal = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'place-out');
            $this->view->placeOutsTaken = $db->getNumSatisfiedPlaceOuts($userId);
        }

        if ($program == 'bhci') {
            $numApplicationElectives = $programs->getNumberOfElectivesByProgram(
                $enrollYear, $enrollSemester, $program, "application-elective");
            $numFreeElectives = $programs->getNumberOfElectivesByProgram(
                $enrollYear, $enrollSemester, $program, "free-elective");

            /* Since I'm using -1 to denote "No requirement", need to exclude those */
            $this->view->electivesTotal = ($numApplicationElectives > 0 ? $numApplicationElectives : 0) +
                ($numFreeElectives > 0 ? $numFreeElectives : 0);

            $freeElectMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'free-elective');
            $appElectMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'application-elective');
            $this->view->electivesTaken =
                ($numFreeElectives > 0 ? $db->getNumberSatisfiedByType($userId, "free-elective", $freeElectMinGrade) : 0) +
                ($numApplicationElectives > 0 ? $db->getNumberSatisfiedByType($userId, "application-elective", $appElectMinGrade) : 0);

            $this->view->electivesTaking =
                ($numFreeElectives > 0 ? $db->getNumberTakingByType($userId, "free-elective") : 0) +
                ($numApplicationElectives > 0 ? $db->getNumberTakingByType($userId, "application-elective") : 0);
        } else if ($program == 'mhci' || $program == 'ugminor' || $program == 'metals') {
            $numElectives = $programs->getNumberOfElectivesByProgram(
                $enrollYear, $enrollSemester, $program, "elective");
            if ($numElectives > 0) {
                $electMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'elective');
                $this->view->electivesTotal = $numElectives;
                $this->view->electivesTaken = $db->getNumberSatisfiedByType($userId, "elective", $electMinGrade);
                $this->view->electivesTaking = $db->getNumberTakingByType($userId, "elective");
            } else {
                /* There is no requirements on electives */
                $this->view->electivesTotal = 0;
                $this->view->electivesTaken = 0;
                $this->view->electivesTaking = 0;
            }
        }

        $this->view->coursesSubmitted = count($db->getCoursesByStatus($userId, "submitted"));
        $this->view->clarificationNeeded = count($db->getCoursesByStatus($userId, "need-clarification"));
    }
    
    public function correctionAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->session_user->loginType != "student") {
            $this->_redirect("/");
        }
        if ($this->getRequest()->getMethod() == 'POST') {
            $content = htmlentities($this->getRequest()->getPost('content'));
            $andrewId = $this->session_user->andrewId;

            if (!Zend_Registry::get('EmailEnabled')) {
                return;
            }

            $mail = new Zend_Mail();
            $mail->setBodyHtml("<html><body><p>Student with Andrew ID $andrewId requested a correction:</p>
                <p>$content</p>
                <div>&nbsp;</div>
                <div>Best,</div>
                <div>EASy Robot</div>
                </body></html>");
            $mail->setFrom('hciieasy@andrew.cmu.edu', 'HCII EASy');

            $db = new Application_Model_DbTable_Users();
            $advisors = $db->getAdvisorsOfProgram($this->view->type);
            if (!$advisors || count($advisors) == 0) {
                $advisors = $db->getAdministrators();
            }

            $ret = "";
            foreach ($advisors as $advisor) {
                $ret .= $advisor->andrew_id;
                $mail->addTo($advisor->andrew_id . "@andrew.cmu.edu");
            }

            $mail->setSubject("New student info correction request from $andrewId");
            $mail->send($this->transport);
        }
    }
    
    /**
     * "My Courses" page
     */
    public function coursesAction() {
        $this->view->title = 'EASy - My Courses';
        $db = new Application_Model_DbTable_Courses();
        $this->view->all_courses = $db->getAllCoursesOfUser($this->session_user->userId, 'student');
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

            /* Verify if this course ID is this current user's course */
            $dbCourses = new Application_Model_DbTable_Courses();
            $dbUsers = new Application_Model_DbTable_Users();

            $data = array();
            if ($dbCourses->getCourseById($courseId)->student_id != $this->session_user->userId) {
                /* If they don't match, likely that someone is trying to breach */
                $data['error'] = 1;
                $data['message'] = "LOL go for it if you want to break the system :)";
            } else {
                $db = new Application_Model_DbTable_Chats();
                if ($db->addMessage($courseId, $message, "student") == -1) {
                    $data['error'] = 1;
                }
            }
            echo Zend_Json::encode($data);
        }
    }

    /**
     * Get all messages from a thread
     * @return Void
     */
    public function getMessagesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $courseId = $this->getRequest()->getParam('course_id');
        /* Verify if this course ID is this current user's course */
        $dbCourses = new Application_Model_DbTable_Courses();
        $dbUsers = new Application_Model_DbTable_Users();
        if ($dbCourses->getCourseById($courseId)->student_id == $this->session_user->userId) {
            $db = new Application_Model_DbTable_Chats();
            echo Zend_Json::encode($db->getMessages($courseId, 'student'));
        } else {
            echo Zend_Json::encode(array('error' => 1));
        }
    }
    
    /**
     * "Submit new course" page
     */
    public function submitAction() {
        $this->view->title = 'EASy - Submit New Course';
        /* TODO auto pull course name and description with number given? */
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/student-new-course.js');
        
        if ($this->getRequest()->getMethod() == 'POST') {
            $courseNumber = $this->getRequest()->getPost('course-number');
            $courseName = htmlentities($this->getRequest()->getPost('course-name'));
            $units = $this->getRequest()->getPost('units');
            $description = htmlentities($this->getRequest()->getPost('description'));
            $takingAs = $this->getRequest()->getPost('taking-as');
	    if ($takingAs == null) {
	        $takingAs = "elective";
	    }
            
            if (preg_match('/^\d{2}-\d{3}$/', $courseNumber) == 1 &&
                preg_match('/^[0-9]+$/', $units) == 1) {
                $db = new Application_Model_DbTable_Courses();
                $db->addCourse($this->session_user->userId, $courseNumber, $courseName, $units, $description,
                               $takingAs, "submitted");
                $this->view->submitted = true;

                if (!Zend_Registry::get('EmailEnabled')) {
                    return;
                }

                $andrewId = $this->session_user->andrewId;
                $mail = new Zend_Mail();
                $mail->setBodyHtml("<html><body><p>Student with Andrew ID $andrewId submitted a new elective for review:</p>
                    <div>Course number: $courseNumber</div>
                    <div>Course name: $courseName</div>
                    <div>Description: $description</div>
                    <div>Taking as: $takingAs</div>
                    <div>&nbsp;</div>
                    <div>Best,</div>
                    <div>EASy Robot</div>
                    </body></html>");
                $mail->setFrom('hciieasy@andrew.cmu.edu', 'HCII EASy');

                $db = new Application_Model_DbTable_Users();
                $advisors = $db->getAdvisorsOfProgram($this->view->type);
                if (!$advisors || count($advisors) == 0) {
                    $advisors = $db->getAdministrators();
                }

                $ret = "";
                foreach ($advisors as $advisor) {
                    $ret .= $advisor->andrew_id;
                    $mail->addTo($advisor->andrew_id . "@andrew.cmu.edu");
                }

                $mail->setSubject("New elective request from $andrewId");
                $mail->send($this->transport);
            }
        }
    }
}