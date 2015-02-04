<?php

/**
 * turistautak.hu felület
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

require_once('type.php');
 
class Polygon extends Type {

	static function getTypeArray () {
		return array(
			0x81 => 'erdő',
			0x82 => 'fenyves',
			0x83 => 'fiatalos',
			0x84 => 'erdőirtás',
			0x85 => 'bokros',
			0x86 => 'szőlő',
			0x87 => 'gyümölcsös',
			0x88 => 'rét',
			0x89 => 'park',
			0x8a => 'szántó',
			0x80 => 'zöldfelület',
			0x91 => 'tenger',
			0x92 => 'tó',
			0x93 => 'folyó',
			0x94 => 'mocsár',
			0x95 => 'nádas',
			0x96 => 'dagonya',
			0x90 => 'víz',
			0xa1 => 'megyeszékhely',
			0xa2 => 'nagyváros',
			0xa3 => 'kisváros',
			0xa4 => 'nagyközség',
			0xa5 => 'falu',
			0xa6 => 'településrész',
			0xa0 => 'település',
			0xb1 => 'épület',
			0xb2 => 'parkoló',
			0xb3 => 'ipari terület',
			0xb4 => 'bevásárlóközpont',
			0xb5 => 'kifutópálya',
			0xb6 => 'sípálya',
			0xb7 => 'szánkópálya',
			0xb8 => 'golfpálya',
			0xb9 => 'sportpálya',
			0xba => 'temető',
			0xbb => 'katonai terület',
			0xbc => 'pályaudvar',
			0xbd => 'iskola',
			0xbe => 'kórház',
			0xb0 => 'mesterséges terület',
			0xf1 => 'fokozottan védett terület',
			0xf2 => 'háttér',
		);
	}
}
