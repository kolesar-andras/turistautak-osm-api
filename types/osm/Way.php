<?php 

/**
 * OSM way
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */ 

class Way extends Element {
	
	private $nodes = array();

	function getNodes () {
		return $this->nodes;
	} 

	function addNode ($node) {
		assert(is_a($node, 'Node'));
		$this->nodes[] = $node;
	}

}
