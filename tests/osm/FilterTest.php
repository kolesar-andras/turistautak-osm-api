<?php

require_once(dirname(__FILE__) . '/../../turistautak.hu/filter.php');

class FilterTest extends PHPUnit_Framework_TestCase {

	function testDeaccent () {
		
		$this->assertEquals('arvizturo tukorfurogep',
				   deaccent('árvíztűrő tükörfúrógép'));

		$this->assertEquals('ARVIZTURO TUKORFUROGEP',
				   deaccent('ÁRVÍZTŰRŐ TÜKÖRFÚRÓGÉP'));
				   
		$this->assertEquals(['a', 'e'],
				   deaccent(['á', 'é']));

	}

}

