<?php

require_once(dirname(__FILE__) . '/../../turistautak.hu/poi.php');

class PoiTest extends PHPUnit_Framework_TestCase {

	function testPoiTagsOSM () {

		$tags = array(); // bírnia kell
		$tags = poiTagsOSM($tags);
		$this->assertEquals('turistautak.hu', $tags['source']);
		
		// bizonytalan elhelyezésű pontok '..' végződéssel
		$tags = array(
			'Code' => 0xab0a,
			'Label' => 'Nagy-Borsog-hegy..',
			'Magassag' => 549,
		);
		$tags = poiTagsOSM($tags);
		$this->assertEquals('Nagy-Borsog-hegy', $tags['name']);
		$this->assertEquals('position', $tags['fixme']);
		$this->assertEquals(549, $tags['ele']);

		$tags = array(
			'Label' => 'Kövesdi erdészlak ..', // id=26963
		);
		$tags = poiTagsOSM($tags);
		$this->assertEquals('Kövesdi erdészlak', $tags['name']);
		$this->assertEquals('position', $tags['fixme']);

		$tags = array(
			'Label' => 'Kövesdi erdészlak .. ', // id=26963 szóközzel megtoldva
		);
		$tags = poiTagsOSM($tags);
		$this->assertEquals('Kövesdi erdészlak', $tags['name']);
		$this->assertEquals('position', $tags['fixme']);
		
		// nem szabad összetéveszteni a '...' végződésűekkel
		$tags = array(
			'Label' => '6. A gémeskutak...', // id=120098
		);
		$tags = poiTagsOSM($tags);
		$this->assertEquals('6. A gémeskutak...', $tags['name']);
		$this->assertNotEquals('position', @$tags['fixme']);

		$tags = array(
			'Label' => 'Alma, Dió, Körte, Szilva, ...', // id=2568
		);
		$tags = poiTagsOSM($tags);
		$this->assertEquals('Alma, Dió, Körte, Szilva, ...', $tags['name']);
		$this->assertNotEquals('position', @$tags['fixme']);

	}

}

