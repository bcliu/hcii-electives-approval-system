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
        if ($this->session_user->loginType != 'student' || !isset($this->session_user->andrewId)) {
            $this->_redirect("/users/logout");
        }
        
        $this->dbUsers = new Application_Model_DbTable_Users();
        $this->view->andrewId = $this->session_user->andrewId;
        $this->view->name = $this->dbUsers->getNameByAndrewId($this->session_user->andrewId);
        $this->view->type = $this->dbUsers->getUserByAndrewId($this->session_user->andrewId)->program;
        $this->_helper->layout->setLayout('student-layout');
        
        $this->config = array(
            'auth' => 'login',
            'username' => 'hciieasy',
            'password' => Zend_Registry::get('AndrewPassword'),
            'ssl' => 'tls',
            'port' => 587
        );

        $this->transport = new Zend_Mail_Transport_Smtp('smtp.andrew.cmu.edu', $this->config);
    }

    public function indexAction() {
        $this->view->title = 'EASy';
        $this->view->headScript()->prependFile($this->view->baseUrl() . '/public/js/student-index.js');
        $this->view->info = $this->dbUsers->getUserByAndrewId($this->view->andrewId);
        
        $db = new Application_Model_DbTable_Courses();
        $andrewId = $this->session_user->andrewId;

        $programs = new Application_Model_DbTable_Programs();
        $program = $this->view->type;
        $enrollDate = $this->dbUsers->getUserByAndrewId($andrewId)->enroll_date;
        $enrollMonth = substr($enrollDate, 0, 2);
        $enrollYear = substr($enrollDate, 3, 4);
        $enrollSemester;

        if (1 <= $enrollMonth && $enrollMonth <= 4) {
            $enrollSemester = "Spring";
        }
        else if (5 <= $enrollMonth && $enrollMonth <= 7) {
            $enrollSemester = "Summer";
        }
        else if (8 <= $enrollMonth && $enrollMonth <= 12) {
            $enrollSemester = "Fall";
        }

        $this->view->totalCores = $programs->getNumberByType($enrollYear, $enrollSemester, $program, 'core');
        $this->view->coresTaken = $db->getNumberSatisfiedByType($andrewId, "core");

        if ($program == 'bhci') {
            if ($program == 'bhci') {
                $this->view->totalFreeElectives = $programs->getNumberOfElectivesByProgram(
                    $enrollYear, $enrollSemester, $program, "free-elective");
                $this->view->freeElectivesTaken = $db->getNumberSatisfiedByType($andrewId, "free-elective");
            }
            $this->view->totalApplicationElectives = $programs->getNumberOfElectivesByProgram(
                $enrollYear, $enrollSemester, $program, "application-elective");
            $this->view->applicationElectivesTaken = $db->getNumberSatisfiedByType($andrewId, "application-elective");
        }
        else if ($program == 'mhci' || $program == 'ugminor') {
            $this->view->totalElectives = $programs->getNumberOfElectivesByProgram(
                $enrollYear, $enrollSemester, $program, "elective");
            $this->view->electivesTaken = $db->getNumberSatisfiedByType($andrewId, "elective");
        }

        $this->view->coursesSubmitted = count($db->getCoursesByStatus($andrewId, "submitted"));
        $this->view->clarificationNeeded = count($db->getCoursesByStatus($andrewId, "need-clarification"));
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
        $andrewId = $this->session_user->andrewId;
        $this->view->all_courses = $db->getAllCoursesOfUser($andrewId);
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
                $db->addCourse($this->session_user->andrewId, $courseNumber, $courseName, $units, $description,
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