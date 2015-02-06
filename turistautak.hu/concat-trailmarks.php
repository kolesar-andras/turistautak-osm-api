<?php 

/**
 * jelzett turistautak összefűzése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.01.30
 *
 */

function concat_trailmarks (&$rels) {

	$common = array();
	foreach ($rels as $id => $rel) {
		if (!isset($rel['endnodes'])) continue; // csak a jelzés-kapcsolatok érdekelnek
		$common[$rel['tags']['jel']][$rel['endnodes'][0]][] = $id;
		$common[$rel['tags']['jel']][$rel['endnodes'][1]][] = $id;
	}

	$count = 0;
	foreach ($common as $jel => $group) {

		// menet közben írjuk a $group tömböt, ezért élőben kell kiolvasnunk
		foreach (array_keys($group) as $node) try {
		
			$ids = $group[$node];
			if (count($ids) != 2) continue;

			// ellenőrizzük, nem csináltunk-e előzőleg butaságot
			if (isset($rels[$ids[0]]['deleted'])) throw new Exception('nincs 0');
			if (isset($rels[$ids[1]]['deleted'])) throw new Exception('nincs 1');

			$bal = refs($rels[$ids[0]]['members']);
			$jobb = refs($rels[$ids[1]]['members']);
	
			// megnézzük, mely végén illeszkedik az új kapcsolat
			if ($rels[$ids[1]]['endnodes'][0] == $node) {
				$members = $rels[$ids[1]]['members'];
				$endnode = $rels[$ids[1]]['endnodes'][1];
			} else if ($rels[$ids[1]]['endnodes'][1] == $node) {
				$members = array_reverse($rels[$ids[1]]['members']);
				$endnode = $rels[$ids[1]]['endnodes'][0];
			} else {
				throw new Exception('nem az van a másik kapcsolat végén, amit vártunk');
			}
	
			// önmagába záródik, nem szeretnénk szívni miatta
			if ($endnode == $node) continue;
	
			if (!is_array($members)) {
				throw new Exception('a members nem tömb');
			}

			if (!is_array($rels[$ids[0]]['members'])) {
				throw new Exception('a tagok nem tömb');
			}

			// megnézzük, hogy a megmaradó melyik végéhez illeszkedik
			if ($rels[$ids[0]]['endnodes'][0] == $node) {
				$rels[$ids[0]]['members'] = array_merge(array_reverse($members), $rels[$ids[0]]['members']);
				$rels[$ids[0]]['endnodes'][0] = $endnode;
			} else if ($rels[$ids[0]]['endnodes'][1] == $node) {
				$rels[$ids[0]]['members'] = array_merge($rels[$ids[0]]['members'], $members);
				$rels[$ids[0]]['endnodes'][1] = $endnode;
			} else {
				throw new Exception('nem az van az aktuális kapcsolat végén, amit vártunk');
			}

			// kicseréljük a megszűnő kapcsolat hivatkozását a túlsó végen a megmaradóra
			if (count($group[$endnode]) == 2) {
				if ($group[$endnode][0] == $ids[1]) {
					$group[$endnode][0] = $ids[0];
				} else if ($group[$endnode][1] == $ids[1]) {
					$group[$endnode][1] = $ids[0];
				} else {
					throw new Exception('nem az van a csomópontban, amit vártunk');
				}	
			}

			// megjelöljük töröltként
			$rels[$ids[1]]['deleted'] = true;
	
			if (false) {		
				$nodetags[$node][sprintf('illesztés:%d.', $count++)] = sprintf('%s [%s = %s + %s] %s',
					$jel,
					implode(', ', refs($rels[$ids[0]]['members'])),
					implode(', ', $bal),
					implode(', ', $jobb), 
					$endnode);
			}

		} catch (Exception $e) {
			// csendben továbblépünk
			echo '<!-- ' . $e->getMessage . ' -->', "\n";
		}	
	}
}
