<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.3
 *
 */

session_start();

include "inc/patch/functions.php";

if (!($_SESSION['isAuthUser']) || isset($_POST['setup_token'])) {

	if (!empty($_POST['setup_token']) && $_POST['setup_token'] == $kinobi->getSetting("setup_token")) {
		$subs_resp = fetchJsonArray($_POST['subs_url'], $_POST['subs_token']);
		if (isset($subs_resp['expires'])) {
			echo "true";
		} else {
			echo "false";
		}
	} else {
		echo "Unauthorized";
	}

} else {

	if (file_exists("inc/config.php")) {
		include "inc/config.php";
	}
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

	// Change Password
	if ($pdo && isset($_SESSION['username']) && isset($_POST['current_passwd']) && isset($_POST['new_passwd'])) {
		$current = $pdo->query('SELECT password FROM users WHERE username = "' . $_SESSION['username'] . '"')->fetch(PDO::FETCH_COLUMN);
		if (hash("sha256", $_POST['current_passwd']) == $current) {
			setSettingUser($pdo, $_SESSION['username'], "password", hash("sha256", $_POST['new_passwd']));
			echo "true";
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

	// Enable / Disable User
	if (isset($_POST['disable_user'])) {
		if ($_POST['user_access'] == true) {
			setSettingUser($pdo, $_POST['disable_user'], "disabled", null);
		} else {
			setSettingUser($pdo, $_POST['disable_user'], "disabled", 1);
		}
	}

	// Require API Authentication
	if (isset($_POST['api_reqauth'])) {
		$api = getSettingApi($pdo);
		if ($_POST['api_reqauth'] == "true") {
			$api['reqauth'] = true;
		} else {
			$api['reqauth'] = false;
		}
		setSettingApi($pdo, $api);
	}

	// API Auto-Enable
	if (isset($_POST['api_auto'])) {
		$api = getSettingApi($pdo);
		if ($_POST['api_auto'] == "true") {
			$api['auto'] = true;
		} else {
			$api['auto'] = false;
		}
		setSettingApi($pdo, $api);
	}

	if (isset($_POST['subs_refresh'])) {
		$subs = getSettingSubscription($pdo);
		$subs['refresh'] = $_POST['subs_refresh'];
		setSettingSubscription($pdo, $subs);
	}

	// License Agreement
	if (isset($_POST['eula_accepted'])) {
		if ($_POST['eula_accepted'] == "true") {
			$kinobi->setSetting("eula_accepted", true);
		} else {
			$kinobi->setSetting("eula_accepted", false);
		}
	}

	// Enable / Disable Software Title
	if ($pdo && isset($_GET['title_id']) && isset($_POST['title_enabled'])) {
		$title_enabled = ($_POST['title_enabled'] === "true") ? "1" : "0";
		$stmt = $pdo->prepare('UPDATE titles SET enabled = ? WHERE id = ?');
		$stmt->execute(array($title_enabled, $_GET['title_id']));
	}

	// Update Override
	if ($pdo && isset($_GET['override']) && isset($_POST['current'])) {
		$stmt = $pdo->prepare('DELETE FROM overrides WHERE name_id = ?');
		$stmt->execute(array($_GET['override']));
		if ("" !== $_POST['current']) {
			$stmt = $pdo->prepare('INSERT INTO overrides (name_id, current) VALUES (?, ?)');
			$stmt->execute(array($_GET['override'], $_POST['current']));
			if ($stmt->errorCode() == '00000') {
				echo "true";
			}
		}
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
		if ($stmt->errorCode() == '00000') {
			echo "true";
		}
	}

	// Update Patch Released
	if ($pdo && isset($_GET['patch_id']) && isset($_POST['patch_released'])) {
		$patch_released = date("U",strtotime($_POST['patch_released']));
		$stmt = $pdo->prepare('UPDATE patches SET released = ? WHERE id = ?');
		$stmt->execute(array($patch_released, $_GET['patch_id']));
		if ($stmt->errorCode() == '00000') {
			echo "true";
		}
	}
}

?>