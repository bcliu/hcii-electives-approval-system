<?php

class Application_Model_DbTable_ForcedValues extends Zend_Db_Table_Abstract
{

    protected $_name = 'forced_values';
    
    public function getValue($studentId, $type, $key) {
        $row = $this->fetchRow("student_id = '$studentId' AND type = '$type' AND key = '$key'");
        return $row;
    }

    public function getValuesOfUser($studentId) {
        return $this->fetchAll("student_id = '$student_id'");
    }

    public function updateValue($studentId, $type, $key, $value, $notes) {
        $data = array(
            'student_id' => $studentId,
            'type' => $type,
            'key' => $key,
            'value' => $value,
            'notes' => $notes
        );

        $where = array(
            "`student_id` = ?" => $studentId,
            "`type` = ?" => $type,
            "`key` = ?" => $key
        )

        $this->update($data, $where);
    }

    public function deleteByStudentId($studentId) {
        $this->delete("student_id = '$studentId'");
    }

}