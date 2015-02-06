<?php 

/**
 * utak összefűzése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */

require_once('turistautak.hu/concat.php');

function concat_ways (&$ways) {

	// megnézzük, hogy a végpontokon mennyi azonos tulajdonságú vonal van
	$common = array();
	foreach ($ways as $id => $way) {
		$concatHash = md5(serialize(getConcatTags($way['tags'])));
		$common[$concatHash][$way['endnodes'][0]][] = $id;
		$common[$concatHash][$way['endnodes'][1]][] = $id;
	}

	$counter = 0;
	foreach ($common as $hash => $group) {

		// menet közben írjuk a $group tömböt, ezért élőben kell kiolvasnunk
		foreach (array_keys($group) as $node) try {

			$ids = $group[$node];
			$count = count($ids);
			if ($count < 2) {
				continue; // nincs mit fűznünk
			} else if ($count == 2) {
				$todo = array($ids); // ezt a kettőt fűzzük
			} else {
				$angle = array();
				foreach ($ids as $id) {
					$way = $ways[$id];
					$angle[$id] = getWayAngle($way, $node);
				}
				$turns = array();
				$turnids = array();
				for ($i = 0; $i < $count-1; $i++) {
					for ($j = $i+1; $j < $count; $j++) {
						$turn = 180 + $angle[$ids[$j]] - $angle[$ids[$i]];
						while ($turn < -180) $turn += 360;
						while ($turn > 180) $turn -= 360;
						$turn = abs($turn);
						$turns[] = $turn;
						$turnids[] = array($ids[$i], $ids[$j]);
					}
				}
				asort($turns);
				$todo = array();
				$volt = array();
				foreach (array_keys($turns) as $key) {
					if (isset($volt[$turnids[$key][0]])) continue;
					if (isset($volt[$turnids[$key][1]])) continue;
					$todo[] = $turnids[$key];
					$volt[$turnids[$key][0]] = true;
					$volt[$turnids[$key][1]] = true;
				}
			}
			
			foreach ($todo as $ids) {	
		
				// ellenőrizzük, nem csináltunk-e előzőleg butaságot
				if (isset($ways[$ids[0]]['deleted'])) throw new Exception('nincs 0: ' . $ways[$ids[0]]['tags']['ID']);
				if (isset($ways[$ids[1]]['deleted'])) throw new Exception('nincs 1: ' . $ways[$ids[1]]['tags']['ID']);

				// megnézzük, mely végén illeszkedik az új kapcsolat
				if ($ways[$ids[1]]['endnodes'][0] == $node) {
					$nodes = $ways[$ids[1]]['nd'];
					$endnode = $ways[$ids[1]]['endnodes'][1];
					$reverse = false;
				} else if ($ways[$ids[1]]['endnodes'][1] == $node) {
					$nodes = array_reverse($ways[$ids[1]]['nd']);
					$endnode = $ways[$ids[1]]['endnodes'][0];
					$reverse = true;
				} else {
					throw new Exception('nem az van a másik kapcsolat végén, amit vártunk');
				}
	
				// önmagába záródik, nem szeretnénk szívni miatta
				if ($endnode == $node) continue;
	
				if (!is_array($nodes)) {
					throw new Exception('a nodes nem tömb');
				}

				if (!is_array($ways[$ids[0]]['nd'])) {
					throw new Exception('a tagok nem tömb');
				}

				// megnézzük, hogy a megmaradó melyik végéhez illeszkedik
				if ($ways[$ids[0]]['endnodes'][0] == $node) {
					$ways[$ids[0]]['nd'] = array_merge(array_reverse($nodes), array_slice($ways[$ids[0]]['nd'], 1));
					$ways[$ids[0]]['endnodes'][0] = $endnode;
					$ways[$ids[0]]['tags'] = mergeConcatTags($ways[$ids[1]]['tags'], $ways[$ids[0]]['tags'], !$reverse, false);

				} else if ($ways[$ids[0]]['endnodes'][1] == $node) {
					$ways[$ids[0]]['nd'] = array_merge($ways[$ids[0]]['nd'], array_slice($nodes, 1));
					$ways[$ids[0]]['endnodes'][1] = $endnode;
					$ways[$ids[0]]['tags'] = mergeConcatTags($ways[$ids[0]]['tags'], $ways[$ids[1]]['tags'], false, $reverse);

				} else {
					throw new Exception('nem az van az aktuális kapcsolat végén, amit vártunk');
				}

				// kicseréljük a megszűnő vonal hivatkozását a túlsó végen a megmaradóra
				$index = array_search($ids[1], $group[$endnode]);
				if ($index === false) throw new Exception('nincs meg a megszűnő vonal hivatkozása a másik végén levő csomópontban');
				$group[$endnode][$index] = $ids[0];

				// megjelöljük töröltként
				$ways[$ids[1]]['deleted'] = true;
	
			} // foreach
			
		} catch (Exception $e) {
			// csendben továbblépünk
			echo '<!-- way concat error: ' . $e->getMessage() . ' -->', "\n";
		}
	}
}
