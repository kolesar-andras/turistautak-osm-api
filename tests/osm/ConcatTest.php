<?php

require_once('../../turistautak.hu/concat.php');

class ConcatTest extends PHPUnit_Framework_TestCase {

	function testMergeConcatTags () {
		
		// egyforma értékek
		$one = array('Utcanev' => 'Kossuth Lajos utca');
		$two = array('Utcanev' => 'Kossuth Lajos utca');
		$this->assertEquals('Kossuth Lajos utca', mergeConcatTags($one, $two)['Utcanev']);

		// az egyik üres		
		$two = array();
		$this->assertEquals('Kossuth Lajos utca, N/A', mergeConcatTags($one, $two)['Utcanev']);
		
		// különbözőek
		$two = array('Utcanev' => 'Petőfi Sándor utca');
		$this->assertEquals('Kossuth Lajos utca, Petőfi Sándor utca', mergeConcatTags($one, $two)['Utcanev']);

		// sorrendiség
		$one = array('Utcanev' => 'A, B');
		$two = array('Utcanev' => 'C, D');
		$this->assertEquals('A, B, C, D', mergeConcatTags($one, $two)['Utcanev']);
		$this->assertEquals('C, D, A, B', mergeConcatTags($two, $one)['Utcanev']);
		$this->assertEquals('B, A, C, D', mergeConcatTags($one, $two, true)['Utcanev']);
		$this->assertEquals('A, B, D, C', mergeConcatTags($one, $two, null, true)['Utcanev']);
		$this->assertEquals('B, A, D, C', mergeConcatTags($one, $two, true, true)['Utcanev']);

		// tartományok
		$one = array('Modositva' => '2015-02-01 12:34:56');
		$two = array('Modositva' => '2015-02-01 12:34:55');
		$this->assertEquals('2015-02-01 12:34:55 ... 2015-02-01 12:34:56', mergeConcatTags($one, $two)['Modositva']);

		$two = array();
		$this->assertEquals('2015-02-01 12:34:56', mergeConcatTags($one, $two)['Modositva']);
		$this->assertEquals('2015-02-01 12:34:56', mergeConcatTags($two, $one)['Modositva']);
				
	}

}

