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
                'number' => $course['number'],
                'grade_requirement' => $course['grade_requirement']
        	);

            $this->insert($data);
    	}
    }

    public function removeSemester($semester, $year) {
        $this->delete("semester = '$semester' AND year = '$year'");
    }

    public function getReqsByType($year, $semester, $program, $type) {
        return $this->fetchAll("program = '$program' AND year = '$year' AND semester = '$semester' AND type = '$type' AND grade_requirement IS NULL AND number IS NULL");
    }

    public function getNumberByType($year, $semester, $program, $type) {
        return count($this->getReqsByType($year, $semester, $program, $type));
    }

    /**
     * [getNumberOfElectivesByProgram description]
     * @param  [type] $year     [description]
     * @param  [type] $semester [description]
     * @param  [type] $program  [description]
     * @param  [type] $type     'elective' or 'free-elective' or 'application-elective'
     * @return [type]           [description]
     */
    public function getNumberOfElectivesByProgram($year, $semester, $program, $type) {
        $number = $this->fetchRow("program = '$program' AND year = '$year' AND semester = '$semester' AND type = '$type' AND number IS NOT NULL");

        if (!$number) {
            return -1;
        }
        return $number->number;
    }
}