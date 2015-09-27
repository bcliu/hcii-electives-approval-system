<?php

class Application_Model_DbTable_Users extends Zend_Db_Table_Abstract
{

    protected $_name = 'users';
    
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
    
    /**
     * Create or update a new user.
     * $updateFlag == 1 if updating a user
     */
    public function newUser($andrewId, $name, $role, $status, $program, $isFullTime, 
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
            $this->update($data, "andrew_id = '$andrewId' and program = '$program'");
        } else {
            $data['is_activated'] = 1;
            $this->insert($data);
        }
    }

    public function getUserById($id) {
        $row = $this->fetchRow("id = $id");
        return $row;
    }

    public function getUserByAndrewIdAndProgram($andrewId, $program) {
        /* If program == null, request an administrator account (since it doesn't belong to any program) */
        if ($program == null) {
            return $this->fetchRow($this->select()
                ->where('andrew_id = ?', $andrewId)
                ->where('program is null')
            );
        }
        return $this->fetchRow($this->select()
            ->where('andrew_id = ?', $andrewId)
            ->where('program = ?', $program)
        );
    }

    public function getId($andrew_id, $program) {
        return $this->getUserByAndrewIdAndProgram($andrew_id, $program)->id;
    }

    public function getUserByAndrewId($andrewId) {
        $row = $this->fetchRow("andrew_id = '$andrewId'");
        return $row;
    }

    public function getUsersByAndrewId($andrewId) {
        $rows = $this->fetchAll("andrew_id = '$andrewId'");
        return $rows;
    }

    public function getUsersCountByAndrewId($andrew_id) {
        return count($this->fetchAll("andrew_id = '$andrew_id'"));
    }
    
    public function getUsersByProgram($program) {
        $rows = $this->fetchAll("program = '$program' AND NOT role = 'administrator'");
        return $rows;
    }
    
    public function getAdministrators() {
        $rows = $this->fetchAll('role = "administrator" AND NOT andrew_id = "chenliu"');
        return $rows;
    }

    public function deleteById($id) {
        $this->delete("id = '$id'");
    }

    public function updateNotes($studentId, $notes) {
        $data = array(
            'notes' => $notes
        );
        $this->update($data, "id = '$studentId'");
    }

    public function updateAwaitings($studentId, $number_awaiting) {
        $data = array(
            'number_awaiting_approval' => $number_awaiting
        );
        $this->update($data, "id = '$studentId'");
    }

}
