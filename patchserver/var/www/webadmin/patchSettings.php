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

if (file_exists("inc/config.php")) {
	include "inc/config.php";
}
include "inc/auth.php";
if (file_exists("inc/functions.php")) {
	include "inc/functions.php";
}
include "inc/patch/functions.php";

$netsus = (isset($conf) ? (strpos(file_get_contents("inc/header.php"), "NetSUS 4") !== false ? 4 : 5) : 0);

$title = ($netsus > 0 ? "Patch Definitions" : "");

include "inc/header.php";

$backup_error = "";
$backup_success = "";
$delete_error = "";
$delete_success = "";
$upload_error = "";
$upload_success = "";
$restore_error = "";
$restore_success = "";
$api_error = "";
$api_success = "";

function formatSize($size, $precision = 1) {
	$base = log($size, 1024);
	$suffixes = array('B', 'kB', 'MB', 'GB', 'TB');
	if ($size == 0) {
		return "0 B";
	} else {
		return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
	}
}

// Service & Dashboard
$service = (isset($conf) ? $conf->getSetting("patch") == "enabled" : true);
$dashboard = (isset($conf) ? $conf->getSetting("showpatch") != "false" : true);

// Cloud Configuration
$cloud = $kinobi->getSetting("cloud");

// Get Retention or Migrate from Webadmin Config
$retention = (isset($conf) ? $conf->getSetting("retention") : "");
if (!empty($retention)) {
	$backup = $kinobi->getSetting("backup");
	$backup['retention'] = $retention;
	$kinobi->setSetting("backup", $backup);
	$conf->deleteSetting("retention");
}

// Configure Database Connection
if (isset($_POST['dbconnect'])) {
	if ($_POST['dsn_prefix'] == "sqlite") {
		$dbpath = dirname($_POST['dsn_dbpath']);
		if (!is_dir($dbpath) && !@mkdir($dbpath, 0755, true)) {
			$e = error_get_last();
			$pdo_error = str_replace("mkdir()", $dbpath, $e['message']);
		} elseif (!is_writable($dbpath)) {
			$pdo_error = $_POST['dsn_dbpath'].": Permission denied";
		} elseif (is_file($_POST['dsn_dbpath'])) {
			if (!is_readable($_POST['dsn_dbpath']) || is_dir($_POST['dsn_dbpath'])) {
				$pdo_error = "SQLSTATE[HY000] [14] unable to open database file";
			} elseif (!is_writable($_POST['dsn_dbpath'])) {
				$pdo_error = "SQLSTATE[HY000]: General error: 8 attempt to write a readonly database";
			}
		}
	}
	if (empty($pdo_error)) {
		$kinobi->setSetting(
			"pdo",
			array(
				"dsn" => array(
					"prefix" => $_POST['dsn_prefix'],
					"dbpath" => $_POST['dsn_dbpath'],
					"host" => $_POST['dsn_host'],
					"port" => $_POST['dsn_port'],
					"dbname" => $_POST['dsn_dbname']
				),
				"username" => $_POST['dbuser'],
				"passwd" => openssl_encrypt($_POST['dbpass'], "AES-128-CTR", $kinobi->getSetting("uuid"), 0, substr(md5($_POST['dbuser']), 0, 16))
			)
		);
	}
}

$db = $kinobi->getSetting("pdo");

if (!isset($pdo_error)) {
	include "inc/patch/database.php";
}

// Save Backup Path
if (isset($_POST['save_backup_path'])) {
	if (!is_dir($_POST['backup_path']) && !@mkdir($_POST['backup_path'], 0755, true)) {
		$e = error_get_last();
		$restore_error = str_replace("mkdir()", $_POST['backup_path'], $e['message']);
	} elseif (!is_writable($_POST['backup_path'])) {
		$restore_error = $_POST['backup_path'].": Permission denied";
	} else {
		$backup = $kinobi->getSetting("backup");
		$backup['path'] = $_POST['backup_path'];
		$kinobi->setSetting("backup", $backup);
	}
}

// Backup Prefs
$backup = $kinobi->getSetting("backup");

// Backup
if (isset($_POST['backup'])) {
	$timestamp = time();
	$key = $kinobi->getSetting("uuid");
	include_once("inc/patch/mysqldump.php");
	if ($db['dsn']['prefix'] == "mysql") {
		$dbname = $db['dsn']['dbname'];
		if ($_POST['backup_type'] == "mysql") {
			$dump = new Mysqldump(
				$db['dsn']['prefix'].":host=".$db['dsn']['host'].";port=".$db['dsn']['port'].";dbname=".$db['dsn']['dbname'],
				$db['username'],
				openssl_decrypt($db['passwd'], "AES-128-CTR", $key, 0, substr(md5($db['username']), 0, 16)),
				array('compress' => Mysqldump::GZIP, "add-drop-table" => true, 'no-autocommit' => false)
			);
		} else {
			$dump = new Mysqldump(
				$db['dsn']['prefix'].":host=".$db['dsn']['host'].";port=".$db['dsn']['port'].";dbname=".$db['dsn']['dbname'],
				$db['username'],
				openssl_decrypt($db['passwd'], "AES-128-CTR", $key, 0, substr(md5($db['username']), 0, 16)),
				array('compress' => Mysqldump::GZIP, 'no-autocommit' => false, 'sqlite-dump' => true)
			);
		}
	}
	if ($db['dsn']['prefix'] == "sqlite") {
		$dbname = basename($db['dsn']['dbpath']);
		if ($pos = strpos($dbname, ".")) {
			$dbname = substr($dbname, 0, $pos);
		}
		if ($_POST['backup_type'] == "mysql") {
			$dump = new Mysqldump(
				$db['dsn']['prefix'].":".$db['dsn']['dbpath'],
				null,
				null,
				array('compress' => Mysqldump::GZIP, "add-drop-table" => true, 'no-autocommit' => false)
			);
		} else {
			$dump = new Mysqldump(
				$db['dsn']['prefix'].":".$db['dsn']['dbpath'],
				null,
				null,
				array('compress' => Mysqldump::GZIP, 'no-autocommit' => false, 'sqlite-dump' => true)
			);
		}
	}
	$dump->start($backup['path']."/".$dbname."-".$timestamp.".sql.gz");
}

// Delete backup
if (isset($_POST['delete_backup'])) {
	if (unlink($backup['path']."/".$_POST['delete_backup'])) {
		$delete_success = "File deleted successfully.";
	} else {
		$delete_error = "Falied to delete file.";
	}
}

// Upload backup
if (isset($_POST['upload']) && isset($_FILES['upload_file']['name'])) {
	if ($_FILES['upload_file']['error'] > 0) {
		$upload_error = $_FILES['upload_file']['error'].".";
	} elseif ($_FILES['upload_file']['type'] != "application/x-gzip") {
		$upload_error = "Invalid file type '".$_FILES['upload_file']['type']."'.";
	} else {
		// To Do: Add string replace to remove spaces in filename
		$filename = basename($_FILES['upload_file']['name']);
		if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $backup['path']."/".$filename)) {
			$upload_success = "File uploaded successfully.";
		} else {
			$upload_error = "Failed to move file to ".$backup['path'].".";
		}
	}
}

// Prune backups
$backup_files = array();
foreach (glob($backup['path']."/*.sql.gz") as $file) {
	$stat = stat($file);
	$backup_files[$stat['mtime']] = basename($file);
}
krsort($backup_files, SORT_NUMERIC);
foreach (array_values($backup_files) as $key => $value) {
	if ($key >= $backup['retention']) {
		unlink($backup['path']."/".$value);
	}
}

