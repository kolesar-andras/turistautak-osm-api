<?php

/**
 * overpass API formátumú lekérdezések értelmezése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.10.04
 *
 */

// a lekérdezések elején van egy [bbox]; előtag, ezt levesszük
$data = preg_replace('/^\[[^[]+\];/', '', $_GET['data']);
$lines = explode("\n", $data);

foreach ($lines as $line) {
	if (preg_match('/^([^=]+)=?(.*)$/', trim($line), $regs)) {
		$key = urldecode($regs[1]);
		$value = urldecode($regs[2]);
		if (!isset($params[$key])) {
			$params[$key] = $value;
		} else if (is_array($params[$key])) {
			$params[$key][] = $value;
		} else {
			$params[$key] = array($params[$key], $value);
		}
	}
}
unset($params['data']);

require_once('map.php');
