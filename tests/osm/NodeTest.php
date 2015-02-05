<?php

class NodeTest extends PHPUnit_Framework_TestCase {

    function testNode () {
    	$node = new Node(47.1234567, 19.8765432); 
        $this->assertEquals('471234567198765432', $node->getPosition());
        return $node;
    }

    function testDifference () {
    	$node1 = new Node(47.1234567, 19.8765432);
    	$node2 = new Node(47.1234567, 19.8765432);
    	$this->assertTrue($node1 == $node2);
    	$this->assertFalse($node1 === $node2);
    	$this->assertEquals($node1, $node2);

		$node1->addTag('id', 1);
		$node2->addTag('id', 2);
		$this->assertFalse($node1 == $node2);

		$clone1 = clone $node1;
		$this->assertTrue($node1 == $clone1);
		$this->assertFalse($node1 === $clone1);

    }

}