// Restore
if (isset($_POST['restore'])) {
	if ($db['dsn']['prefix'] == "sqlite") {
		$sql = "DROP TABLE IF EXISTS criteria;".PHP_EOL.
			"DROP TABLE IF EXISTS dependencies;".PHP_EOL.
			"DROP TABLE IF EXISTS capabilities;".PHP_EOL.
			"DROP TABLE IF EXISTS components;".PHP_EOL.
			"DROP TABLE IF EXISTS kill_apps;".PHP_EOL.
			"DROP TABLE IF EXISTS ext_attrs;".PHP_EOL.
			"DROP TABLE IF EXISTS patches;".PHP_EOL.
			"DROP TABLE IF EXISTS requirements;".PHP_EOL.
			"DROP TABLE IF EXISTS titles;".PHP_EOL.
			"DROP TABLE IF EXISTS users;".PHP_EOL.
			"DROP TABLE IF EXISTS subscription;".PHP_EOL.
			"DROP TABLE IF EXISTS api;";
		$pdo->exec($sql);
	}
	$sql = gzfile($backup['path']."/".$_POST['restore']);
	$sql = implode($sql);
	try {
		$pdo->exec($sql);
	} catch(PDOException $e) {
		$restore_error = $e->getMessage();
	}
	if (empty($restore_error)) {
		$restore_success = "Restored '".basename($_POST['restore'])."'.";
		$restore_success .= ($netsus == 0 ? " Log out for changes to take effect." : "");
	}
}

// Subscription
if (isset($_POST['subscribe'])) {
	$subs = getSettingSubscription($pdo);
	$subs['url'] = (empty($_POST['subs_url']) ? null : $_POST['subs_url']);
	$subs['token'] = (empty($_POST['subs_token']) ? null : $_POST['subs_token']);
	setSettingSubscription($pdo, $subs);
}

// Create User
if (isset($_POST['create_user'])) {
	createUser($pdo, $_POST['add_user'], hash("sha256", $_POST['add_pass']));
	if (isset($_POST['add_token'])) {
		setSettingUser($pdo, $_POST['add_user'], "token", bin2hex(openssl_random_pseudo_bytes(16)));
	}
	if (empty($_POST['add_expires'])) {
		setSettingUser($pdo, $_POST['add_user'], "expires", null);
	} else {
		setSettingUser($pdo, $_POST['add_user'], "expires", (int)date("U",strtotime($_POST['add_expires'])));
	}
}

// Delete  User
if (isset($_POST['delete_user'])) {
	deleteUser($pdo, $_POST['delete_user']);
}

// Reset Password
if (isset($_POST['save_pass'])) {
	setSettingUser($pdo, $_POST['save_pass'], "password", hash("sha256", $_POST['reset_pass']));
}

// Create Token
if (isset($_POST['create_token']) && !empty($_POST['create_token'])) {
	setSettingUser($pdo, $_POST['create_token'], "token", bin2hex(openssl_random_pseudo_bytes(16)));
}

// Reset Expiry
if (isset($_POST['reset_expiry'])) {
	if (empty($_POST['reset_expires'])) {
		setSettingUser($pdo, $_POST['reset_expiry'], "expires", null);
	} else {
		setSettingUser($pdo, $_POST['reset_expiry'], "expires", (int)date("U",strtotime($_POST['reset_expires'])));
	}
}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

// SQLite Databases
$sqlite_dir = dirname($_SERVER['DOCUMENT_ROOT']) . "/kinobi/db/";
$sqlite_dbs = array();
foreach (glob($sqlite_dir . "*") as $sqlite_db) {
	array_push($sqlite_dbs, basename($sqlite_db));
}

// Backups
$backups = array();
foreach (glob($backup['path'] . "/*.sql.gz") as $value) {
	$sql = implode(gzfile($value));
	$stat = stat($value);
	$backups[] = array(
		"file" => basename($value),
		"path" => $value,
		"date" => $stat['mtime'],
		"size" => filesize($value),
		"type" => (false === ($pos = strpos($sql, "AUTO_INCREMENT")) ? false === ($pos = strpos($sql, "AUTOINCREMENT")) ? "Unknown" : "SQLite" : "MySQL")
	);
}

// Get Backup Schedule
exec("crontab -l 2>/dev/null", $crontab);
$backup_api = $_SERVER['SERVER_NAME']."/v1.php/backup/".$kinobi->getSetting("uuid");
foreach ($crontab as $entry) {
	if (strpos($entry, $backup_api) !== false) {
		$entry = explode(" ", $entry);
		$schedule_str = $entry[4];
		break;
	}
}
if (isset($schedule_str)) {
	$scheduled = explode(",", $schedule_str);
} else {
	$scheduled = array();
}

// Subscription
$subs = getSettingSubscription($pdo);
if (!empty($subs['url']) && !empty($subs['token'])) {
	$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
}

// Users
$users = getSettingUsers($pdo);
$web_users = array();
$api_users = array();
$api_tokens = array_map(function($el) { if (isset($el['token'])) { return $el['token']; } }, $users);
foreach ($users as $key => $value) {
	if (isset($value['web']) && $value['web']) {
		array_push($web_users, $key);
	}
	if (isset($value['api'])) {
		array_push($api_users, $key);
	}
}
if (sizeof($web_users) == 1 && isset($users[implode($web_users)]['expires'])) {
	setSettingUser($pdo, implode($web_users), "expires", null);
}

// API Security
$api = getSettingApi($pdo);
if (empty($api_tokens) && $api['authtype'] == "token") {
	$api['authtype'] = "basic";
	setSettingApi($pdo, $api);
}
if (empty($api_users)) {
	$api['reqauth'] = false;
	setSettingApi($pdo, $api);
}
?>

			<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css"/>
			<link rel="stylesheet" href="theme/bootstrap-datetimepicker.css" />
			<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />
			<link rel="stylesheet" href="theme/bootstrap-toggle.css">

			<style>
				.btn-table {
					width: 75px;
				}
				#tab-content {
<?php if ($netsus > 4) { ?>
					margin-top: 341px;
<?php } elseif ($cloud) { ?>
					margin-top: 252px;
<?php } else { ?>
					margin-top: 295px;
<?php } ?>
				}
				#nav-title {
					top: 51px;
					height: 83px;
					border-bottom: 1px solid #eee;
					background: #fff;
					-webkit-transition: all 0.5s ease;
					-moz-transition: all 0.5s ease;
					-o-transition: all 0.5s ease;
					transition: all 0.5s ease;
					z-index: 90;
				}
				@media(min-width:768px) {
<?php if ($netsus > 4) { ?>
					#tab-content {
						margin-top: 165px;
					}
					#nav-title {
						left: 220px;
					}
<?php } else { ?>
					#tab-content {
						margin-top: 119px;
					}
