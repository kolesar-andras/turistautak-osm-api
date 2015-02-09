<?php 

/**
 * jelzett turistautak címkézése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.01.30
 *
 */

function trailmarks (&$ways, &$rels) {

	foreach ($ways as $way) {
		if (isset($way['deleted'])) continue;
		$counter = 0;
		$volt = array();
		
		$tag = trim($way['tags']['Label']);
		if ($tag != '') foreach (explode(' ', $tag) as $jel) {

			$jel = trim($jel);
		
			if (preg_match('/^([KPSZVFE])(.*)$/iu', $jel, $regs)) {
				$szin = $regs[1];
				$forma = $regs[2];
			} else {
				$szin = '';
				$forma = $jel;
			}
		
			$szinek = array(
				'k' => 'blue',
				'p' => 'red',
				's' => 'yellow',
				'z' => 'green',
				'v' => 'purple',
				'f' => 'black',
				'e' => 'gray',
			);

			$formak = array(
				'' => array('bar', ''),
				'+' => array('cross', '+'),
				'3' => array('triangle', '▲'),
				'4' => array('rectangle', '■'),
				'q' => array('dot', '●'),
				'b' => array('arch', 'Ω'),
				'l' => array('L', '▙'),
				'c' => array('circle', '↺'),
				't' => array('T', ':T:'), // ???
			);

			$color = @$szinek[mb_strtolower($szin)];
			$symbol = @$formak[mb_strtolower($forma)];
		
			$name = isset($symbol[1]) ? ($szin . $symbol[1]) : mb_strtoupper($jel);
			$tags = array(
				'jel' => mb_strtolower($jel),
				'name' => $name,
				'network' => $forma == '' ? 'nwn' : 'lwn',
				'route' => 'hiking',
				'type' => 'route',
				'source' => 'turistautak.hu',
			);

			if ($symbol !== null) {
				$face = $symbol[0];
				$tags['osmc:symbol'] = sprintf(
					'%s:white:%s_%s',
					$color, $color, $face
				);
			}
		
			$members = array(
				array(
					'type' => 'way',
					'ref' => $way['attr']['id'],
				)
			);
		
			$attr = array(
				'id' => sprintf('-2%09d%02d', -$way['attr']['id'], $counter++),
			);
		
			$rel = array(
				'attr' => $attr,
				'members' => $members,
				'tags' => $tags,
				'endnodes' => $way['endnodes'],
			);
	
			$rels[] = $rel;

		}

		// külön mező az úton haladó túramozgalmak nevének felsorolása vesszővel
		$tag = @$way['tags']['Turamozgalom'] . ',' . @$way['tags']['K'];
		foreach (explode(',', $tag) as $jel) {
		
			$jel = trim($jel);
			if ($jel == '') continue;
			if (isset($volt[$jel])) continue;
			$volt[$jel] = true;

			$tags = array(
				'name' => $jel,
				'route' => 'hiking',
				'type' => 'route',
				'source' => 'turistautak.hu',
			);

			$members = array(
				array(
					'type' => 'way',
					'ref' => $way['attr']['id'],
				)
			);
		
			$attr = array(
				'id' => sprintf('-2%09d%02d', -$way['attr']['id'], $counter++),
			);
		
			$rel = array(
				'attr' => $attr,
				'members' => $members,
				'tags' => $tags,
				'endnodes' => $way['endnodes'],
			);
	
			$rels[] = $rel;

		}
	}
}
