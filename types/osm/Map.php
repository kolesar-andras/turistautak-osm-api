<?php 

/**
 * térképi állomány
 *
 * osm objektumok: node, way, relation
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */
 
class Map {

	var $nodes = array();
	var $ways = array();
	var $relations = array();
		
	function addNode ($node) {
		assert(is_a($node, 'Node'));
		if (!$this->isNodeOnMap($node)) {
			$position = $node->getPosition();
			@$this->nodes[$position][] = $node;
		}
	}

	function removeNode ($node) {
		assert(is_a($node, 'Node'));
		$position = $node->getPosition();
		if (!is_array(@$this->nodes[$position])) throw new Exception('node not found');
		$index = $this->nodeIndex($node, $this->nodes[$position]);
		if ($index === false) throw new Exception('node not found');
		unset($this->nodes[$position][$index]);
		if (count($this->nodes[$position]) == 0)
			unset($this->nodes[$position]);
	}

	private function nodeIndex ($node, $nodes) {
		if (!is_array($nodes)) {
			return false;
		} else {
			$index = array_search($node, $nodes, true);
		}
		return $index;
	}
	
	function addWay ($way) {
		assert(is_a($way, 'Way'));
		$this->ways[] = $way;
		
		foreach ($way->getNodes() as $node) {
			$this->addNode($node);
		}
	}
	
	function addRelation ($relation) {
		assert(is_a($relation, 'Relation'));
		$this->ways[] = $relation;
	}
	
	function getNodesAtPosition ($position) {
		return @$this->nodes[$position];
	}
	
	function isNodeAtPosition ($position) {
		return isset($this->nodes[$position]);
	}

	function isNodeAtNode ($node) {
		assert(is_a($node, 'Node'));
		return isset($this->nodes[$node->getPosition()]);
	}

	function isNodeOnMap ($node) {
		assert(is_a($node, 'Node'));
		$position = $node->getPosition();
		$nodes = $this->getNodesAtPosition($position);
		$index = $this->nodeIndex($node, $nodes);
		return $index === false ? false : true;
	}

}