<?php } ?>
				}
			</style>

			<script type="text/javascript" src="scripts/moment/moment.min.js"></script>
			<script type="text/javascript" src="scripts/bootstrap/transition.js"></script>
			<script type="text/javascript" src="scripts/bootstrap/collapse.js"></script>
			<script type="text/javascript" src="scripts/datetimepicker/bootstrap-datetimepicker.min.js"></script>

			<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
			<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

			<script type="text/javascript" src="scripts/toggle/bootstrap-toggle.min.js"></script>

			<script type="text/javascript">
				var allUsers = [<?php echo (sizeof($users) > 0 ? "\"".implode('", "', array_keys($users))."\"" : ""); ?>];
				var apiUsers = [<?php echo (sizeof($api_users) > 0 ? "\"".implode('", "', $api_users)."\"" : ""); ?>];
				var scheduled = [<?php echo (sizeof($scheduled) > 0 ? "\"".implode('", "', $scheduled)."\"" : ""); ?>];
				var sqliteDBs = [<?php echo (sizeof($sqlite_dbs) > 0 ? "\"".implode('", "', $sqlite_dbs)."\"" : ""); ?>];
			</script>

			<script type="text/javascript" src="scripts/patchValidation.js"></script>

			<script type="text/javascript">
				function updateSchedule(element) {
					if (scheduled.indexOf(element.value) >= 0) {
						scheduled.splice(scheduled.indexOf(element.value), 1);
					}
					if (element.checked) {
						scheduled.push(element.value);
					}
					scheduled.sort();
					ajaxPost('patchCtl.php', 'schedule='+scheduled.join());
					if (scheduled.length == 0 && ($('#dsn_prefix').val() == 'sqlite' || $('#dsn_prefix').val() == 'mysql' && ($('#dsn_host').val() == 'localhost' || $('#dsn_host').val() == '127.0.0.1'))) {
						showScheduleError();
					} else {
						hideScheduleError();
					}
				}

				function validRetention(element, labelId = false) {
					hideSuccess(element);
					if (element.value == parseInt(element.value) && element.value > 0  && element.value < 31) {
						hideError(element, labelId);
					} else {
						showError(element, labelId);
					}
				}

				function updateRetention(element, offset = false) {
					if (element.value == parseInt(element.value) && element.value > 0  && element.value < 31) {
						ajaxPost("patchCtl.php", "retention="+element.value);
						showSuccess(element, offset);
					}
				}

				function showScheduleError() {
					$('#schedule-tab-icon').removeClass('hidden');
					$('#schedule-alert-msg').removeClass('hidden');
				}

				function hideScheduleError() {
					$('#schedule-tab-icon').addClass('hidden');
					$('#schedule-alert-msg').addClass('hidden');
				}

				function toggleService() {
					if ($('#patchenabled').prop('checked')) {
						$('#patch').removeClass('hidden');
						$('#backup').prop('disabled', false);
						$('[name="schedule"]').prop('disabled', false);
						if (scheduled.length == 0) {
							showScheduleError();
						}
						$('#retention').prop('disabled', false);
						$('[name="restorepromt"]').prop('disabled', false);
						ajaxPost('patchCtl.php', 'service=enable');
					} else {
						$('#patch').addClass('hidden');
						$('#backup').prop('disabled', true);
						hideScheduleError();
						$('[name="schedule"]').prop('disabled', true);
						$('[name="schedule"]').prop('checked', false);
						scheduled = [];
						ajaxPost('patchCtl.php', 'schedule=');
						$('#retention').prop('disabled', true);
						$('[name="restorepromt"]').prop('disabled', true);
						ajaxPost('patchCtl.php', 'service=disable');
					}
				}

				function toggleDashboard() {
					if ($('#dashboard').prop('checked')) {
						ajaxPost('patchCtl.php', 'dashboard=true');
					} else {
						ajaxPost('patchCtl.php', 'dashboard=false');
					}
				}

				function toggleConnType() {
					var dsn_prefix = document.getElementById('dsn_prefix');
					if (dsn_prefix.value == 'sqlite') {
						$('#mysql_db').addClass('hidden');
						$('#sqlite_db').removeClass('hidden');
					} else {
						$('#sqlite_db').addClass('hidden');
						$('#mysql_db').removeClass('hidden');
					}
				}

				function validConn() {
					var dsn_prefix = document.getElementById('dsn_prefix');
					var dsn_dbpath = document.getElementById('dsn_dbpath');
					var dsn_dbfile = document.getElementById('dsn_dbfile');
					var new_dbfile = document.getElementById('new_dbfile');
					var dsn_host = document.getElementById('dsn_host');
					var dsn_port = document.getElementById('dsn_port');
					var dsn_dbname = document.getElementById('dsn_dbname');
					var dbuser = document.getElementById('dbuser');
					var dbpass = document.getElementById('dbpass');
					if ("" == dsn_dbfile.value) {
						$('#new_db_wrapper').removeClass('hidden');
						$('#new_dbfile').prop('disabled', false);
						dsn_dbpath.value = '<?php echo $sqlite_dir; ?>' + new_dbfile.value;
					} else {
						$('#new_db_wrapper').addClass('hidden');
						$('#new_dbfile').prop('disabled', true);
						dsn_dbpath.value = '<?php echo $sqlite_dir; ?>' + dsn_dbfile.value;
						new_dbfile.value = '';
					}
					if (/^[A-Za-z0-9._-]{1,64}$/.test(new_dbfile.value) && sqliteDBs.indexOf(new_dbfile.value) == -1 || /^[A-Za-z0-9._-]{1,64}$/.test(dsn_dbfile.value)) {
						hideError(new_dbfile, 'dsn_dbfile_label');
					} else {
						showError(new_dbfile, 'dsn_dbfile_label');
					}
					if (/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$|^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/.test(dsn_host.value)) {
						if (dsn_port.value == parseInt(dsn_port.value) && dsn_port.value >= 0 && dsn_port.value <= 65535) {
							hideError(dsn_host, 'dsn_host_label');
						} else {
							hideError(dsn_host);
						}
					} else {
						showError(dsn_host, 'dsn_host_label');
					}
					if (dsn_port.value == parseInt(dsn_port.value) && dsn_port.value >= 0 && dsn_port.value <= 65535) {
						if (/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$|^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/.test(dsn_host.value)) {
							hideError(dsn_port, 'dsn_host_label');
						} else {
							hideError(dsn_port);
						}
					} else {
						showError(dsn_port, 'dsn_host_label');
					}
					if (/^[A-Za-z0-9_]{1,64}$/.test(dsn_dbname.value)) {
						hideError(dsn_dbname, 'dsn_dbname_label');
					} else {
						showError(dsn_dbname, 'dsn_dbname_label');
					}
					if (/^[A-Za-z0-9._]{1,16}$/.test(dbuser.value)) {
						hideError(dbuser, 'dbuser_label');
					} else {
						showError(dbuser, 'dbuser_label');
					}
					if (/^.{1,64}$/.test(dbpass.value)) {
						hideError(dbpass, 'dbpass_label');
					} else {
						showError(dbpass, 'dbpass_label');
					}
					if (dsn_prefix.value == "sqlite" && (/^[A-Za-z0-9._-]{1,64}$/.test(new_dbfile.value) && sqliteDBs.indexOf(new_dbfile.value) == -1 || /^[A-Za-z0-9._-]{1,64}$/.test(dsn_dbfile.value)) || dsn_prefix.value == "mysql" && /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$|^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/.test(dsn_host.value) && dsn_port.value == parseInt(dsn_port.value) && dsn_port.value >= 0 && dsn_port.value <= 65535 && /^[A-Za-z0-9_]{1,64}$/.test(dsn_dbname.value) && /^[A-Za-z0-9._]{1,16}$/.test(dbuser.value) && /^.{1,64}$/.test(dbpass.value)) {
						$('#dbconnect').prop('disabled', false);
					} else {
						$('#dbconnect').prop('disabled', true);
					}
				}

				function validPath(element, buttonId, labelId = false) {
					hideSuccess(element);
					if (/^(\/)[^\0:]*$/.test(element.value)) {
						hideError(element, labelId);
						$('#'+buttonId).prop('disabled', false);
					} else {
						showError(element, labelId);
						$('#'+buttonId).prop('disabled', true);
					}
				}

				function validSubscribe() {
					var subs_url = document.getElementById('subs_url');
					var subs_token = document.getElementById('subs_token');
					if (/^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:/?#[\]@!\$&'\(\)\*\+,;=.]+$/.test(subs_url.value)) {
						hideError(subs_url, 'subs_url_label');
					} else {
						showError(subs_url, 'subs_url_label');
					}
					if (/^.{1,255}$/.test(subs_token.value)) {
						hideError(subs_token, 'subs_token_label');
					} else {
						showError(subs_token, 'subs_token_label');
					}
					if (/^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:/?#[\]@!\$&'\(\)\*\+,;=.]+$/.test(subs_url.value) && /^.{1,255}$/.test(subs_token.value) || subs_url.value == "" && subs_token.value == "") {
						hideError(subs_url, 'subs_url_label');
						hideError(subs_token, 'subs_token_label');
						$('#subscribe').prop('disabled', false);
					} else {
						$('#subscribe').prop('disabled', true);
					}
				}

				function subsRefresh(element) {
					ajaxPost('patchCtl.php', 'subs_refresh='+element.value);
				}

				function toggleAPIAuthType() {
					if ($('#api_authtype').val() == 'basic') {
						ajaxPost('patchCtl.php', 'api_authtype=basic');
					} else if ($('#api_authtype').val() == 'token') {
						ajaxPost('patchCtl.php', 'api_authtype=token');
					} else {
						ajaxPost('patchCtl.php', 'api_authtype=none');
					}
				}

				function toggleAPIAccess() {
					if ($('#api_reqauth').prop('checked') == true) {
						ajaxPost('patchCtl.php', 'api_reqauth=true');
					} else {
						ajaxPost('patchCtl.php', 'api_reqauth=false');
					}
				}

				function toggleAPIAuto() {
					if ($('#api_auto').prop('checked') == true) {
						ajaxPost('patchCtl.php', 'api_auto=true');
					} else {
						ajaxPost('patchCtl.php', 'api_auto=false');
					}
				}

				function toggleWebAdmin(element) {
					user = element.value;
					if (element.checked) {
						ajaxPost('patchCtl.php', 'allow_web='+user);
					} else {
						ajaxPost('patchCtl.php', 'deny_web='+user);
					}
				}

				function toggleAPIRead(element) {
					user = element.value;
					if (element.checked) {
						ajaxPost('patchCtl.php', 'allow_api='+user);
						if (apiUsers.indexOf(user) == -1) {
							apiUsers.push(user);
						}
					} else {
						ajaxPost('patchCtl.php', 'deny_api='+user);
						if (apiUsers.indexOf(user) >= 0) {
							apiUsers.splice(apiUsers.indexOf(user), 1);
						}
					}
					if (apiUsers.length == 0) {
						$('#api_access').val('writeonly');
						$('#api_access').prop('disabled', true);
						ajaxPost('patchCtl.php', 'api_access=writeonly');
					} else {
						$('#api_access').prop('disabled', false);
					}
				}

				function toggleAPIWrite(element) {
					user = element.value;
					if (element.checked) {
						ajaxPost('patchCtl.php', 'allow_api_rw='+user);
						$('input[name="api_ro"][value="' + user + '"]').prop('disabled', true);
						$('input[name="api_ro"][value="' + user + '"]').prop('checked', true);
						if (apiUsers.indexOf(user) == -1) {
							apiUsers.push(user);
						}
					} else {
						ajaxPost('patchCtl.php', 'allow_api='+user);
						$('input[name="api_ro"][value="' + user + '"]').prop('disabled', false);
					}
				}

				function validUser() {
					var add_user = document.getElementById('add_user');
					var add_pass = document.getElementById('add_pass');
					var add_verify = document.getElementById('add_verify');
					var add_expires = document.getElementById('add_expires');
					if (/^([A-Za-z0-9 ._-]){1,64}$/.test(add_user.value) && allUsers.indexOf(add_user.value) == -1) {
						hideError(add_user, 'add_user_label');
					} else {
						showError(add_user, 'add_user_label');
					}
					if (/^.{1,128}$/.test(add_pass.value)) {
						hideError(add_pass, 'add_pass_label');
					} else {
						showError(add_pass, 'add_pass_label');
					}
					if (/^.{1,128}$/.test(add_verify.value) && add_verify.value == add_pass.value) {
						hideError(add_verify, 'add_verify_label');
					} else {
						showError(add_verify, 'add_verify_label');
					}
					if (/^$/.test(add_expires.value) || /^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(add_expires.value)) {
						hideError(add_expires, 'add_expires_label');
					} else {
						showError(add_expires, 'add_expires_label');
					}
					if (/^([A-Za-z0-9 ._-]){1,64}$/.test(add_user.value) && allUsers.indexOf(add_user.value) == -1 && /^.{1,128}$/.test(add_verify.value) && add_verify.value == add_pass.value && (/^$/.test(add_expires.value) || /^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(add_expires.value))) {
						$('#create_user').prop('disabled', false);
					} else {
						$('#create_user').prop('disabled', true);
					}
				}

				function validPass() {
					var reset_pass = document.getElementById('reset_pass');
					var reset_verify = document.getElementById('reset_verify');
					if (/^.{1,128}$/.test(reset_pass.value)) {
						hideError(reset_pass, 'reset_pass_label');
					} else {
						showError(reset_pass, 'reset_pass_label');
					}
					if (/^.{1,128}$/.test(reset_verify.value) && reset_verify.value == reset_pass.value) {
						hideError(reset_verify, 'reset_verify_label');
					} else {
						showError(reset_verify, 'reset_verify_label');
					}
					if (/^.{1,128}$/.test(reset_verify.value) && reset_verify.value == reset_pass.value) {
						$('#save_pass').prop('disabled', false);
					} else {
						$('#save_pass').prop('disabled', true);
					}
				}

				function validExpiry() {
					var reset_expires = document.getElementById('reset_expires');
					if (/^$/.test(reset_expires.value) || /^(19[7-9][0-9]|[2-9][0-9][0-9][0-9])-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])T([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])Z$/.test(reset_expires.value)) {
						hideError(reset_expires, 'reset_expires_label');
						$('#reset_expiry').prop('disabled', false);
					} else {
						showError(reset_expires, 'reset_expires_label');
						$('#reset_expiry').prop('disabled', true);
					}
				}

				function generateToken(user_id) {
					$('#create_token').val(user_id);
					$('#Settings').submit();
				}
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					if (scheduled.length == 0 && ($('#dsn_prefix').val() == 'sqlite' || $('#dsn_prefix').val() == 'mysql' && ($('#dsn_host').val() == 'localhost' || $('#dsn_host').val() == '127.0.0.1'))) {
						showScheduleError();
					}
				});
			</script>

			<script type="text/javascript">
				$(function () {
					$('#add_expires_datepicker').datetimepicker({
						format: 'YYYY-MM-DDTHH:mm:ss\\Z',
						minDate: moment().add(1, 'days')
					});
				});
			</script>

			<script type="text/javascript">
				$(function () {
					$('#reset_expires_datepicker').datetimepicker({
						format: 'YYYY-MM-DDTHH:mm:ss\\Z',
						minDate: moment().add(1, 'days')
					});
				});
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					$('#backups').DataTable( {
						buttons: [
							{
								text: '<span class="glyphicon glyphicon-plus"></span> Upload',
								className: 'btn-primary btn-sm btn-table',
								action: function ( e, dt, node, config ) {
									$("#uploadBackup").modal();
								}
							}
						],
						"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'<'dataTables_paginate'B>>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
						"order": [ 2, 'desc' ],
						"lengthMenu": [ [5, 10, 25, -1], [5, 10, 25, "All"] ],
						"pageLength": 5,
						"columns": [
							null,
							null,
							null,
							null,
							{ "orderable": false }
						]
					});
					$('#users').DataTable( {
						buttons: [
							{
								text: '<span class="glyphicon glyphicon-plus"></span> Add',
								className: 'btn-primary btn-sm btn-table',
<?php if (!empty($pdo_error)) { ?>
								enabled: false,
<?php } ?>
								action: function ( e, dt, node, config ) {
									$("#create_user-modal").modal();
								}
							}
						],
						"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'<'dataTables_paginate'B>>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
						"order": [ 0, 'asc' ],
						"lengthMenu": [ [5, 10, 25, -1], [5, 10, 25, "All"] ],
						"pageLength": 5,
						"columns": [
							null,
							null,
							{ "orderable": false },
							null,
							{ "orderable": false },
							{ "orderable": false },
							{ "orderable": false },
							{ "orderable": false }
						]
					});
				} );
			</script>

			<script type="text/javascript">
				// function to save the current tab on refresh
				$(document).ready(function(){
					$('a[data-toggle="tab"]').on('show.bs.tab', function(e) {
						localStorage.setItem('activePatchTab', $(e.target).attr('href'));
					});
					var activePatchTab = localStorage.getItem('activePatchTab');
					if(activePatchTab){
						$('#top-tabs a[href="' + activePatchTab + '"]').tab('show');
					}
				});
			</script>

			<script type="text/javascript">
				$(document).ready(function(){
					toggleService();
				});
			</script>

