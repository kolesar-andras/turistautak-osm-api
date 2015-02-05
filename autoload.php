<?php

/**
 * osztályok automatikus betöltése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */
 
function autoload ($name) {
	$filename = sprintf('%s.php', $name);
	$directories = array(
		'',
		'osm/',
	);
	
	foreach ($directories as $directory) {
		@include_once('types/' . $directory . $filename);
		@include_once('tests/' . $directory . $filename);
	}
}

spl_autoload_register('autoload');
