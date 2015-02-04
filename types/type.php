<?php

/**
 * turistautak.hu általános típus
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */
 
class Type {

	static function getTypeArray () {
		return array();
	}

	static function getNameFromCode ($code) {
		$types = self::getTypeArray();
		return @$types[$code];
	}

}
