<?php

/**
 * turistautak.hu vonal
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

require_once('type.php');

class Line extends Type {

	static function getTypeArray () {
		return array(
			0x0000 => 'nullás kód, általában elfelejtett típus',
			0x0081 => 'csapás',
			0x0082 => 'ösvény',
			0x0083 => 'gyalogút',
			0x0084 => 'szekérút',
			0x0085 => 'földút',
			0x0086 => 'burkolatlan utca',
			0x0087 => 'makadámút',
			0x0091 => 'burkolt gyalogút',
			0x0092 => 'kerékpárút',
			0x0093 => 'utca',
			0x0094 => 'kiemelt utca',
			0x0095 => 'országút',
			0x0096 => 'másodrendű főút',
			0x0097 => 'elsőrendű főút',
			0x0098 => 'autóút',
			0x0099 => 'autópálya',
			0x009a => 'erdei aszfalt',
			0x009b => 'egyéb közút',
			0x00a1 => 'lehajtó',
			0x00a2 => 'körforgalom',
			0x00a3 => 'lépcső',
			0x00a4 => 'kifutópálya',
			0x00b1 => 'folyó',
			0x00b2 => 'patak',
			0x00b3 => 'időszakos patak',
			0x00b4 => 'komp',
			0x00b5 => 'csatorna',
			0x00c1 => 'vasút',
			0x00c2 => 'kisvasút',
			0x00c3 => 'villamos',
			0x00c4 => 'kerítés',
			0x00c5 => 'elektromos vezeték',
			0x00c6 => 'csővezeték',
			0x00c7 => 'kötélpálya',
			0x00d1 => 'felmérendő utak',
			0x00d2 => 'kanyarodás tiltás',
			0x00d3 => 'vízpart',
			0x00d4 => 'völgyvonal',
			0x00d5 => 'megyehatár',
			0x00d6 => 'országhatár',
			0x00d7 => 'alapszintvonal',
			0x00d8 => 'főszintvonal',
			0x00d9 => 'vastag főszintvonal',
			0x00da => 'felező szintvonal',
		);
	}
}