<?php if (isset($_POST['dbconnect']) && empty($pdo_error) && $netsus == 0) { ?>
			<script>
				$(window).load(function() {
					$('#database_change-modal').modal('show');
				});
			</script>

<?php } ?>

<?php if (!empty($restore_success) && $netsus == 0) { ?>
			<script>
				$(window).load(function() {
					$('#restore_complete-modal').modal('show');
				});
			</script>

<?php } ?>
			<nav id="nav-title" class="navbar navbar-default navbar-fixed-top">
				<div style="padding: 19px 20px 1px;">
<?php if ($netsus > 0) { ?>
					<div class="description"><a href="settings.php">Settings</a> <span class="glyphicon glyphicon-chevron-right"></span> <span class="text-muted">Services</span> <span class="glyphicon glyphicon-chevron-right"></span></div>
					<div class="row">
						<div class="col-xs-10">
							<h2>Patch Definitions</h2>
						</div>
<?php } else { ?>
					<div class="description">&nbsp;</div>
					<div class="row">
						<div class="col-xs-10">
							<h2>Settings</h2>
						</div>
<?php } ?>
						<div class="col-xs-2 text-right <?php echo ($netsus > 0 ? "" : "hidden"); ?>">
							<input type="checkbox" id="patchenabled" data-toggle="toggle" data-size="small" onChange="toggleService();" <?php echo ($service ? "checked" : ""); ?>>
						</div>
					</div>
				</div>
				<div style="padding: <?php echo ($netsus > 4 ? "" : "1"); ?>6px 20px 0px; background-color: #f9f9f9; border-bottom: 1px solid #ddd;">
					<div class="checkbox checkbox-primary <?php echo ($netsus > 4 ? "" : "hidden"); ?>">
						<input name="dashboard" id="dashboard" class="styled" type="checkbox" value="true" onChange="toggleDashboard();" <?php echo ($dashboard ? "checked" : ""); ?>>
						<label><strong>Show in Dashboard</strong><br><span style="font-size: 75%; color: #777;">Display service status in the NetSUS dashboard.</span></label>
					</div>
					<ul class="nav nav-tabs nav-justified" id="top-tabs" style="margin-bottom: -1px;">
