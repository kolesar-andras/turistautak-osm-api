<?php 

/**
 * turistautak.hu utak burkolatának megfeleltetése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

function burkolat ($code) {
	
	$codes = array(
		'aszfalt' => 'asphalt',
		'rossz aszfalt' => 'asphalt',
		'beton' => 'concrete',
		'makadám' => 'compacted',
		'köves' => 'gravel',
		'kavics' => 'pebblestone',
		'homok' => 'sand',
		'föld' => 'dirt',
		'középen füves' => 'grass',
		'fű' => 'grass',

		'térkő' => 'paving_stones',
		'murva' => 'gravel',
		'kockakő' => 'cobblestone',
		'zúzottkő' => 'gravel',
		'sziklás' => 'rock',
		'fa' => 'wood',
		'gumi' => 'tartan',
		'föld k. fű' => 'grass',
		'homok k. fű' => 'grass',
		'fold' => 'dirt',
		'agyag' => 'clay',
		'kisszemcsés-zúzottkő' => 'fine_gravel',
		'kissz. zúzott' => 'fine_gravel',
		'füves' => 'grass',
		'vasbeton útpanel' => 'concrete:plates',
		'kő lépcső' => '',
		'kavics-kő' => 'gravel',
		'kő' => 'gravel',
		'terméskő, kitöltött' => 'gravel',
		'macskakő' => 'cobblestone',
		'kavicsos-köves' => 'gravel',
		'idomkő, beton térkő' => 'paving_stones',
		'Földes' => 'dirt',
		'kőzúzalék' => 'gravel',
		'fém' => 'metal',
		'földes, kavicsos' => 'dirt',
		'rossz beton' => 'concrete',
		'palló' => '',
		'beton lépcső' => 'concrete',
		'utcakő' => 'cobblestone',
		'agyagos homok' => 'sand',
		'sóderos föld' => 'dirt',
		'Földes, középen füve' => '',
		'tönkrement aszfalt' => 'asphalt',

	);
	
	return @$codes[$code];

}

