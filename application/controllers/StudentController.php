<?php

/**
 * Controller for all student views
 */
class StudentController extends Zend_Controller_Action {
    
    private $dbUsers;
    private $config;
    private $transport;
    private $session_user;
    
    private function seasonToString($season) {
        if ($season == 0) {
            return "Spring";
        } else if ($season == 1) {
            return "Summer";
        } else if ($season == 2) {
            return "Fall";
        }
    }
    
    /* Public initialization function to check if it's a valid student user */
    public function init() {
        $this->session_user = new Zend_Session_Namespace('user');
        /* If this is not a student user, redirect to / */
        if ($this->session_user->loginType != 'student' || !isset($this->session_user->andrewId) ||
            !isset($this->session_user->userId)) {
            $this->_redirect("/users/logout");
        }
        
        $this->dbUsers = new Application_Model_DbTable_Users();
        $this->dbPrograms = new Application_Model_DbTable_Programs();
        $this->dbCourses = new Application_Model_DbTable_Courses();
        $this->dbForcedValues = new Application_Model_DbTable_ForcedValues();
        $this->dbChats = new Application_Model_DbTable_Chats();
        
        $this->view->andrewId = $this->session_user->andrewId;
        $user = $this->dbUsers->getUserById($this->session_user->userId);
        $this->view->name = $user->name;
        $this->view->type = $user->program;
        $this->_helper->layout->setLayout('student-layout');

        $this->view->info = $this->dbUsers->getUserById($this->session_user->userId);
        
        $this->view->enrollDate = $this->view->info->enroll_date;
        $this->view->enrollSeason = substr($this->view->enrollDate, 0, 1);
        $this->view->enrollYear = substr($this->view->enrollDate, 2, 4);
        $this->view->enrollSemester = $this->seasonToString($this->view->enrollSeason);
        
        $this->view->graduationDate = $this->view->info->graduation_date;
        $this->view->graduationSeason = substr($this->view->graduationDate, 0, 1);
        $this->view->graduationYear = substr($this->view->graduationDate, 2, 4);
        $this->view->graduationSemester = $this->seasonToString($this->view->graduationSeason);
        
        $this->config = array(
            'auth' => 'login',
            'username' => 'cmu.hcii.easy@gmail.com',
            'password' => Zend_Registry::get('AndrewPassword'),
            'ssl' => 'tls',
            'port' => 587
        );

        $this->transport = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $this->config);
    }
    
    public function returnSuccess() {
        echo Zend_Json::encode(array('success' => 1));
    }
    
    public function returnFailure($msg) {
        echo Zend_Json::encode(array(
            'success' => 0,
            'message' => $msg
        ));
    }
    
    public function getCoursesListAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $userId = $this->session_user->userId;
        
        echo Zend_Json::encode($this->dbCourses->getAllCoursesOfUser($userId, "student"));
    }
    
    public function isValidCourseId($courseId) {
        return $courseId != null && $courseId != '' && $this->dbCourses->getCourseById($courseId) != null;
    }
    
    public function canCourseBeEdited($courseId) {
        $userId = $this->session_user->userId;
        
        $course = $this->dbCourses->getCourseById($courseId);
        $courseStatus = $course->status;
        
        /* Only courses under review or need clarification can be deleted or updated */
        return $course->student_id == $userId && 
            ($courseStatus == 'submitted' || $courseStatus == 'need-clarification');
    }
    
    public function removeCourseAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $courseId = $this->getRequest()->getParam('courseId');
        
        if (!$this->isValidCourseId($courseId)) {
            $this->returnFailure('Invalid request');
            return;
        }
        
        if ($this->canCourseBeEdited($courseId)) {
            $this->dbCourses->deleteCourse($courseId);
            $this->returnSuccess();
        } else {
            $this->returnFailure('Unauthorized');
        }
    }
    
    /* POST request */
    public function updateCourseInfoAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        if ($this->getRequest()->getMethod() == 'POST') {
            $courseId = $this->getRequest()->getPost('id');
            $newCourseDescription = $this->getRequest()->getPost('course_description');
        
            /* Verify that this course can be updated */
            if (!$this->isValidCourseId($courseId)) {
                $this->returnFailure('Invalid request');
                return;
            }
            
            if ($this->canCourseBeEdited($courseId)) {
                $this->dbCourses->update(array(
                    'course_description' => $newCourseDescription
                ), "id = $courseId");
                $this->dbChats->addMessage($courseId, "[EASy message: course description updated by student]", "student");
                $this->returnSuccess();
            } else {
                $this->returnFailure('Unauthorized');
            }
        }
    }
    
    public function getForcedValuesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $userId = $this->session_user->userId;
        
        echo Zend_Json::encode($this->dbForcedValues->getValuesOfUser($userId));
    }
    
    public function getRequirementsAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $type = $this->getRequest()->getParam('type');
        $enrollYear = $this->view->enrollYear;
        $program = $this->view->type;
        $enrollSemester = $this->view->enrollSemester;
        echo Zend_Json::encode($this->dbPrograms->getReqsByType($enrollYear, $enrollSemester, $program, $type));
    }
    
    public function getGradeRequirementAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $type = $this->getRequest()->getParam('type');
        $enrollYear = $this->view->enrollYear;
        $program = $this->view->type;
        $enrollSemester = $this->view->enrollSemester;
        
        echo $this->dbPrograms->getMinGrade($program, $enrollSemester, $enrollYear, $type);
    }

    public function indexAction() {
        $userId = $this->session_user->userId;

        $this->view->title = 'EASy';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/student-index.js');
        
        $db = $this->dbCourses;

        $programs = $this->dbPrograms;
        $program = $this->view->type;
        
        $this->view->bhciOrMinor = ($program == 'bhci' || $program == 'ugminor');
        $enrollYear = $this->view->enrollYear;
        $enrollSemester = $this->view->enrollSemester;

        $this->view->coresTotal = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'core');
        $coreMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'core');
        $this->view->coresTaken = $db->getNumberSatisfiedByType($userId, "core", $coreMinGrade);
        $this->view->coresTaking = $db->getNumberTakingByType($userId, "core");

        $this->view->coresGradeReq = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'core');
        
        /* ========================
         * TODO: Move all printing to html code below to using AJAX
         * ========================
         */

        /* Minor and BHCI have prerequisites; MHCI and METALS have place-outs */
        if ($this->view->bhciOrMinor) {
            $this->view->prerequisitesTotal = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'prerequisite');
            $prereqMinGrade = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'prerequisite');
            /* TODO How to check forced values?? */
            $this->view->prerequisitesTaken = $db->getNumberSatisfiedByType($userId, "prerequisite", $prereqMinGrade);
            $this->view->prerequisitesTaking = $db->getNumberTakingByType($userId, "prerequisite");
            $this->view->prerequisitesGradeReq = $programs->getMinGrade($program, $enrollSemester, $enrollYear, 'prerequisite');
        } else {
            $this->view->placeOutsTotal = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'place-out');
            $this->view->placeOutsTaken = $db->getNumSatisfiedPlaceOuts($userId);
        }

        $numElectives = $programs->getNumberOfElectivesByProgram($enrollYear, $enrollSemester, $program);
        /* Since I'm using -1 to denote "No requirement", need to exclude those */
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

        $this->view->coursesSubmitted = count($db->getCoursesByStatus($userId, "submitted"));
        $this->view->clarificationNeeded = count($db->getCoursesByStatus($userId, "need-clarification"));
    }
    
    public function getPreapprovedElectivesAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $dbPreapproved = new Application_Model_DbTable_PreapprovedElectives();
        echo Zend_Json::encode($dbPreapproved->getAll($this->view->type)->toArray());
    }
    
    public function preapprovedElectivesAction() {
        $this->view->title = 'EASy - Preapproved Electives';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/student/preapproved-electives.js');
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
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/student-courses.js');
    }
    
    public function feedbackAction() {
        $this->view->title = 'EASy - Feedback & Complaints';
        
        if ($this->getRequest()->getMethod() == 'POST') {
            $feedback = htmlentities($this->getRequest()->getPost('feedback'));
            
            if (!Zend_Registry::get('EmailEnabled')) {
                return;
            }

            $andrewId = $this->session_user->andrewId;
            $mail = new Zend_Mail();
            $mail->setBodyHtml("<html><body><p>Feedback from student with Andrew ID $andrewId:</p>
                <div>$feedback</div>
                </body></html>");
            $mail->setFrom('hciieasy@andrew.cmu.edu', 'HCII EASy');
            $mail->addTo("cli" . "u.cmu+easy" . "@" . "g" . "mail.com");
            $mail->setSubject("Feedback for EASy from $andrewId");
            $mail->send($this->transport);
            
            $this->view->submitted = true;
        }
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
                if ($this->dbChats->addMessage($courseId, $message, "student") == -1) {
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