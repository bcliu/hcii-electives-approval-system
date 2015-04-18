<?php

class Application_Model_DbTable_Users extends Zend_Db_Table_Abstract
{

    protected $_name = 'users';
    
    public function doesAndrewIdExist($andrewId) {
        $row = $this->fetchRow("andrew_id = '$andrewId'");
        if (!$row) {
            return false;
        }
        return true;
    }
    
    public function getAdvisorsOfProgram($program) {
        $select = $this->select();
        $select->where('receive_from LIKE ? AND role = "administrator"', "%$program%");
        $rows = $this->fetchAll($select);

        if (count($rows->toArray()) == 0) {
            $rows = $this->fetchAll('role = "administrator"');
        }
        
        return $rows;
    }

    public function addAwaitingCount($studentId, $amount) {
        $currentVal = $this->getUserById($studentId)->number_awaiting_approval;
        $data = array(
            'number_awaiting_approval' => $currentVal + $amount
        );
        $this->update($data, "id = '$studentId'");
    }

    public function getNameByAndrewId($andrewId) {
        $row = $this->fetchRow("andrew_id = '$andrewId'");
        
        if (!$row) {
            return null;
        }
        return $row->name;
    }
    
    /**
     * Create or update a new user.
     * $updateFlag == 1 if updating a user
     */
    public function newUser($andrewId, $name, $password, $role, $status, $program, $isFullTime, 
                            $enrollDate, $graduationDate, $major, $notes, $receiveFrom, $updateFlag) {
        $data = array(
            'andrew_id' => $andrewId,
            'name' => $name,
            'role' => $role,
            'status' => $status,
            'program' => $program,
            'is_full_time' => $isFullTime,
            'enroll_date' => $enrollDate,
            'graduation_date' => $graduationDate,
            'major' => $major,
            'notes' => $notes,
            'receive_from' => $receiveFrom
        );

        if ($updateFlag == 1) {
            $this->update($data, "andrew_id = '$andrewId'");
        }
        else {
            $data['password'] = $password;
            $data['is_activated'] = 1;
            $this->insert($data);
        }
    }

    public function getIdByAndrewId($andrewId) {
        $row = $this->fetchRow("andrew_id = '$andrewId'");
        if (!$row) {
            throw new Exception("Andrew ID $andrewId not found");
        }
        return $row->id;
    }

    public function getUserByAndrewId($andrewId) {
        $row = $this->fetchRow("andrew_id = '$andrewId'");
        if (!$row)
            throw new Exception("Andrew ID $andrewId not found");
        return $row;
    }

    public function getUserById($id) {
        $row = $this->fetchRow("id = $id");
        return $row;
    }
    
    public function getUsersByProgram($program) {
        $rows = $this->fetchAll("program = '$program' AND NOT role = 'administrator'");
        return $rows;
    }
    
    public function getAdministrators() {
        $rows = $this->fetchAll('role = "administrator" AND NOT andrew_id = "chenliu"');
        return $rows;
    }
    
    public function deleteByAndrewId($andrewId) {
        $this->delete("andrew_id = '$andrewId'");
    }

    public function setPassword($andrew_id, $password) {
        $data = array(
            'password' => $password,
            'is_activated' => 1
        );
        $this->update($data, "andrew_id = '$andrew_id'");
    }
    
    /**
     * Reset password of specified user to some random password given.
     */
    public function resetPassword($andrew_id, $password) {
        $data = array(
            'password' => $password,
            'is_activated' => 0 /* Set to not activated again */
        );
        $this->update($data, "andrew_id = '$andrew_id'");
    }

    public function updateNotes($andrew_id, $notes) {
        $data = array(
            'notes' => $notes
        );
        $this->update($data, "andrew_id = '$andrew_id'");
    }

    public function updateAwaitings($andrew_id, $number_awaiting) {
        $data = array(
            'number_awaiting_approval' => $number_awaiting
        );
        $this->update($data, "andrew_id = '$andrew_id'");
    }

}
