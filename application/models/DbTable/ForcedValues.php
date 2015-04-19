<?php

class Application_Model_DbTable_ForcedValues extends Zend_Db_Table_Abstract
{

    protected $_name = 'forced_values';
    
    public function getValue($studentId, $type, $key) {
        $dbUsers = new Application_Model_DbTable_Users();
        $row = $this->fetchRow("student_id = '$studentId' AND type = '$type' AND key = '$key'");
        return $row;
    }

    public function getValuesOfUser($studentId) {
        $dbUsers = new Application_Model_DbTable_Users();
        return $this->fetchAll("student_id = '$student_id'");
    }

    public function updateValue($andrew_id, $type, $key, $value, $notes) {
        $this->removeEntry($andrew_id, $type, $key);
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrew_id);

    	$data = array(
		      'student_id' => $studentId,
		      'type' => $type,
		      'key' => $key,
		      'value' => $value,
		      'notes' => $notes
        );

        $this->insert($data);
    }

    public function removeEntry($andrew_id, $type, $key) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrew_id);

        $this->delete(array(
			    "`student_id` = ?" => $studentId,
			    "`type` = ?" => $type,
			    "`key` = ?" => $key
        ));
    }

    public function getNumSatisfied($andrew_id, $type) {
        $dbUsers = new Application_Model_DbTable_Users();
        $studentId = $dbUsers->getIdByAndrewId($andrew_id);

        $rows = $this->fetchAll("student_id = '$studentId' AND type = '$type' AND value = 'satisfied'");
        return count($rows);
    }

}