<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.2
 *
 */

session_start();

if (!($_SESSION['isAuthUser'])) {

	echo "Not authorized - please log in";

} else {

	if (file_exists("inc/config.php")) {
		include "inc/config.php";
	}
	include "inc/patch/functions.php";
	include "inc/patch/database.php";

	if (isset($_POST['service']) && isset($conf)) {
		if ($_POST['service'] == "enable") {
			$conf->setSetting("patch", "enabled");
		} else {
			$conf->setSetting("patch", "disabled");
		}
	}

	if (isset($_POST['dashboard']) && isset($conf)) {
		if ($_POST['dashboard'] == "true") {
			$conf->setSetting("showpatch", "true");
		} else {
			$conf->setSetting("showpatch", "false");
		}
	}

	// Download Backup
	if (isset($_GET['download'])) {
		$backup = $kinobi->getSetting("backup");
		$filename = $_GET['download'];
		if (file_exists($backup['path'].'/'.$filename)) {
			if (ob_get_level()) ob_end_clean();
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: '.filesize($backup['path'].'/'.$filename));
			ob_clean();
			flush();
			readfile($backup['path'].'/'.$filename);
			exit;
		}
	}

	// Backup Schedule
	if (isset($_POST['schedule'])) {
		exec("crontab -l", $crontab);
		$backup_api = $_SERVER['SERVER_NAME']."/v1.php/backup/".$kinobi->getSetting("uuid");
		foreach ($crontab as $key => $value) {
			if (strpos($value, $backup_api) !== false) {
				unset($crontab[$key]);
			}
		}
		$temp_file = tempnam(sys_get_temp_dir(), "crontab_");
		if ($_POST['schedule'] !== "") {
			$crontab[] = "0 0 * * ".$_POST['schedule']." curl -k '".(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on" ? "http" : "https")."://".$_SERVER['SERVER_NAME']."/v1.php/backup/".$kinobi->getSetting("uuid")."' > /dev/null 2>&1";
		}
		$crontab[] = "";
		file_put_contents($temp_file, implode(PHP_EOL, $crontab));
		exec("crontab ".$temp_file);
		unlink($temp_file);
	}

	// Backup Retention
	if (isset($_POST['retention'])) {
		$backup = $kinobi->getSetting("backup");
		$backup['retention'] = $_POST['retention'];
		$kinobi->setSetting("backup", $backup);
	}

	// Web Admin Access
	if (isset($_POST['allow_web'])) {
		$users = $kinobi->getSetting("users");
		$users[$_POST['allow_web']]['web'] = true;
		$kinobi->setSetting("users", $users);
	}

	if (isset($_POST['deny_web'])) {
		$users = $kinobi->getSetting("users");
		unset($users[$_POST['deny_web']]['web']);
		$kinobi->setSetting("users", $users);
	}

	// API Read/Write Access
	if (isset($_POST['allow_api'])) {
		$users = $kinobi->getSetting("users");
		$users[$_POST['allow_api']]['api'] = "0";
		$kinobi->setSetting("users", $users);
	}

	if (isset($_POST['allow_api_rw'])) {
		$users = $kinobi->getSetting("users");
		$users[$_POST['allow_api_rw']]['api'] = "1";
		$kinobi->setSetting("users", $users);
	}

	if (isset($_POST['deny_api'])) {
		$users = $kinobi->getSetting("users");
		unset($users[$_POST['deny_api']]['api']);
		$kinobi->setSetting("users", $users);
	}

	// API Authentication Type
	if (isset($_POST['api_authtype'])) {
		$api = $kinobi->getSetting("api");
		$api['authtype'] = $_POST['api_authtype'];
		$kinobi->setSetting("api", $api);
	}

	// Require API Authentication
	if (isset($_POST['api_reqauth'])) {
		$api = $kinobi->getSetting("api");
		if ($_POST['api_reqauth'] == "true") {
			$api['reqauth'] = true;
		} else {
			$api['reqauth'] = false;
		}
		$kinobi->setSetting("api", $api);
	}

	// API Auto-Enable
	if (isset($_POST['api_auto'])) {
		$api = $kinobi->getSetting("api");
		if ($_POST['api_auto'] == "true") {
			$api['auto'] = true;
		} else {
			$api['auto'] = false;
		}
		$kinobi->setSetting("api", $api);
	}

	if (isset($_POST['subs_refresh'])) {
		$subs = $kinobi->getSetting("subscription");
		$subs['refresh'] = $_POST['subs_refresh'];
		$kinobi->setSetting("subscription", $subs);
	}

	// Enable / Disable Software Title
	if ($pdo && isset($_GET['title_id']) && isset($_POST['title_enabled'])) {
		$title_enabled = ($_POST['title_enabled'] === "true") ? "1" : "0";
		$stmt = $pdo->prepare('UPDATE titles SET enabled = ? WHERE id = ?');
		$stmt->execute(array($title_enabled, $_GET['title_id']));
	}

	// Update Title Modified
	if ($pdo && isset($_GET['title_id']) && isset($_POST['title_modified'])) {
		$title_modified = time();
		$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
		$stmt->execute(array($title_modified, $_GET['title_id']));
	}

	// Enable/Disable Patch
	if ($pdo && isset($_GET['patch_id']) && isset($_POST['patch_enabled'])) {
		$patch_enabled = ($_POST['patch_enabled'] === "true") ? "1" : "0";
		$stmt = $pdo->prepare('UPDATE patches SET enabled = ? WHERE id = ?');
		$stmt->execute(array($patch_enabled, $_GET['patch_id']));
	}

	// Update Field w/Table
	if ($pdo && isset($_GET['table']) && isset($_GET['field']) && isset($_POST['value']) && isset($_GET['id'])) {
		$stmt = $pdo->prepare('UPDATE '.$_GET['table'].' SET '.$_GET['field'].' = ? WHERE id = ?');
		$stmt->execute(array($_POST['value'], $_GET['id']));
	}

	// Update Patch Released
	if ($pdo && isset($_GET['patch_id']) && isset($_POST['patch_released'])) {
		$patch_released = date("U",strtotime($_POST['patch_released']));
		$stmt = $pdo->prepare('UPDATE patches SET released = ? WHERE id = ?');
		$stmt->execute(array($patch_released, $_GET['patch_id']));
	}

}

?>