<?php

class Application_Model_DbTable_Chats extends Zend_Db_Table_Abstract
{

    protected $_name = 'chats';
    
    /**
     * Add a message under some course thread
     * @param String $course_id ID of the course thread
     * @param String $message   Message content
     * @param String $origin    Whether this message comes from student or advisor.
     *                          Possible values: "student", "advisor"
     * @return -1   If $origin has illegal value
     *          0   If successful
     */
    public function addMessage($course_id, $message, $origin) {
        if ($origin != "student" && $origin != "advisor") {
            return -1;
        }

        $date = new Zend_Date();
        $data = array(
            'message' => $message,
            'course_id' => $course_id,
            'origin' => $origin,
            'read_by_admin' => $origin == 'student' ? 0 : 1,
            'read_by_student' => $origin == 'student' ? 1 : 0,
            'time' => $date->toString("YYYY-MM-dd HH:mm:ss")
        );

        $this->insert($data);

        return 0;
    }

    public function getMessages($course_id) {
        $rows = $this->fetchAll($this->select()
                    ->where("course_id = '$course_id'")
                    ->order('time ASC')
                );
        return $rows;
    }

}