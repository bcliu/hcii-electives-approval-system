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
        $dbCourses = new Application_Model_DbTable_Courses();
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbCourses->getCourseById($course_id)->student_id;
        
        $data = array(
            'message' => $message,
            'course_id' => $course_id,
            'origin' => $origin,
            'student_id' => $studentId,
            'read_by_advisor' => $origin == 'student' ? 0 : 1,
            'read_by_student' => $origin == 'student' ? 1 : 0,
            'time' => $date->toString("YYYY-MM-dd HH:mm:ss")
        );

        $this->insert($data);

        return 0;
    }

    public function getMessages($course_id, $viewer) {
        if ($viewer != 'student' && $viewer != 'advisor') {
            throw new Exception("Unrecognized viewer in getMessages", 1);
        }
        $rows = $this->fetchAll($this->select()
                    ->where("course_id = '$course_id'")
                    ->order('time ASC'));
        $this->markAsRead($rows->toArray(), $viewer);
        return $rows;
    }

    public function hasUnreadMessages($courseId, $origin) {
        if ($origin != 'student' && $origin != 'advisor') {
            throw new Exception("Unrecognized origin", 1);
        }

        $readBy = $origin == 'student' ? 'advisor' : 'student';
        $rows = $this->fetchAll($this->select()
                    ->where("course_id = '$courseId' AND origin = '$origin' AND read_by_$readBy = 0"));

        return count($rows) != 0;
    }

    public function markAsRead($messages, $viewer) {
        if ($viewer != 'student' && $viewer != 'advisor') {
            throw new Exception("Unrecognized viewer in markAsRead", 1);
        }
        foreach ($messages as $message) {
            if ($viewer == 'student') {
                if ($message['read_by_student'] == 1)
                    continue;
                $message['read_by_student'] = 1;
            } else {
                if ($message['read_by_advisor'] == 1)
                    continue;
                $message['read_by_advisor'] = 1;
            }
            $id = $message['chat_id'];
            $this->update($message, "chat_id = $id");
        }
    }

    /**
     * Get array of students with unread messages by advisors
     * @return Array Students with unread messages
     */
    public function getStudentsWithUnread() {
        $rows = $this->fetchAll($this->select("student_id")
            ->distinct()
            ->where("read_by_advisor = 0"));
        return $rows->toArray();
    }

    public function deleteByStudentId($studentId) {
        $this->delete("student_id = '$studentId'");
    }

}