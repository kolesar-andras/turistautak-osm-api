<?php 

/**
 * turistautak.hu-hoz kapcsolódó dolgok
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

require_once('../include_general.php');

function auth_http () {

	if (date('Y-m-d') < '2015-02-01' && !allow_download($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
		$realm = 'turistautak.hu';
		header('WWW-Authenticate: Basic realm="' . $realm . '"');
		header('HTTP/1.0 401 Unauthorized');
		exit;
	}

}

function allow_download ($user, $password) {

	if ($user == '') return false;
	if ($password == '') return false;

	$cryptpass = substr(crypt(strtolower($password), PASSWORD_SALT), 2);
	$sql_user = "SELECT id, userpasswd, uids, user_ids, allow_turistautak_region_download FROM geocaching.users WHERE member='" . addslashes($user) . "'";

	if (!$myrow_user = mysql_fetch_array(mysql_query($sql_user))) return false;
	if ($myrow_user['userpasswd'] != $cryptpass) return false;
	if ($myrow_user['allow_turistautak_region_download']) return true;
	
	$sql_rights = sprintf("SELECT COUNT(*) FROM regions_explicit WHERE user_id=%d AND allow_region_download=1", $myrow_user['id']);
	if (!simple_query($sql_rights)) return false;
	
	return true;

}

