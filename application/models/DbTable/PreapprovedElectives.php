<?php

class Application_Model_DbTable_PreapprovedElectives extends Zend_Db_Table_Abstract
{

    protected $_name = 'preapproved_electives';
	
	public function add($courseNumber, $courseName, $program) {
		$data = array(
			'course_number' => $courseNumber,
			'course_name' => $courseName,
			'program' => $program
		);
		$this->insert($data);
	}
	
	public function deleteElective($courseNumber, $program) {
		$this->delete(array(
			'course_number = ?' => $courseNumber,
			'program = ?' => $program)
		);
	}
	
	public function getAll($program) {
		return $this->fetchAll(array('program = ?' => $program));
	}
}