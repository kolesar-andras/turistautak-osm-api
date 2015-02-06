<?php 

/**
 * turistautak.hu típusok nevének beírása a programkódba
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.03
 *
 */

require_once('../include_general.php');
require_once('../include_arrays.php');
require_once('../poi-type-array.inc.php');

require_once('types/line.php');
require_once('types/polygon.php');

function tr ($str) {
	return iconv('Windows-1250', 'UTF-8', $str);
}

function comment_types () {
	$myself = file_get_contents(__FILE__);
	$myself = preg_replace_callback('/(^\s*)case (0x[0-9a-f]+):\s*$/um', "comment_types_callback", $myself);
	header('Content-type: text/plain; charset=utf-8');
	echo $myself;
	exit;
}

function comment_types_callback ($matches) {
global $poi_types_array;
	if (preg_match('/^0x([0-9a-f]{4,4})$/', $matches[2], $regs)) {
		$code = hexdec($regs[1]);
		if ($code >= 0xa000) {
			// poi
			$typename = tr($poi_types_array[$code]['nev']);
		} else {
			// vonal
			$typename = line_type($code);
		}

	} else if (preg_match('/^0x([0-9a-f]{2,2})$/', $matches[2], $regs)) {
		// felület
		$code = hexdec($regs[1]);
		$typename = polygon_type($code);
	}		
			
	return sprintf('%scase %s: // %s', $matches[1], $matches[2], $typename);
}

