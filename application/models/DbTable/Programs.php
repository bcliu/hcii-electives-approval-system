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

    public function getMinGrade($program, $semester, $year, $type) {
        $row = $this->fetchRow($this->select()
            ->where("program = '$program' AND semester = '$semester' AND year = '$year' AND type = '$type' AND grade_requirement IS NOT NULL"));
        return $row->grade_requirement;
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
                'number' => isset($course['number']) ? $course['number'] : null,
                'grade_requirement' => isset($course['grade_requirement']) ? $course['grade_requirement'] : null
        	);

            $this->insert($data);
    	}
    }

    public function removeSemester($semester, $year, $program) {
        $this->delete("semester = '$semester' AND year = '$year' AND program = '$program'");
    }

    public function getReqsByType($year, $semester, $program, $type) {
        return $this->fetchAll("program = '$program' AND year = '$year' AND semester = '$semester' AND type = '$type' AND grade_requirement IS NULL AND number IS NULL");
    }

    public function getNumberByType($year, $semester, $program, $type) {
        return count($this->getReqsByType($year, $semester, $program, $type));
    }

    public function getNumberOfElectivesByProgram($year, $semester, $program) {
        $number = $this->fetchRow("program = '$program' AND year = '$year' AND semester = '$semester' AND type = 'elective' AND number IS NOT NULL");

        if (!$number) {
            return -1;
        }
        return $number->number;
    }
    
    public function migrateMergingElectives() {
        $data = array(
            'type' => 'elective'
        );
        
        $this->update($data, "type = 'free-elective'");
        $this->delete("type = 'application-elective'");
    }
}