<?php

class Application_Model_DbTable_ForcedValues extends Zend_Db_Table_Abstract
{

    protected $_name = 'forced_values';
    
    public function getValue($studentId, $type, $key) {
        $row = $this->fetchRow("student_id = '$studentId' AND `type` = '$type' AND `key` = '$key'");
        return $row;
    }

    public function getValuesOfUser($studentId) {
        return $this->fetchAll("student_id = '$studentId'");
    }

    public function updateValue($studentId, $type, $key, $value, $notes) {
        $existing = $this->getValue($studentId, $type, $key);

        $data = array(
            'student_id' => $studentId,
            'type' => $type,
            'key' => $key,
            'value' => $value,
            'notes' => $notes
        );

        if (!$existing) {
            $this->insert($data);
        } else {
            $where = array(
                "`student_id` = ?" => $studentId,
                "`type` = ?" => $type,
                "`key` = ?" => $key
            );

            $this->update($data, $where);
        }
    }

    public function deleteByStudentId($studentId) {
        $this->delete("student_id = '$studentId'");
    }

}