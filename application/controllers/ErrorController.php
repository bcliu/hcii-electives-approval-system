<?php

class ErrorController extends Zend_Controller_Action
{

    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');
        
        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
        
                // 404 error -- controller or action not found
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->message = 'Page not found';
                break;
            default:
                // application error
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->message = 'Application error';
                break;
        }
        
        // Log exception, if logger available
        if ($log = $this->getLog()) {
            $log->crit($this->view->message, $errors->exception);
        }
        
        // conditionally display exceptions
        if ($this->getInvokeArg('displayExceptions') == true) {
            $this->view->exception = $errors->exception;
        }
        
        $this->view->request = $errors->request;
	error_log($errors->exception->getMessage() . "\n" . $errors->exception->getTraceAsString());

        /* Send error report to the awesome programmer! */
        if (Zend_Registry::get('EmailEnabled')) {
            try {
                $mail = new Zend_Mail();
                $this->config = array(
                    'auth' => 'login',
                    'username' => Zend_Registry::get('EasyMailUserName'),
                    'password' => Zend_Registry::get('AndrewPassword'),
                    'ssl' => 'tls',
                    'port' => Zend_Registry::get('EasyMailPort')
                );
                $this->transport = new Zend_Mail_Transport_Smtp(Zend_Registry::get('EasyMailSmtp'), $this->config);
                $mail->setBodyHtml("<html><body>New error report: <h3>Exception information:</h3><p>
                    <b>Message:</b>" . $errors->exception->getMessage() . "</p><h3>Stack trace:</h3>
                    <pre>" . $errors->exception->getTraceAsString() . "</pre><h3>Request Parameters:</h3>
                    <pre>" . var_export($this->view->request->getParams(), true) . "</pre></body></html>");
                $mail->setFrom(Zend_Registry::get('EasyMailFullAddress'), 'HCII EASy');
                $mail->addTo("chenliu@andrew.cmu.edu");
                $mail->setSubject("EASy error report");
                $mail->send($this->transport);
            } catch (Exception $e) {
                /* Do nothing */
            }
        }
    }

    public function getLog()
    {
        $bootstrap = $this->getInvokeArg('bootstrap');
        if (!$bootstrap->hasPluginResource('Log')) {
            return false;
        }
        $log = $bootstrap->getResource('Log');
        return $log;
    }


}

