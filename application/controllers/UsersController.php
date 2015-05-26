<?php

class UsersController extends Zend_Controller_Action
{
    private $config;
    private $transport;
    
    public function init() {
        $this->config = array(
            'auth' => 'login',
            'username' => 'cmu.hcii.easy@gmail.com',
            'password' => Zend_Registry::get('AndrewPassword'),
            'ssl' => 'tls',
            'port' => 587
        );

        $this->transport = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $this->config);
    }
    
    public function getInfoAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        $type = $this->getRequest()->getParam('type');
        $andrewId = $this->getRequest()->getParam('andrew-id');
        echo $this->retrieveNameMajor($andrewId, $type);
    }

    public function retrieveNameMajor($andrewId, $type) {
        $CMU_DIRECTORY_URL = "https://directory.andrew.cmu.edu/search/basic/results/cmuAndrewId=";
        if ($type == 'name') {
            /* Pull from CMU directory */
            $result = file_get_contents("$CMU_DIRECTORY_URL$andrewId", false, null);
            $start = strpos($result, "search_results");
            $start = strpos($result, "<h1>", $start);
            $end = strpos($result, "</h1>", $start);
            $name = substr($result, $start, $end - $start);

            $start = strpos($name, ">") + 1;
            $len = strpos($name, "(") - $start;
            return trim(substr($name, $start, $len));
        }
        else if ($type == 'major') {
            $result = file_get_contents("$CMU_DIRECTORY_URL$andrewId", false, null);
            $MAJOR_START = "Department with which this person is affiliated:</div>";
            $start = strpos($result, $MAJOR_START) + strlen($MAJOR_START);
            $end = strpos($result, "</div>", $start);
            return trim(substr($result, $start, $end - $start));
        }
    }

    public function importAction() {
        $ENTERED_PROGRAM = "entered program";
        $NAME = "name";
        $ANDREW_ID = "andrew id";
        $PROGRAM = "program";
        $STATUS = "status";
        $FTPT = "ft/pt";
        $PRIMARY_MAJOR = "primary major";
        $EXPECTED_GRADUATION = "expected graduation";

        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $session_user = new Zend_Session_Namespace('user');
        if ($session_user->loginType != "administrator") {
            $this->_redirect("/");
        }
        if ($this->getRequest()->getMethod() == 'POST') {
            $filename = $_FILES["file"]["tmp_name"];
            require_once(APPLICATION_PATH . '/../library/PHPExcel/PHPExcel/IOFactory.php');

            $numUsers;

            try {
                $objPHPExcel = PHPExcel_IOFactory::load($filename);

                $numUsers = $objPHPExcel->getActiveSheet()->getHighestRow() - 1;

                /* Change the format of date columns to the easier to read one */
                $row = $objPHPExcel->getActiveSheet()->getRowIterator(1)->current();
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $value = strtolower($cell->getValue());
                    if ($value == $ENTERED_PROGRAM || $value == $EXPECTED_GRADUATION) {
                        $col = $cell->getColumn();
                        $size = $objPHPExcel->getActiveSheet()->getHighestRow();
                        //$objPHPExcel->getActiveSheet()
                        //            ->getStyle("${col}2:${col}${size}")
                        //            ->getNumberFormat()
                        //            ->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH); /* e.g. 14-01-01 */
                    }
                }

                $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

                $andrewIdColPresent = false;
                $enteredColPresent = false;
                $programColPresent = false;
                $expectedColPresent = false;

                foreach ($sheetData[1] as $col => $colName) {
                    $colName = strtolower($colName);

                    switch ($colName) {
                        case $ANDREW_ID:
                        $andrewIdColPresent = true;
                        break;

                        case $ENTERED_PROGRAM:
                        $enteredColPresent = true;
                        break;

                        case $PROGRAM:
                        $programColPresent = true;
                        break;

                        case $EXPECTED_GRADUATION:
                        $expectedColPresent = true;
                    }

                    /* Loop through row 2 to row n to replace column name */
                    for ($i = 2; $i <= count($sheetData); $i++) {
                        $sheetData[$i][$colName] = $sheetData[$i][$col];
                        unset($sheetData[$i][$col]);
                    }
                }
                /* Remove the columns header */
                unset($sheetData[1]);

                if (!$andrewIdColPresent) {
                    throw new Exception("Required column 'Andrew ID' is absent");
                }
                if (!$enteredColPresent) {
                    throw new Exception("Required column 'Entered program' is absent");
                }
                if (!$expectedColPresent) {
                    throw new Exception("Required column 'Expected graduation' is absent");
                }
                if (!$programColPresent) {
                    throw new Exception("Required column 'Program' is absent");
                }

                /* Loop through all users and add them */
                foreach ($sheetData as $row) {
                    if (!isset($row[$ANDREW_ID]) || $row[$ANDREW_ID] == "") {
                        throw new Exception("Andrew ID(s) of at least one user is not specified");
                    }
                    if (!isset($row[$ENTERED_PROGRAM]) || $row[$ENTERED_PROGRAM] == "") {
                        throw new Exception("Date entered program is not specified for user with Andrew ID ${row[$ANDREW_ID]}");
                    }
                    if (!isset($row[$PROGRAM]) || $row[$PROGRAM] == "") {
                        throw new Exception("Program is not specified for user with Andrew ID ${row[$ANDREW_ID]}");
                    }
                    if (!isset($row[$EXPECTED_GRADUATION]) || $row[$EXPECTED_GRADUATION] == "") {
                        throw new Exception("Expected graduation date is not specified for user with Andrew ID ${row[$ANDREW_ID]}");
                    }
                    
                    $andrewId = $row[$ANDREW_ID];
                    $name;
                    if (!isset($row[$NAME]) || $row[$NAME] == "") {
                        $name = $this->retrieveNameMajor($andrewId, 'name');
                        /* If name is weird.... then something went wrong */
                        if ($name == "" || strlen($name) > 50) {
                            throw new Exception("Failed to retrieve user name with Andrew ID \"$andrewId\" from CMU Directory.");
                        }
                    }
                    else {
                        $name = $row[$NAME];
                    }

                    $major;
                    if (!isset($row[$PRIMARY_MAJOR]) || $row[$PRIMARY_MAJOR] == "") {
                        $major = $this->retrieveNameMajor($andrewId, 'major');
                        if ($major == "" || strlen($major) > 80) {
                            throw new Exception("Failed to retrieve major of user with Andrew ID \"$andrewId\" from CMU Directory.");
                        }
                    }
                    else {
                        $major = $row[$PRIMARY_MAJOR];
                    }

                    $role = "student";
                    $notes = "";
                    $receiveFrom = "";
                    $status;
                    if (!isset($row[$STATUS]) || $row[$STATUS] == "") {
                        $status = "enrolled";
                    }
                    else {
                        $status = strtolower($row[$STATUS]);
                        if ($status == "g" || $status == "graduated") {
                            $status = "graduated";
                        }
                        else if ($status == "i" || $status == "inactive") {
                            $status = "inactive";
                        }
                        else {
                            $status = "enrolled";
                        }
                    }

                    $program = strtolower($row[$PROGRAM]);
                    if ($program == "m" || $program == "mhci") {
                        $program = "mhci";
                    } else if ($program == 'metals') {
                        $program = 'metals';
                    } else if ($program == "b" || $program == "bhci") {
                        $program = "bhci";
                    } else if ($program == "minor") {
                        $program = "ugminor";
                    } else if ($program == 'learningmedia') {
                        $program = "learning-media";
                    } else {
                        throw new Exception("Unrecognized program for user with Andrew ID $andrewId");
                    }

                    $isFullTime;
                    if (!isset($row[$FTPT]) || $row[$FTPT] == "") {
                        $isFullTime = 1;
                    }
                    else {
                        $isFullTime = strtolower($row[$FTPT]);
                        if ($isFullTime == "p" || $isFullTime == "part-time" || $isFullTime == "parttime") {
                            $isFullTime = 0;
                        }
                        else {
                            $isFullTime = 1;
                        }
                    }

                    /* enrollDate */
                    $enrollDate = date_parse_from_format("m/Y", $row[$ENTERED_PROGRAM]);
                    if ($enrollDate['year'] == false || $enrollDate['month'] == false) {
                        throw new Exception("Unrecognized date format for user with Andrew ID $andrewId");
                    }
                    else {
                        $enrollDate = sprintf('%02d', $enrollDate['month']) . '/' . $enrollDate['year'];
                    }

                    /* graduationDate */
                    $graduationDate = date_parse_from_format("m/Y", $row[$EXPECTED_GRADUATION]);
                    if ($graduationDate['year'] == false || $graduationDate['month'] == false) {
                        throw new Exception("Unrecognized date format for user with Andrew ID ${row[$ANDREW_ID]}");
                    }
                    else {
                        $graduationDate = sprintf('%02d', $graduationDate['month']) . '/' . $graduationDate['year'];
                    }

                    $this->createUser($andrewId, $name, $role, $status, $program, $isFullTime, $enrollDate,
                                      $graduationDate, $major, $notes, $receiveFrom);
                }
            } catch (Exception $e) {
                $msg = array('success' => 0, 'message' => $e->getMessage());
                echo Zend_Json::encode($msg);
                return;
            }

            $msg = array('success' => 1, 'message' => "Successfully created/updated $numUsers users.");
            echo Zend_Json::encode($msg);
        }
    }

    public function indexAction() {
        $this->_redirect("/users/login");
    }

    private function createUser($andrewId, $name, $role, $status, $program, $isFullTime, $enrollDate,
                                $graduationDate, $major, $notes, $receiveFrom) {
        $db = new Application_Model_DbTable_Users();
        /* Set updateFlag based on whether Andrew ID exists */
        $updateFlag = ($db->getUserByAndrewIdAndProgram($andrewId, $program) != null) ? 1 : 0;
        $db->newUser($andrewId, $name, $role, $status,
                     $program, $isFullTime, $enrollDate, $graduationDate,
                     $major, $notes, $receiveFrom, $updateFlag);
        
        if ($updateFlag == 0 && Zend_Registry::get('EmailEnabled') && getenv('APPLICATION_ENV') == 'development') {
            /* If new user, send a mail with temporary password */
            $mail = new Zend_Mail();
            $mail->setBodyHtml("<html><body><p>Dear $name,</p><p>Your HCII EASy account has just been created. Log in to http://easy.hcii.cs.cmu.edu/easy with your Andrew ID to manage your HCI courses, submit elective requests and track your graduation status.</p>&nbsp;<p></p><p>Best,</p><p>EASy Robot</p></body></html>");
            $mail->setFrom('hciieasy@andrew.cmu.edu', 'HCII EASy');
            $mail->addTo("$andrewId@andrew.cmu.edu", $name);
            $mail->setSubject('Your HCII EASy account');
            $mail->send($this->transport);
            /* TODO Sometimes get Unknown email error, when server responds with email not found. Need to catch this!! Also other errors. Make it robust */
        }
    }
    
    /* Create a mhci/metals/bhci/ugminor/admin user, then redirect back to the original page */
    public function createAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        $session_user = new Zend_Session_Namespace('user');
        if ($session_user->loginType == "administrator" && $this->getRequest()->getMethod() == 'POST') {
            $andrewId = $this->getRequest()->getPost('andrew-id');
            $name = $this->getRequest()->getPost('name');
            $from = $this->getRequest()->getPost('from');
            $type = $this->getRequest()->getPost('type');
            $role = $type == 'admin' ? "administrator" : "student";
            $isFullTime = $this->getRequest()->getPost('is-full-time');
            $enrollDate = $this->getRequest()->getPost('enroll-date');
            $graduationDate = $this->getRequest()->getPost('graduation-date');
            $major = $this->getRequest()->getPost('major');
            $notes = $this->getRequest()->getPost('notes');
            $program = $type == 'admin' ? null : $type;
            $status = $this->getRequest()->getPost('status');

            $receiveFrom = "";

            if ($type == 'admin') {
                $receiveFrom .= $this->getRequest()->getPost('receive-from-mhci') ? "mhci," : "";
                $receiveFrom .= $this->getRequest()->getPost('receive-from-metals') ? "metals," : "";
                $receiveFrom .= $this->getRequest()->getPost('receive-from-bhci') ? "bhci," : "";
                $receiveFrom .= $this->getRequest()->getPost('receive-from-ugminor') ? "ugminor" : "";
                $receiveFrom .= $this->getRequest()->getPost('receive-from-learning-media') ? 'learning-media' : "";
            }
            
            $this->createUser($andrewId, $name, $role, $status, $program, $isFullTime, $enrollDate, $graduationDate, $major,
                              $notes, $receiveFrom);
        }
    }
    
    /**
     * Delete a user. Requires administrative privilege.
     */
    public function removeAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        
        $session_user = new Zend_Session_Namespace('user');
        if ($session_user->loginType == "administrator") {
            $andrewId = $this->getRequest()->getParam('andrewid');
            $program = $this->getRequest()->getParam('program');

            $dbUsers = new Application_Model_DbTable_Users();
            $studentId = $dbUsers->getId($andrewId, $program);
            $dbUsers->deleteById($studentId);

            $dbCourses = new Application_Model_DbTable_Courses();
            $dbCourses->deleteByStudentId($studentId);

            $dbChats = new Application_Model_DbTable_Users();
            $dbChats->deleteByStudentId($studentId);

            $dbForcedValues = new Application_Model_DbTable_ForcedValues();
            $dbForcedValues->deleteByStudentId($studentId);
        }
    }
    
    /**
     * User login
     */
    public function loginAction() {
        $this->view->title = 'EASy';
        /* Obtain user login session variable */
        $session_user = new Zend_Session_Namespace('user');
        
        $db = new Application_Model_DbTable_Users();
        /* If logged in already, redirect to / */
        if ($session_user->loginType == "administrator" ||
            $session_user->loginType == "student") {
            $this->_redirect("/");
        }
        /* Otherwise, attempt to authenticate from Shibboleth credentials */
        else {
            /* If in development environment, auth directly */
            if (defined('APPLICATION_ENV') && getenv('APPLICATION_ENV') == 'development') {
                $user = $db->getUserByAndrewId('chenliu'); /* The magic andrew id */
                $session_user->loginType = $user->role;
                $session_user->andrewId = 'chenliu';
                $session_user->userId = $user->id;
                $this->_redirect('/');
                return;
            }
            $credential = $_SERVER["REMOTE_USER"];
            if (substr($credential, -14) != "andrew.cmu.edu") {
                $this->_redirect("/users/error");
                return;
            }
            /* If is @andrew.cmu.edu credential, continue to check against database */
            else {
                $andrewId = substr($credential, 0, strlen($credential) - 15);
                error_log("Attempting to use $credential to login");
                $usersCount = $db->getUsersCountByAndrewId($andrewId);
                if ($usersCount > 1) {
                    $session_user->andrewId = $andrewId;
                    $this->_redirect("/users/select-program");
                    return;
                }

                $user = $db->getUserByAndrewId($andrewId);

                /* If user not found in database */
                if (!$user) {
                    $this->_redirect("/users/error");
                    return;
                }
                /* Otherwise, start session */
                else {
                    $session_user->loginType = $user->role;
                    $session_user->andrewId = $andrewId;
                    $session_user->userId = $user->id;
                    $this->_redirect("/");
                    return;
                }
            }
        }
    }

    public function selectProgramAction() {
        $session_user = new Zend_Session_Namespace('user');
        if (!isset($session_user->andrewId)) {
            $this->_redirect("/users/error");
            return;
        }
        $db = new Application_Model_DbTable_Users();
        $usersCount = $db->getUsersCountByAndrewId($session_user->andrewId);
        if ($usersCount == 1) {
            $session_user->userId = $db->getUserByAndrewId($session_user->andrewId)->id;
            $this->_redirect("/");
            return;
        }
        $users = $db->getUsersByAndrewId($session_user->andrewId)->toArray();
        $this->view->users = $users;

        $programSelected = $this->getRequest()->getParam("program");
        if ($programSelected != null) {
            //error_log($programSelected);
            $user = $db->getId($session_user->andrewId, $programSelected);
            if (!$user) {
                $this->_redirect("/users/error");
                return;
            }
            $session_user->loginType = $user->role;
            $session_user->userId = $user->id;
            $this->_redirect("/");
        }
    }
    
    public function errorAction() {
        Zend_Session::destroy();
    }

    public function logoutAction() {
        Zend_Session::destroy();
        $this->_redirect("/Shibboleth.sso/Logout", array("prependBase" => false));
    }
    
    /* Print 0 if the user specified by Andrew ID does not exist, 1 otherwise */
    public function userAction() {
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        
        $andrewId = $this->getRequest()->getParam('andrewid');
        $program = $this->getRequest()->getParam('program');
        $db = new Application_Model_DbTable_Users();
        
        echo ($db->getUserByAndrewIdAndProgram($andrewId, $program) != null) ? '1' : '0';
    }

}
