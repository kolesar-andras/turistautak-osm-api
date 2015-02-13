<?php 

/**
 * letöltések szűrése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

function bbox ($bounding, $filter) {

	// bounding box
	if ($bounding == '') {
		if (!$filter) throw new Exception('no bbox');
		$bbox = null;
	
	} else {
		$bbox = explode(',', $bounding);
		if (count($bbox) != 4) throw new Exception('invalid bbox syntax');
		if ($bbox[0]>=$bbox[2] || $bbox[1]>=$bbox[3]) throw new Exception('invalid bbox');
		foreach ($bbox as $coord) if (!is_numeric($coord)) throw new Exception('invalid bbox');

		$area = ($bbox[2]-$bbox[0])*($bbox[3]-$bbox[1]);
		if (!$filter && $area>0.25) throw new Exception('bbox too large');
	}
	
	return $bbox;

}

function typeFilter ($types, $names) {

	// előkészítjük a szűrőfeltételt
	if (!is_array($types)) $types = array($types);

	// értelmezzük a szűréseket
	$codes = array();
	foreach ($types as $type) {
		if (is_numeric($type)) {
			$codes[] = $type;
		} else if (preg_match('/^0x([0-9a-f]+)$/i', $type, $regs)) {
			$codes[] = hexdec($regs[1]);
		} else {
			$code = array_search(deaccent($type), deaccent($names));
			if ($code !== false && is_numeric($code)) $codes[] = $code;
		}
	}
	
	return $codes;
}

function deaccent ($string) {
	if (is_array($string)) {
		foreach ($string as $k=>$v) {
			$out[$k] = deaccent($v);
		}
		return $out;
	} else {
		return str_replace(
			array('á', 'é', 'í', 'ó', 'ú', 'ö', 'ő', 'ü', 'ű',
				  'Á', 'É', 'Í', 'Ó', 'Ú', 'Ö', 'Ő', 'Ü', 'Ű'),
			array('a', 'e', 'i', 'o', 'u', 'o', 'o', 'u', 'u',
				  'A', 'E', 'I', 'O', 'U', 'O', 'O', 'U', 'U'),
			$string);
	}
}
