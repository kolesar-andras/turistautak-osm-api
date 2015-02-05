<?php

class MapTest extends PHPUnit_Framework_TestCase {

	function testNode () {
		$map = new Map;
		$node = NodeTest::testNode();
		$this->assertFalse($map->isNodeAtNode($node), 'nem kellene még lennie');
		$map->addNode($node);
		$this->assertTrue($map->isNodeAtNode($node), 'lennie kellene már');
		$this->assertTrue($map->isNodeAtPosition($node->getPosition()), 'így is lennie kellene');
		$map->removeNode($node);
		$this->assertFalse($map->isNodeAtNode($node), 'nem lenne szabad lennie');
		
		// kipróbáljuk azonos helyre tett két ponttal is
		$clone = clone $node;
		$map->addNode($node);
		$map->addNode($clone);
		$map->removeNode($clone);
		$map->removeNode($node);
		$this->assertFalse($map->isNodeAtNode($node), 'nem lenne szabad lennie');
		
		try {
			$map->addNode($node->getPosition());
			$this->assertTrue(false, 'ide nem lenne szabad eljutnia');
		} catch (Exception $e) {
			$this->assertContains('assert()', $e->getMessage());
		}

		try {
			$map->removeNode($node);
			$this->assertTrue(false, 'ide nem lenne szabad eljutnia');
		} catch (Exception $e) {
			$this->assertEquals('node not found', $e->getMessage());
		}
		
	}
	
	function testWay () {
		$map = new Map;
		$way1 = new Way;
		$node1 = new Node(47.0000001, 19.0000001);
		$way1->addNode($node1);
		$node2 = new Node(47.0000002, 19.0000002);
		$way1->addNode($node2);
		$map->addWay($way1);
		$this->assertTrue($map->isNodeOnMap($node1));
		$this->assertTrue($map->isNodeOnMap($node2));
		
		$way2 = new Way;
		$way2->addNode($node2);
		$node3 = new Node(47.0000003, 19.0000003);
		$way2->addNode($node3);
		$map->addWay($way2);

		// a két út közös pontját egyszer szabad tárolnia
		// ha eltávolítom, akkor nem szabad a térképen maradnia
		// a node-nak semmiképpen, de másolatának sem
		$map->removeNode($node2);
		$this->assertFalse($map->isNodeOnMap($node2));
		$this->assertFalse($map->isNodeAtNode($node2));
		
	}
	
	function testRelation () {
		$map = new Map;
		$relation = new Relation;
		$map->addRelation($relation);
	}
}

