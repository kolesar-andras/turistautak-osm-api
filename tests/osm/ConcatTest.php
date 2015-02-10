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
		$one = array('KorlatozasSebesseg' => '130');
		$two = array('KorlatozasSebesseg' => '90');
		$this->assertEquals('90 ... 130', mergeConcatTags($one, $two)['KorlatozasSebesseg']);

		$one = array('KorlatozasSebesseg' => 'N/A ... 50');
		$this->assertEquals('N/A ... 90', mergeConcatTags($one, $two)['KorlatozasSebesseg']);
				
	}

}

