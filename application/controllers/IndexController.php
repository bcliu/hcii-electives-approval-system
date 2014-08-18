<?php

class IndexController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $this->view->title = 'EASy';
        $session_user = new Zend_Session_Namespace('user');
        
        /* If no valid login data is present, redirect to login page */
        if (($session_user->loginType != 'student' && $session_user->loginType != 'administrator') ||
            !isset($session_user->andrewId)) {
            $this->_redirect("/users/login");
        }
        else if ($session_user->loginType == 'student') {
            $this->_redirect("/student");
        }
        else if ($session_user->loginType == 'administrator') {
            $this->_redirect("/admin");
        }
    }
}