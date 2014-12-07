<?php

class Application_Model_DbTable_ForcedValues extends Zend_Db_Table_Abstract
{

    protected $_name = 'forced_values';
    
    public function getValue($andrew_id, $type, $key) {
        $row = $this->fetchRow("user_andrew_id = '$andrew_id' AND type = '$type' AND key = '$key'");
        return $row;
    }

    public function getValuesOfUser($andrew_id) {
        return $this->fetchAll("user_andrew_id = '$andrew_id'");
    }

    public function updateValue($andrew_id, $type, $key, $value, $notes) {
        $this->removeEntry($andrew_id, $type, $key);

    	$data = array(
		      'user_andrew_id' => $andrew_id,
		      'type' => $type,
		      'key' => $key,
		      'value' => $value,
		      'notes' => $notes
        );

        $this->insert($data);
    }

    public function removeEntry($andrew_id, $type, $key) {
        $this->delete(array(
			    "`user_andrew_id` = ?" => $andrew_id,
			    "`type` = ?" => $type,
			    "`key` = ?" => $key
        ));
    }

    public function getNumSatisfied($andrew_id, $type) {
        $rows = $this->fetchAll("user_andrew_id = '$andrew_id' AND type = '$type' AND value = 'satisfied'");
        return count($rows);
    }

}