<?php if (isset($subs_resp['endpoint']) || $netsus == 0) { ?>
						<li><a class="tab-font" href="#users-tab" role="tab" data-toggle="tab">Authentication</a></li>
<?php }
if (!$cloud) { ?>
						<li><a class="tab-font" href="#database-tab" role="tab" data-toggle="tab" <?php echo (empty($pdo_error) ? "" : "style=\"color: #a94442;\"") ?>><span id="database-tab-icon" class="glyphicon glyphicon-exclamation-sign <?php echo (empty($pdo_error) ? "hidden" : "") ?>"></span> Database</a></li>
<?php } ?>
						<li class="active"><a class="tab-font" href="#backup-tab" role="tab" data-toggle="tab"><span id="schedule-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Backup</a></li>
						<li><a class="tab-font" href="#restore-tab" role="tab" data-toggle="tab">Restore</a></li>
						<li><a class="tab-font" href="#subscription-tab" role="tab" data-toggle="tab"><span id="subscription-tab-icon" class="glyphicon glyphicon-exclamation-sign <?php echo (isset($subs_resp) ? (empty($subs_resp) ? "" : ($subs_resp['expires'] > $subs_resp['timestamp'] + (14*24*60*60) ? "hidden" : "")) : "hidden"); ?>"></span> Subscription</a></li>
					</ul>
				</div>
			</nav>

			<form action="patchSettings.php" method="post" name="Settings" id="Settings" enctype="multipart/form-data">

				<div id="tab-content" class="tab-content">

