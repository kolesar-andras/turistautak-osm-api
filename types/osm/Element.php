<?php 

/**
 * OSM element
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */
 
class Element {

	private $id;
	private $tags;
	
	function setId ($id) {
		$this->id = $id;
	}

	function getId ($id) {
		return $this->id;
	}
	
	function addTag ($key, $value = null) {
		if ($value == '') {
			unset($this->tags[$key]);
		} else {
			$this->tags[$key] = $value;
		}
	}
	
	function getTags () {
		return $this->tags;
	}
	
}
