<?php

class Application_Model_DbTable_Programs extends Zend_Db_Table_Abstract
{

    protected $_name = 'programs';
    
    public function getRequirementsByProgram($program) {
        $rows = $this->fetchAll($this->select()
                    ->where("program = '$program'")
                    ->order('id ASC')
                );
        return $rows;
    }

    public function getReqsByProgramSemester($program, $semester, $year) {
        $rows = $this->fetchAll($this->select()
                    ->where("program = '$program' AND semester = '$semester' AND year = '$year'")
                    ->order('id ASC')
                );
        return $rows;
    }

    /**
     * Remove all courses of program, semester and year specified, 
     * then add the new courses.
     */
    public function updateReqsByProgramSemester($year, $semester, $program, $courses) {
    	$this->delete("program = '$program' AND year = '$year' AND semester = '$semester'");

    	foreach ($courses as $course) {
    		$data = array(
	            'program' => $program,
	            'year' => $year,
	            'semester' => $semester,
	            'course_name' => $course['course_name'],
	            'course_numbers' => $course['course_numbers'],
	            'type' => $course['type'],
                'number' => $course['number']
        	);

            $this->insert($data);
    	}
    }

    public function removeSemester($semester, $year) {
        $this->delete("semester = '$semester' AND year = '$year'");
    }

    public function getNumberByType($year, $semester, $program, $type) {
        $cores = $this->fetchAll("program = '$program' AND year = '$year' AND semester = '$semester' AND type = '$type'");
        return count($cores->toArray());
    }

    public function getNumberOfElectivesByProgram($year, $semester, $program, $type) {
        $number = $this->fetchRow("program = '$program' AND year = '$year' AND semester = '$semester' AND type = '$type'");

        if (!$number) {
            return -1;
        }
        return $number->number;
    }
}