<?php if (isset($subs_resp['endpoint']) || $netsus == 0) { ?>
					<div class="tab-pane fade in" id="users-tab">

						<div style="padding: 9px 20px 1px;">
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($api_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $api_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($api_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $api_success; ?></div>
								</div>
							</div>

							<h5><strong>Users</strong> <small>Kinobi user accounts and privileges.</small></h5>

							<table id="users" class="table table-hover" style="border-bottom: 1px solid #eee;">
								<thead>
									<tr>
										<th>Username</th>
										<th>Token</th>
										<th><!-- Warning --></th>
										<th>Expires</th>
										<th><?php echo ($netsus > 0 ? "" : "Web Admin"); ?></th>
										<th><?php echo (isset($subs_resp['type']) ? $subs_resp['type'] == "Server" ? "API Read" : "" : ""); ?></th>
										<th><?php echo (isset($subs_resp['endpoint']) ? "API Write" : ""); ?></th>
										<th><!-- Delete --></th>
									</tr>
								</thead>
								<tbody>
<?php foreach ($users as $key => $value) {
	if ($netsus == 0 || !isset($value['web'])) {?>
									<tr>
										<td>
											<div class="dropdown">
												<a href="#" id="user_<?php echo $key; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><?php echo $key; ?></a>
												<ul class="dropdown-menu" aria-labelledby="user_<?php echo $key; ?>">
													<li><a data-toggle="modal" href="#reset_pass-modal" onClick="$('#reset_pass-title').text('<?php echo $key; ?>'); $('#save_pass').val('<?php echo $key; ?>');">Reset Password</a></li>
												</ul>
											</div>
										</td>
										<td>
											<div class="dropdown">
												<a href="#" id="token_<?php echo $key; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><?php echo (isset($value['token']) ? $value['token'] : "&lt;No Token&gt;"); ?></a>
												<ul class="dropdown-menu" aria-labelledby="token_<?php echo $key; ?>">
													<li><a href="#" onClick="generateToken('<?php echo $key; ?>');"><?php echo (isset($value['token']) ? "Re-" : ""); ?>Generate</a></li>
												</ul>
											</div>
										</td>
										<td><span class="<?php echo (isset($value['expires']) ? (time() > $value['expires'] ? "text-warning glyphicon glyphicon-exclamation-sign" : "") : ""); ?>"></span></td>
										<td>
											<div class="dropdown">
												<a href="#" id="expiry_<?php echo $key; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><?php echo (isset($value['expires']) ? gmdate("Y-m-d\TH:i:s\Z", $value['expires']) : "&lt;Never&gt;"); ?></a>
												<ul class="dropdown-menu" aria-labelledby="expiry_<?php echo $key; ?>">
													<li class="<?php echo (isset($value['web']) ? ($value['web'] && sizeof($web_users) == 1 ? "disabled" : "") : ""); ?>"><a data-toggle="<?php echo (isset($value['web']) ? ($value['web'] && sizeof($web_users) == 1 ? "" : "modal") : "modal"); ?>" href="#reset_expiry-modal" onClick="$('#reset_expiry-title').text('<?php echo $key; ?>'); $('#reset_expires').val('<?php echo (empty($value['expires']) ? "" : gmdate("Y-m-d\TH:i:s\Z", $value['expires'])); ?>'); $('#reset_expiry').val('<?php echo $key; ?>');"><?php echo (isset($value['expires']) ? "Change" : "Set"); ?></a></li>
												</ul>
											</div>
										</td>
										<td>
											<div class="checkbox checkbox-primary checkbox-inline <?php echo ($netsus == 0 ? "" : "hidden"); ?>">
												<input type="checkbox" class="styled" name="web_ui" id="web_ui" value="<?php echo $key; ?>" onChange="toggleWebAdmin(this);" <?php echo (isset($value['web']) ? ($value['web'] ? "checked" : "") : ""); ?> <?php echo ($value['web'] && sizeof($web_users) == 1 || $key == $_SESSION['username'] ? "disabled" : ""); ?>/>
												<label/>
											</div>
										</td>
										<td>
											<div class="checkbox checkbox-primary checkbox-inline <?php echo (isset($subs_resp['type']) ? $subs_resp['type'] == "Server" ? "" : "hidden" : "hidden"); ?>">
												<input type="checkbox" class="styled" name="api_ro" id="api_ro" value="<?php echo $key; ?>" onChange="toggleAPIRead(this);" <?php echo (isset($value['api']) ? "checked" : ""); ?> <?php echo (isset($value['api']) ? ($value['api'] == "1" ? "disabled" : "") : ""); ?>/>
												<label/>
											</div>
										</td>
										<td>
											<div class="checkbox checkbox-primary checkbox-inline <?php echo (isset($subs_resp['endpoint']) ? "" : "hidden"); ?>">
												<input type="checkbox" class="styled" name="api_rw" id="api_rw" value="<?php echo $key; ?>" onChange="toggleAPIWrite(this);" <?php echo (isset($value['api']) ? ($value['api'] == "1" ? "checked" : "") : ""); ?>/>
												<label/>
											</div>
										</td>
										<td align="right"><button type="button" name="del_user_prompt" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_user-modal" onClick="$('#delete_user-title').text('<?php echo $key; ?>'); $('#delete_user').val('<?php echo $key; ?>');" value="<?php echo $key; ?>" <?php echo ($value['web'] && sizeof($web_users) == 1 || $key == $_SESSION['username'] ? "disabled" : ""); ?>>Delete</button></td>
									</tr>
<?php }
} ?>
								</tobdy>
							</table>
							<input type="hidden" name="create_token" id="create_token">
						</div>

						<hr>

<?php if (isset($subs_resp['endpoint'])) { ?>
						<div style="padding: 9px 20px 1px; background-color: #f9f9f9;">
							<h5><strong>API Authentication Type</strong> <small>Authentication type to use for API endpoints.</small></h5>
							<div class="form-group" style="max-width: 449px;">
								<select id="api_authtype" name="api_authtype" class="form-control input-sm" onChange="toggleAPIAuthType();" <?php echo (sizeof($api_users) == 0 ? "disabled" : ""); ?>>
									<option value="basic" <?php echo ($api['authtype'] == "basic" ? "selected" : ""); ?>>Basic</option>
									<option value="token" <?php echo ($api['authtype'] == "token" ? "selected" : ""); ?> <?php echo (empty($api_tokens) ? "disabled" : ""); ?>>Token</option>
								</select>
							</div>
						</div>

						<hr>

						<div style="padding: 9px 20px 1px;">
							<div class="checkbox checkbox-primary">
								<input name="api_auto" id="api_auto" class="styled" type="checkbox" value="true" onChange="toggleAPIAuto();" <?php echo ($api['auto'] ? "checked" : ""); ?>>
								<label><strong>API Auto-Enable</strong><br><span style="font-size: 75%; color: #777;">Automatically enable items imported via the API.</span></label>
							</div>
						</div>
<?php if (isset($subs_resp['type']) && $subs_resp['type'] == "Server") { ?>
						<hr>

						<div style="padding: 9px 20px 1px; background-color: #f9f9f9;">
							<div class="checkbox checkbox-primary">
								<input name="api_reqauth" id="api_reqauth" class="styled" type="checkbox" value="true" onChange="toggleAPIAccess();" <?php echo ($api['reqauth'] ? "checked" : ""); ?> <?php echo (sizeof($api_users) == 0 ? "disabled" : ""); ?>>
								<label><strong>Require API Authentication</strong><br><span style="font-size: 75%; color: #777;">Require authentication for API endpoints.<br><strong>Note:</strong> Jamf Pro does not currently support authentication for reading from external patch sources.</span></label>
							</div>
						</div>

						<hr>
<?php }
} ?>

						<!-- Add User Modal -->
						<div class="modal fade" id="create_user-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Add User</h3>
									</div>
									<div class="modal-body">
										<h5 id="add_user_label"><strong>User Name</strong> <small>Username for the account.</small></h5>
										<div class="form-group">
											<input type="text" name="add_user" id="add_user" class="form-control input-sm" onFocus="validUser();" onKeyUp="validUser();" onBlur="validUser();" placeholder="[Required]" value=""/>
										</div>
										<h5 id="add_pass_label"><strong>Password</strong> <small>Password for the account.</small></h5>
										<div class="form-group">
											<input type="password" name="add_pass" id="add_pass" class="form-control input-sm" onFocus="validUser();" onKeyUp="validUser();" onBlur="validUser();" placeholder="[Required]" value=""/>
										</div>
										<h5 id="add_verify_label"><strong>Verify Password</strong></h5>
										<div class="form-group">
											<input type="password" name="add_verify" id="add_verify" class="form-control input-sm" onFocus="validUser();" onKeyUp="validUser();" onBlur="validUser();" placeholder="[Required]" value=""/>
										</div>
										<h5 id="add_expires_label"><strong>Expires</strong> <small>Date that this user account expires.</small></h5>
										<div class="form-group">
											<div class="input-group date" id="add_expires_datepicker">
												<span class="input-group-addon input-sm" style="color: #555; background-color: #eee; border: 1px solid #ccc; border-right: 0;">
													<span class="glyphicon glyphicon-calendar"></span>
												</span>
												<input type="text" name="add_expires" id="add_expires" class="form-control input-sm" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" onFocus="validUser();" onKeyUp="validUser();" onBlur="validUser();" placeholder="[Optional]" value="<?php echo gmdate("Y-m-d\TH:i:s\Z", time()+30*24*60*60); ?>" />
											</div>
										</div>
										<div class="checkbox checkbox-primary">
											<input name="add_token" id="add_token" class="styled" type="checkbox" value="true" checked>
											<label><strong>Generate Token</strong><br><span style="font-size: 75%; color: #777;">Generate an authentication token for this user.</span></label>
										</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
										<button type="submit" name="create_user" id="create_user" class="btn btn-primary btn-sm" disabled>Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Reset Password Modal -->
						<div class="modal fade" id="reset_pass-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Reset Password for <span id="reset_pass-title">User</span></h3>
									</div>
									<div class="modal-body">
										<h5 id="reset_pass_label"><strong>New Password</strong> <small>Password for the account.</small></h5>
										<div class="form-group">
											<input type="password" name="reset_pass" id="reset_pass" class="form-control input-sm" onFocus="validPass();" onKeyUp="validPass();" onBlur="validPass();" placeholder="[Required]" value=""/>
										</div>
										<h5 id="reset_verify_label"><strong>Verify Password</strong></h5>
										<div class="form-group">
											<input type="password" name="reset_verify" id="reset_verify" class="form-control input-sm" onFocus="validPass();" onKeyUp="validPass();" onBlur="validPass();" placeholder="[Required]" value=""/>
										</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
										<button type="submit" name="save_pass" id="save_pass" class="btn btn-primary btn-sm" disabled>Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Reset Expiry Modal -->
						<div class="modal fade" id="reset_expiry-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Reset Expiry for <span id="reset_expiry-title">User</span></h3>
									</div>
									<div class="modal-body">
										<h5 id="reset_expires_label"><strong>Expires</strong> <small>Date that this user account expires.</small></h5>
										<div class="form-group">
											<div class="input-group date" id="reset_expires_datepicker">
												<span class="input-group-addon input-sm" style="color: #555; background-color: #eee; border: 1px solid #ccc; border-right: 0;">
													<span class="glyphicon glyphicon-calendar"></span>
												</span>
												<input type="text" name="reset_expires" id="reset_expires" class="form-control input-sm" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" onFocus="validExpiry();" onKeyUp="validExpiry();" onBlur="validExpiry();" placeholder="[Optional]" value="" />
											</div>
										</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
										<button type="submit" name="reset_expiry" id="reset_expiry" class="btn btn-primary btn-sm" value="">Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete User Modal -->
						<div class="modal fade" id="delete_user-modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Delete <span id="delete_user-title">User</span></h3>
									</div>
									<div class="modal-body">
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_user" id="delete_user" class="btn btn-danger btn-sm pull-right" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div> <!-- /.tab-pane -->
<?php } ?>

<?php if (!$cloud) { ?>
					<div class="tab-pane fade in" id="database-tab">

						<div style="padding: 9px 20px 16px;">
<?php if (empty($pdo_error)) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo "Connected to: ".($db['dsn']['prefix'] == "sqlite" ? $db['dsn']['dbpath'] : $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)); ?></div>
								</div>
							</div>
<?php } else { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $pdo_error; ?></div>
								</div>
							</div>
<?php } ?>

							<h5><strong>Database Type</strong> <small>Database connection type to use.</small></h5>
							<div class="form-group" style="max-width: 449px;">
								<select id="dsn_prefix" name="dsn_prefix" class="form-control input-sm" onChange="validConn(); toggleConnType();">
									<option value="sqlite" <?php echo ($db['dsn']['prefix'] == "sqlite" ? "selected": ""); ?>>SQLite</option>
									<option value="mysql" <?php echo ($db['dsn']['prefix'] == "mysql" ? "selected": ""); ?>>MySQL</option>
								</select>
							</div>

							<div id="sqlite_db" class="<?php echo ($db['dsn']['prefix'] == "sqlite" ? "": "hidden"); ?>">
								<h5 id="dsn_dbfile_label"><strong>Database</strong> <small>SQLite database file.</small></h5>
								<div class="form-group" style="max-width: 449px;">
									<select id="dsn_dbfile" name="dsn_dbfile" class="form-control input-sm" onFocus="validConn();" onChange="validConn();">
<?php foreach ($sqlite_dbs as $sqlite_db) { ?>
										<option value="<?php echo $sqlite_db; ?>" <?php echo (basename($db['dsn']['dbpath']) == $sqlite_db ? "selected": ""); ?>><?php echo $sqlite_db; ?></option>
<?php } ?>
										<option value="">New...</option>
									</select>
								</div>

								<div id="new_db_wrapper" class="form-group has-feedback hidden" style="max-width: 449px;">
									<input type="text" name="new_dbfile" id="new_dbfile" class="form-control input-sm" onFocus="validConn();" onKeyUp="validConn();" onBlur="validConn();" disabled/>
								</div>

								<input type="hidden" name="dsn_dbpath" id="dsn_dbpath" value="<?php echo (isset($db['dsn']['dbpath']) ? $db['dsn']['dbpath'] : ""); ?>"/>
							</div>

							<div id="mysql_db" class="<?php echo ($db['dsn']['prefix'] == "mysql" ? "": "hidden"); ?>">
								<h5 id="dsn_host_label"><strong>Hostname &amp; Port</strong> <small>Hostname or IP address, and port number for the MySQL server.</small></h5>
								<div class="form-group has-feedback" style="display: inline-block; margin-bottom: 5px;">
									<input type="text" name="dsn_host" id="dsn_host" class="form-control input-sm" style="width: 334px;" onFocus="validConn();" onKeyUp="validConn();" onBlur="validConn();" value="<?php echo (isset($db['dsn']['host']) ? $db['dsn']['host'] : ""); ?>"/>
								</div>
								<div class="form-group text-center" style="display: inline-block; width: 3px; margin-bottom: 5px;">:</div>
								<div class="form-group has-feedback" style="display: inline-block; margin-bottom: 5px;">
									<input type="text" name="dsn_port" id="dsn_port" class="form-control input-sm" style="width: 105px;" onFocus="validConn();" onKeyUp="validConn();" onBlur="validConn();" value="<?php echo (isset($db['dsn']['port']) ? $db['dsn']['port'] : ""); ?>"/>
								</div>

								<h5 id="dsn_dbname_label"><strong>Database</strong> <small>MySQL database used for patch definitions.</small></h5>
								<div class="form-group has-feedback" style="max-width: 449px;">
									<input type="text" name="dsn_dbname" id="dsn_dbname" class="form-control input-sm" onFocus="validConn();" onKeyUp="validConn();" onBlur="validConn();" value="<?php echo (isset($db['dsn']['dbname']) ? $db['dsn']['dbname'] : ""); ?>"/>
								</div>

								<h5 id="dbuser_label"><strong>Username</strong> <small>Username used to connect to the MySQL server.</small></h5>
								<div class="form-group has-feedback" style="max-width: 449px;">
									<input type="text" name="dbuser" id="dbuser" class="form-control input-sm" onFocus="validConn();" onKeyUp="validConn();" onBlur="validConn();" value="<?php echo (isset($db['username']) ? $db['username'] : ""); ?>"/>
								</div>
								<h5 id="dbpass_label"><strong>Password</strong> <small>Password used to authenticate with the MySQL server.</small></h5>
								<div class="form-group has-feedback" style="max-width: 449px;">
									<input type="password" name="dbpass" id="dbpass" class="form-control input-sm" onFocus="validConn();" onKeyUp="validConn();" onBlur="validConn();" value=""/>
								</div>
							</div>

							<button type="submit" name="dbconnect" id="dbconnect" class="btn btn-primary btn-sm" style="width: 75px;" disabled>Save</button>
						</div>

						<!-- Database Success Modal -->
						<div class="modal fade" id="database_change-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Database Changed</h3>
									</div>
									<div class="modal-body">
										<div style="margin-top: 0px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success">
											<div class="panel-body">
												<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo (empty($pdo_error) ? "Connected to: ".($db['dsn']['prefix'] == "sqlite" ? $db['dsn']['dbpath'] : $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) : ""); ?>. Log out for changes to take effect.</div>
											</div>
										</div>
									</div>
									<div class="modal-footer">
										<a href="logout.php" role="button" class="btn btn-primary btn-sm pull-right">Logout</a>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div> <!-- /.tab-pane -->
<?php } ?>

					<div class="tab-pane active fade in" id="backup-tab">

						<div style="padding: 9px 20px 16px;">
							<div id="schedule-alert-msg" style="margin-top: 11px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning hidden">
								<div class="panel-body">
									<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>No backups scheduled.</div>
								</div>
							</div>

							<h5><strong>Backup Schedule</strong> <small>Days of the week for an automatic backup to run.<br><strong>Note:</strong> Backups will occur at 12:00 AM (this server's local time) on the specified days.</small></h5>

							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[0]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="0" <?php echo (in_array(0, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="sun"> Sun </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[1]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="1" <?php echo (in_array(1, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="mon"> Mon </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[2]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="2" <?php echo (in_array(2, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="tue"> Tue </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[3]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="3" <?php echo (in_array(3, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="wed"> Wed </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[4]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="4" <?php echo (in_array(4, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="thu"> Thu </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[5]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="5" <?php echo (in_array(5, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="fri"> Fri </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[6]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="6" <?php echo (in_array(6, $scheduled) ? "checked" : ""); ?> <?php echo ($pdo ? "" : "disabled"); ?>>
								<label for="sat"> Sat </label>
							</div>
						</div>

						<hr>

						<div style="padding: 9px 20px 1px; background-color: #f9f9f9;">
							<h5 id="retention_label"><strong>Backup Retention</strong> <small>Number of backup archives to be retained on the server.</small></h5>
							<div class="form-group has-feedback" style="width: 105px;">
								<input type="text" id="retention" class="form-control input-sm" onFocus="validRetention(this, 'retention_label');" onKeyUp="validRetention(this, 'retention_label');" onChange="updateRetention(this);" placeholder="[1 - 30]" value="<?php echo $backup['retention']; ?>" />
							</div>
						</div>

						<hr>

 						<div style="padding: 9px 20px 16px;">
							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($backup_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $backup_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($backup_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $backup_success; ?></div>
								</div>
							</div>

							<h5><strong>Manual Backup</strong> <small>Perform a manual backup of the database.</small></h5>
							<button type="button" class="btn btn-primary btn-sm" style="width: 75px;" data-toggle="modal" data-target="#backup-modal"  <?php echo ($pdo ? "" : "disabled"); ?>>Backup</button>
						</div>

						<!-- Manual Backup Modal -->
						<div class="modal fade" id="backup-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Manual Backup</h3>
									</div>
									<div class="modal-body">

										<h5><strong>Backup Format</strong> <small>Select the format for the database backup.<br><strong>Note:</strong> The backup may only be restored to the corresponding database type.</small></h5>
										<div class="form-group">
											<select id="backup_type" name="backup_type" class="form-control input-sm">
												<option value="sqlite" <?php echo ($db['dsn']['prefix'] == "sqlite" ? "selected": ""); ?>>SQLite</option>
												<option value="mysql" <?php echo ($db['dsn']['prefix'] == "mysql" ? "selected": ""); ?>>MySQL</option>
											</select>
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="backup" id="backup" class="btn btn-primary btn-sm" >Backup</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="restore-tab">

						<div style="padding: 9px 20px 1px;">
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($upload_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $upload_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($upload_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $upload_success; ?></div>
								</div>
							</div>

							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($delete_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $delete_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($delete_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $delete_success; ?></div>
								</div>
							</div>

							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($restore_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $restore_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($restore_success) || $netsus == 0 ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $restore_success; ?> <a href="logout.php">Log Out</a> for changes to take effect.</div>
								</div>
							</div>

							<h5><strong>Available Backups</strong> <small>Click the backup filename to <?php echo ($cloud ? "restore" : "download or restore"); ?> a backup archive.<?php if (!$cloud) { ?><br>Backup archives are saved in <a data-toggle="modal" href="#backup_path-modal"><span style="font-family:monospace;"><?php echo $backup['path']; ?></span></a> on this server.<?php } ?></small></h5>
							
						</div>

						<div style="padding: 9px 20px 1px; overflow-x: auto;">
							<table id="backups" class="table table-hover" style="border-bottom: 1px solid #eee;">
								<thead>
									<tr>
										<th>Filename</th>
										<th>Format</th>
										<th>Date</th>
										<th>Size</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
<?php foreach ($backups as $value) { ?>
									<tr>
										<td>
											<div class="dropdown">
												<a href="#" id="<?php echo $value['file']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><?php echo $value['file']; ?></a>
												<ul class="dropdown-menu" aria-labelledby="<?php echo $value['file']; ?>">
<?php if (!$cloud) { ?>
													<li><a href="patchCtl.php?download=<?php echo $value['file']; ?>">Download</a></li>
<?php } ?>
													<li class="<?php echo (!$pdo || strtolower($value['type']) != $db['dsn']['prefix'] ? "disabled" : ""); ?>"><a data-toggle="<?php echo (!$pdo || strtolower($value['type']) != $db['dsn']['prefix'] ? "" : "modal"); ?>" href="#restore-modal" onClick="$('#restore-title').text('<?php echo $value['file']; ?>'); $('#restore').val('<?php echo $value['file']; ?>');">Restore</a></li>
												</ul>
											</div>
										</td>
										<td><?php echo $value['type']; ?></td>
										<td><?php echo gmdate("Y-m-d\TH:i:s\Z", $value['date']); ?></td>
										<td><?php echo formatSize($value['size'], 0); ?></td>
										<td align="right"><button type="button" name="deletepromt" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_backup-modal" onClick="$('#delete_backup-title').text('<?php echo $value['file']; ?>'); $('#delete_backup').val('<?php echo $value['file']; ?>');">Delete</button></td>
									</tr>
<?php } ?>
								</tobdy>
							</table>
						</div>

						<!-- Backup Path Modal -->
						<div class="modal fade" id="backup_path-modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Change Backup Path</h3>
									</div>
									<div class="modal-body">

										<h5 id="db_backup_path_label"><strong>Backup Path</strong> <small>Location to save backups.</small></h5>
										<div class="form-group has-feedback" style="max-width: 449px;">
											<input type="text" name="backup_path" id="backup_path" class="form-control input-sm" onFocus="validPath(this, 'save_backup_path', 'db_backup_path_label');" onKeyUp="validPath(this, 'save_backup_path', 'db_backup_path_label');" onBlur="validPath(this, 'save_backup_path', 'db_backup_path_label');" value="<?php echo $backup['path']; ?>"/>
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" style="width: 75px;" >Cancel</button>
										<button type="submit" name="save_backup_path" id="save_backup_path" class="btn btn-primary btn-sm" style="width: 75px;" disabled>Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Upload Modal -->
						<div class="modal fade" id="uploadBackup" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Upload Backup</h3>
									</div>
									<div class="modal-body">

										<h5><strong>Archive</strong> <small>Select a backup archive file (gzipped SQLite or MySQL dump file) to add to the list of available backups.</small></h5>
										<input type="file" name="upload_file" id="upload_file" class="form-control input-sm" onChange="document.getElementById('upload').disabled = this.value == '';" >

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="upload" id="upload" class="btn btn-primary btn-sm" disabled >Upload</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Restore Backup Modal -->
						<div class="modal fade" id="restore-modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Restore <span id="restore-title">Backup</span></h3>
									</div>
									<div class="modal-body">
										<div class="text-muted">Are you sure you want to restore this backup?</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="restore" id="restore" class="btn btn-primary btn-sm pull-right" value="">Restore</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Restore Success Modal -->
						<div class="modal fade" id="restore_complete-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Restore Complete</h3>
									</div>
									<div class="modal-body">
										<div style="margin-top: 0px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success">
											<div class="panel-body">
												<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $restore_success; ?></div>
											</div>
										</div>
									</div>
									<div class="modal-footer">
										<a href="logout.php" role="button" class="btn btn-primary btn-sm pull-right">Logout</a>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete Backup Modal -->
						<div class="modal fade" id="delete_backup-modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title" id="modalLabel">Delete <span id="delete_backup-title">Backup</span></h3>
									</div>
									<div class="modal-body">
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_backup" id="delete_backup" class="btn btn-danger btn-sm pull-right" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="subscription-tab">

						<div style="padding: 9px 20px 16px;">
<?php if (empty($subs['url']) && empty($subs['token'])) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px;" class="panel panel-primary">
								<div class="panel-body">
									<div class="text-muted"><span class="text-info glyphicon glyphicon-info-sign" style="padding-right: 12px;"></span>Register for a <a target="_blank" href="https://kinobi.io/kinobi/">Kinobi subscription</a> to provide patch definitions.</div>
								</div>
							</div>
<?php } elseif (!isset($subs_resp['expires'])) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>Invalid token. Please ensure the Server URL and Token values are entered exactly as they were provided.</div>
								</div>
							</div>
<?php } elseif ($subs_resp['expires'] > $subs_resp['timestamp'] + (14*24*60*60)) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $subs_resp['type']; ?> subscription expires: <?php echo date('M j, Y', $subs_resp['expires']); ?>.</div>
								</div>
							</div>
<?php } elseif ($subs_resp['expires'] > $subs_resp['timestamp']) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning">
								<div class="panel-body">
									<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $subs_resp['type']; ?> subscription expires: <?php echo date('M j, Y', $subs_resp['expires']); ?>. <a target="_blank" href="<?php echo $subs_resp['renew']; ?>">Click here to renew</a>.</div>
								</div>
							</div>
<?php } else { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $subs_resp['type']; ?> subscription expired: <?php echo date('M j, Y', $subs_resp['expires']); ?>. <a target="_blank" href="<?php echo $subs_resp['renew']; ?>">Click here to renew</a>.</div>
								</div>
							</div>
<?php }
if (!$cloud) { ?>

							<h5 id="subs_url_label"><strong>Server URL</strong> <small>URL for the subscription server.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" name="subs_url" id="subs_url" class="form-control input-sm" onFocus="validSubscribe();" onKeyUp="validSubscribe();" onBlur="validSubscribe();" placeholder="[Required]" value="<?php echo $subs['url']; ?>" <?php echo (empty($pdo_error) ? "" : "disabled") ?>/>
							</div>

							<h5 id="subs_token_label"><strong>Token</strong> <small>Auth token for the subscription server.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" name="subs_token" id="subs_token" class="form-control input-sm" onFocus="validSubscribe();" onKeyUp="validSubscribe();" onBlur="validSubscribe();" placeholder="[Required]" value="<?php echo $subs['token']; ?>" <?php echo (empty($pdo_error) ? "" : "disabled") ?>/>
							</div>

							<div class="text-left">
								<button type="submit" name="subscribe" id="subscribe" class="btn btn-primary btn-sm" style="width: 75px;" disabled>Apply</button>
							</div>
						</div>

<?php if (isset($subs_resp['type']) && $subs_resp['type'] == "Server") { ?>
						<hr>

						<div style="padding: 9px 20px 1px; background-color: #f9f9f9;">
							<h5><strong>Check-In Frequency</strong> <small>Frequency at which imported titles are refreshed.</small></h5>
							<div class="form-group" style="max-width: 449px;">
								<select id="subs_refresh" name="subs_refresh" class="form-control input-sm" onChange="subsRefresh(this);" <?php echo (empty($subs_resp) ? "disabled" : ""); ?>>
									<option value="300" <?php echo ($subs['refresh'] == "300" ? "selected" : ""); ?>>Every 5 minutes</option>
									<option value="900" <?php echo ($subs['refresh'] == "900" ? "selected" : ""); ?>>Every 15 minutes</option>
									<option value="1800" <?php echo ($subs['refresh'] == "1800" ? "selected" : ""); ?>>Every 30 minutes</option>
									<option value="3600" <?php echo ($subs['refresh'] == "3600" ? "selected" : ""); ?>>Every 60 minutes</option>
								</select>
							</div>
						</div>

						<hr>
<?php }
} ?>
					</div> <!-- /.tab-pane -->

				</div> <!-- end .tab-content -->
			</form> <!-- end Database form -->
<?php include "inc/footer.php"; ?>