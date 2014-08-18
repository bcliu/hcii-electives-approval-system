<?php 

class Zend_View_Helper_ProduceList extends Zend_View_Helper_Abstract {

    public function produceList($arr) {
        $result = '';
        foreach ($arr as $value) {
            $result .= "<li><a href='javascript: ;'>$value</a></li>";
        }
        return $result;
    }
}