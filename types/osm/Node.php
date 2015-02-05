<?php 

/**
 * OSM node
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */ 

class Node extends Element {

	// az osm szerver 7 tizedesig tárolja a koordinátákat
	// mi is így teszünk; ha ebben az ábrázolásban egyezik,
	// akkor a pontok azonos helyen vannak
	const positionFormat = '%1.7f';

	private $lat;
	private $lon;

	function __construct ($lat=null, $lon=null) {
		$this->setLatLon($lat, $lon);
	}
	
	function setLatLon ($lat, $lon) {
		$this->lat = sprintf(self::positionFormat, $lat);
		$this->lon = sprintf(self::positionFormat, $lon);
	}
	
	function getPosition () {
		$format = sprintf('%1$s%1$s', self::positionFormat);
		$value = sprintf($format, $this->lat, $this->lon);
		return str_replace('.', '', $value);
	}

}
