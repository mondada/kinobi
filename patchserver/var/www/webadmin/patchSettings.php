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

if (file_exists("inc/config.php")) {
	include "inc/config.php";
}
include "inc/auth.php";
if (file_exists("inc/functions.php")) {
	include "inc/functions.php";
}
include "inc/patch/functions.php";
include "inc/header.php";

$pdo_connection_status = null;
$subs_resp = array();
$backup_error = null;
$backup_success = null;
$restore_error = null;
$restore_success = null;
$pdo_error = null;
$state_clear = false;

$netsus = (isset($conf) ? (strpos(file_get_contents("inc/header.php"), "NetSUS 4") !== false ? 4 : 5) : 0);

// Service & Dashboard
$service = (isset($conf) && $netsus > 4 ? $conf->getSetting("patch") == "enabled" : true);
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
if (isset($_POST['dsn_save'])) {
	if ($_POST['dsn_prefix'] == "sqlite") {
		$dsn_parent_dir = dirname($_POST['dsn_dbpath']);
		if (!is_dir($dsn_parent_dir) && !@mkdir($dsn_parent_dir, 0755, true)) {
			$e = error_get_last();
			$pdo_error = str_replace("mkdir()", $dsn_parent_dir, $e['message']);
		} elseif (!is_writable($dsn_parent_dir)) {
			$pdo_error = $_POST['dsn_dbpath'] . ": Permission denied";
		} elseif (!is_readable($_POST['dsn_dbpath']) || is_dir($_POST['dsn_dbpath'])) {
			$pdo_error = "SQLSTATE[HY000] [14] unable to open database file";
		} elseif (!is_writable($_POST['dsn_dbpath'])) {
			$pdo_error = "SQLSTATE[HY000]: General error: 8 attempt to write a readonly database";
		}
	}

	if ($_POST['dsn_prefix'] == "mysql") {
		$dsn = $_POST['dsn_prefix'] . ":host=" . $_POST['dsn_host'] . ";port=" . $_POST['dsn_port'] . ";dbname=" . $_POST['dsn_dbname'];
		$username = $_POST['dsn_dbuser'];
		$passwd = $_POST['dsn_dbpass'];

		try {
			$pdo = new PDO($dsn, $username, $passwd);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			$pdo_error = $e->getMessage();
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
				"username" => $_POST['dsn_dbuser'],
				"passwd" => openssl_encrypt($_POST['dsn_dbpass'], "AES-128-CTR", $kinobi->getSetting("uuid"), 0, substr(md5($_POST['dsn_dbuser']), 0, 16))
			)
		);
	} else {
		$db = array(
			"dsn" => array(
				"prefix" => $_POST['dsn_prefix'],
				"dbpath" => $_POST['dsn_dbpath'],
				"host" => $_POST['dsn_host'],
				"port" => $_POST['dsn_port'],
				"dbname" => $_POST['dsn_dbname']
			),
			"username" => $_POST['dsn_dbuser']
		);

		$pdo = false;
	}
}

if (!isset($db)) {
	include "inc/patch/database.php";
	unset($db['passwd']);
}

if (empty($pdo_error)) {
	$pdo_connection_status = "Connected to: " . ($db['dsn']['prefix'] == "sqlite" ? $db['dsn']['dbpath'] : $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS));
	if (isset($_POST['dsn_save']) && $netsus == 0) {
		$pdo_connection_status .= '. <a href="logout.php">Log Out</a> for changes to take effect.';
	}
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
	$backup_success = "Backup Completed Successfully.";
}

// Delete backup
if (isset($_POST['del_backup'])) {
	if (unlink($backup['path']."/".$_POST['del_backup'])) {
		$restore_success = "Backup deleted successfully.";
	} else {
		$restore_error = "Falied to delete backup.";
	}
}

// Upload backup
if (isset($_POST['upload_backup']) && isset($_FILES['backup_file']['name'])) {
	if ($_FILES['backup_file']['error'] > 0) {
		$restore_error = $_FILES['backup_file']['error'].".";
	} elseif ($_FILES['backup_file']['type'] != "application/x-gzip") {
		$restore_error = "Invalid file type '".$_FILES['backup_file']['type']."'.";
	} else {
		// To Do: Add string replace to remove spaces in filename
		$filename = basename($_FILES['backup_file']['name']);
		if (move_uploaded_file($_FILES['backup_file']['tmp_name'], $backup['path']."/".$filename)) {
			$restore_success = "File uploaded successfully.";
		} else {
			$restore_error = "Failed to move file to ".$backup['path'].".";
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
if (isset($_POST['restore_backup'])) {
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
			"DROP TABLE IF EXISTS api;".PHP_EOL.
			"DROP TABLE IF EXISTS overrides;";
		$pdo->exec($sql);
	}
	$sql = gzfile($backup['path']."/".$_POST['restore_backup']);
	$sql = implode($sql);
	try {
		$pdo->exec($sql);
	} catch(PDOException $e) {
		$restore_error = $e->getMessage();
	}
	if (empty($restore_error)) {
		$restore_success = "Restored " . $_POST['restore_backup'] . ".";
		$restore_success .= ($netsus == 0 ? ' <a href="logout.php">Log Out</a> for changes to take effect.' : '');
		include "inc/patch/database.php";
	}
}

// Subscription
if (isset($_POST['subscribe'])) {
	$subs = getSettingSubscription($pdo);
	if (!empty($_POST['subs_url']) && !empty($_POST['subs_token'])) {
		$subs_test = fetchJsonArray($_POST['subs_url'], $_POST['subs_token']);
		$subs_success = isset($subs_test['type']);
	} else {
		$subs_success = true;
	}
	if ($subs['url'] != $_POST['subs_url'] || $subs['token'] != $_POST['subs_token']) {
		if ($subs_success) {
			$kinobi->setSetting("eula_accepted", false);
			$state_clear = true;
		}
	}
	$subs['url'] = (empty($_POST['subs_url']) ? null : $_POST['subs_url']);
	$subs['token'] = (empty($_POST['subs_token']) ? null : $_POST['subs_token']);
	setSettingSubscription($pdo, $subs);
}

// Create User
if (isset($_POST['create_user'])) {
	createUser($pdo, $_POST['user_username'], hash("sha256", $_POST['user_password']));
	if ($_POST['user_privileges'] == 1) {
		setSettingUser($pdo, $_POST['user_username'], "web", true);
		setSettingUser($pdo, $_POST['user_username'], "api", 1);
	}
	if ($_POST['user_privileges'] == 0) {
		setSettingUser($pdo, $_POST['user_username'], "api", 0);
	}
	if ($_POST['user_access'] == 0) {
		setSettingUser($pdo, $_POST['user_username'], "disabled", 1);
	}
	if (isset($_POST['user_change'])) {
		setSettingUser($pdo, $_POST['save_user'], "reset", 1);
	}
	if (empty($_POST['user_expires'])) {
		setSettingUser($pdo, $_POST['user_username'], "expires", null);
	} else {
		setSettingUser($pdo, $_POST['user_username'], "expires", (int)date("U",strtotime($_POST['user_expires'])));
	}
	if (isset($_POST['user_token'])) {
		setSettingUser($pdo, $_POST['user_username'], "token", bin2hex(openssl_random_pseudo_bytes(16)));
	}
}

// Save User
if (isset($_POST['save_user'])) {
	if (!empty($_POST['user_password'])) {
		setSettingUser($pdo, $_POST['save_user'], "password", hash("sha256", $_POST['user_password']));
	}
	if (isset($_POST['user_privileges'])) {
		if ($_POST['user_privileges'] === "1") {
			setSettingUser($pdo, $_POST['save_user'], "web", true);
			setSettingUser($pdo, $_POST['save_user'], "api", 1);
		}
		if ($_POST['user_privileges'] === "0") {
			setSettingUser($pdo, $_POST['save_user'], "web", null);
			setSettingUser($pdo, $_POST['save_user'], "api", 0);
		}
		if ($_POST['user_privileges'] === "none") {
			setSettingUser($pdo, $_POST['save_user'], "web", null);
			setSettingUser($pdo, $_POST['save_user'], "api", null);
		}
	}
	if (isset($_POST['user_access'])) {
		if ($_POST['user_access'] === "1") {
			setSettingUser($pdo, $_POST['save_user'], "disabled", null);
		}
		if ($_POST['user_access'] === "0") {
			setSettingUser($pdo, $_POST['save_user'], "disabled", 1);
		}
	}
	if (isset($_POST['user_change'])) {
		setSettingUser($pdo, $_POST['save_user'], "reset", 1);
	} else {
		setSettingUser($pdo, $_POST['save_user'], "reset", null);
	}
	if (empty($_POST['user_expires'])) {
		setSettingUser($pdo, $_POST['save_user'], "expires", null);
	} else {
		setSettingUser($pdo, $_POST['save_user'], "expires", (int)date("U",strtotime($_POST['user_expires'])));
	}
	if (isset($_POST['user_token'])) {
		setSettingUser($pdo, $_POST['save_user'], "token", bin2hex(openssl_random_pseudo_bytes(16)));
	}
	if ($_POST['user_username'] != $_POST['save_user']) {
		renameUser($pdo, $_POST['save_user'], $_POST['user_username']);
	}
	if ($_POST['save_user'] == $_SESSION['username']) {
		$_SESSION['username'] = $_POST['user_username'];
	}
}

// Delete  User
if (isset($_POST['del_user'])) {
	deleteUser($pdo, $_POST['del_user']);
}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

// SQLite Databases
unset($db['passwd']);
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
	$scheduled = array_map("intval", explode(",", $schedule_str));
} else {
	$scheduled = array();
}

// Subscription
$subs = getSettingSubscription($pdo);
if (!empty($subs['url']) && !empty($subs['token'])) {
	$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
	$subs_resp['endpoint'] = isset($subs_resp['endpoint']);
	$subs_resp['expires'] = (isset($subs_resp['expires']) ? (int)$subs_resp['expires'] : 0);
	unset($subs_resp['auth']);
	unset($subs_resp['source']);
	unset($subs_resp['functions']);
	unset($subs_resp['upload']);
	unset($subs_resp['import']);
}
if (isset($subs_resp['renew'])) {
	if ($cloud) {
		$subs_resp['renew'] = $subs_resp['renew'] . "?register=cloud";
	} else {
		$subs_resp['renew'] = $subs_resp['renew'] . "?register=self";
	}
}
$subs_type = (isset($subs_resp['type']) ? $subs_resp['type'] : null);
$eula_accepted = $kinobi->getSetting("eula_accepted");

// Users
$users = getSettingUsers($pdo, true);
$web_users = array();
$api_users = array();
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
if (empty($api_users)) {
	$api['reqauth'] = false;
	setSettingApi($pdo, $api);
}
?>
				<!-- Awesome Bootstrap Checkbox -->
				<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css" type="text/css"/>

				<!-- DateTime Picker -->
				<link rel="stylesheet" href="theme/bootstrap-datetimepicker.css" />

				<!-- DataTables -->
				<link rel="stylesheet" href="theme/dataTables.bootstrap.css" type="text/css"/>
				<link rel="stylesheet" href="theme/buttons.bootstrap.css" type="text/css"/>

				<!-- bootstrap-select -->
				<link rel="stylesheet" href="theme/bootstrap-select.css" type="text/css"/>

				<!-- Bootstrap Toggle -->
				<link rel="stylesheet" href="theme/bootstrap-toggle.css" type="text/css"/>

				<!-- Custom styles for this project -->
				<link rel="stylesheet" href="theme/kinobi.css" type="text/css">

				<!-- Custom styles for this page -->
				<style>
					#dashboard-spacer label,
					#dashboard-spacer label small {
						color: transparent;
					}
					#dashboard-spacer label::before {
						border-color: transparent;
					}
<?php if ($netsus) { ?>
					.form-inline {
						width: auto;
					}
					@media(min-width:768px) {
						#page-title-wrapper {
							left: 220px;
						}
						#wrapper.toggled #page-title-wrapper {
							margin-left: 0;
							margin-right: 0;
						}
						.dataTables-footer {
							left: 220px;
						}
<?php if ($netsus == 4) { ?>
						#page-content-wrapper {
							padding-left: 220px;
						}
<?php } ?>
					}
<?php } ?>
				</style>

				<!-- Moment.js -->
				<script type="text/javascript" src="scripts/moment/moment.min.js"></script>

				<!-- Bootstrap Transitions -->
				<script type="text/javascript" src="scripts/bootstrap/transition.js"></script>

				<!-- Bootstrap Collapse -->
				<script type="text/javascript" src="scripts/bootstrap/collapse.js"></script>

				<!-- DateTime Picker -->
				<script type="text/javascript" src="scripts/datetimepicker/bootstrap-datetimepicker.min.js"></script>

				<!-- DataTables -->
				<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
				<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
				<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
				<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

				<!-- Bootstrap Add Clear -->
				<script type="text/javascript" src="scripts/bootstrap-add-clear/bootstrap-add-clear.min.js"></script>

				<!-- bootstrap-select -->
				<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>

				<!-- Bootstrap Toggle -->
				<script type="text/javascript" src="scripts/toggle/bootstrap-toggle.min.js"></script>

				<script type="text/javascript">
					var current_user = "<?php echo (isset($_SESSION['username']) ? $_SESSION['username'] : ""); ?>";
					var netsus = <?php echo $netsus; ?>;
					var service = <?php echo json_encode($service); ?>;
					var dashboard = <?php echo json_encode($dashboard); ?>;
					var cloud = <?php echo json_encode($cloud); ?>;

					var users_json = <?php echo json_encode(array_values($users)); ?>;

					var pdo_error = "<?php echo htmlentities($pdo_error); ?>";
					var pdo_connection_status = '<?php echo $pdo_connection_status; ?>';;
					var db_json = <?php echo json_encode($db); ?>;
					var sqlite_dir = '<?php echo $sqlite_dir; ?>';
					var sqlite_dbs_json = <?php echo json_encode($sqlite_dbs); ?>;

					var scheduled_json = <?php echo json_encode($scheduled); ?>;
					var backup_json = <?php echo json_encode($backup); ?>;
					var backup_error = '<?php echo htmlentities($backup_error); ?>';
					var backup_success = '<?php echo $backup_success; ?>';

					var backups_json = <?php echo json_encode($backups); ?>;
					var restore_error = '<?php echo htmlentities($restore_error); ?>';
					var restore_success = '<?php echo $restore_success; ?>';

					var subs_json = <?php echo json_encode($subs); ?>;
					var subs_resp_json = <?php echo json_encode($subs_resp); ?>;
					var subs_type = "<?php echo $subs_type; ?>";
					var eula_accepted = <?php echo json_encode($eula_accepted); ?>;
					var state_clear = <?php echo json_encode($state_clear); ?>;
					var api_json = <?php echo json_encode($api); ?>;
				</script>

				<script type="text/javascript" src="scripts/kinobi/patchSettings.js"></script>

				<div id="page-title-wrapper">
					<div id="page-title">
						<ol class="breadcrumb">
							<li class="active">&nbsp;</li>
						</ol>

						<div class="row">
							<div class="col-xs-9">
								<h2 id="heading">Settings</h2>
							</div>
							<div class="col-xs-3 text-right hidden">
								<input id="service-status" type="checkbox" data-toggle="toggle" data-size="small">
							</div>
						</div>
					</div>

					<div class="alert-wrapper">
						<div id="dashboard-wrapper" class="checkbox checkbox-primary hidden">
							<input id="dashboard-display" class="styled" type="checkbox">
							<label><strong>Show in Dashboard</strong> <small>Display service status in the NetSUS dashboard.</small></label>
						</div>
					</div>

					<div class="nav-tabs-wrapper">
						<ul class="nav nav-tabs nav-justified" id="top-tabs">
							<li><a id="users-tab-link" href="#users-tab" role="tab" data-toggle="tab"><small>Users</small></a></li>
							<li><a id="database-tab-link" href="#database-tab" role="tab" data-toggle="tab"><small><span id="database-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Database</small></a></li>
							<li><a id="backup-tab-link" href="#backup-tab" role="tab" data-toggle="tab"><small><span id="backup-tab-icon" class="glyphicon glyphicon-warning-sign hidden-xs hidden"></span> Backup</a></small></li>
							<li><a href="#restore-tab" role="tab" data-toggle="tab"><small>Restore</small></a></li>
							<li><a id="subscription-tab-link" href="#subscription-tab" role="tab" data-toggle="tab"><small><span id="subscription-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Subscription</small></a></li>
						</ul>
					</div>
				</div>

				<!-- Page Title Spacer -->
				<div style="margin-top: -18px;">
					<div style="height: 79px;"></div>
					<div style="padding: 0 20px;">
						<div id="dashboard-spacer" class="checkbox checkbox-primary hidden">
							<input class="styled" type="checkbox">
							<label><strong>Show in Dashboard</strong> <small>Display service status in the NetSUS dashboard.</small></label>
						</div>
					</div>
					<div style="height: 58px;"></div>
				</div>

				<div class="tab-content">
					<div class="tab-pane fade in" id="users-tab">
						<div class="page-content">
							<div class="text-muted"><small>Kinobi user accounts and privileges.</small></div>
						</div>

						<table id="users" class="table table-hover" style="min-width: 768px; width: 100%; border-bottom: 1px solid #ddd;">
							<thead>
								<tr>
									<th>Enable</th>
									<th>Username</th>
									<th>Token</th>
									<th>Expiry</th>
									<th>Access</th>
									<th><!-- Delete --></th>
								</tr>
							</thead>
							<tbody>
							</tobdy>
						</table>

						<form method="post" id="users-form">
							<!-- User Modal -->
							<div class="modal fade" id="user-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="user-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="user-label">New User</h3>
										</div>
										<div class="modal-body">
											<input type="hidden" name="create_user" id="create-user" disabled/>

											<input type="hidden" name="save_user" id="save-user" disabled/>

											<div class="form-group">
												<label class="control-label" for="user-username">User Name <small>Username for the account.</small></label>
												<input type="text" class="form-control input-sm" name="user_username" id="user-username" placeholder="[Required]"/>
												<span id="user-username-help" class="help-block hidden"><small>Duplicate</small></span>
											</div>

											<!-- Server Only -->
											<div class="form-group hidden">
												<label class="control-label" for="user-privileges">Privilege Set <small>Set of privileges to grant the account.</small></label>
												<select name="user_privileges" id="user-privileges" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value="none">No Access</option>
													<option value="0">Read Endpoints</option>
													<option value="1" selected>Full Access</option>
												</select>
											</div>
											<!-- / end Server Only -->

											<div class="form-group">
												<label class="control-label" for="user-access">Access Status <small>Access status of the account ("enabled" or "disabled").</small></label>
												<select name="user_access" id="user-access" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value="0">Disabled</option>
													<option value="1" selected>Enabled</option>
												</select>
											</div>

											<div class="form-group">
												<label class="control-label" for="user-password">Password <small>Password for the account.</small></label>
												<input type="password" class="form-control input-sm" name="user_password" id="user-password" placeholder="[Required]"/>
												<span id="user-password-help" class="help-block hidden"><small>Did not match</small></span>
											</div>

											<div class="form-group">
												<label class="control-label" for="user-verify">Verify Password</label>
												<input type="password" class="form-control input-sm" name="user_verify" id="user-verify" placeholder="[Required]"/>
												<span id="user-verify-help" class="help-block hidden"><small>Did not match</small></span>
											</div>

											<div class="checkbox checkbox-primary">
												<input type="checkbox" class="styled" name="user_change" id="user-reset-passwd">
												<label><strong>Force Password Change</strong> <small class="text-muted">Force user to change password at next login.</small></label>
											</div>

											<!-- Server Only -->
											<div class="form-group hidden">
												<label class="control-label" for="user-expires">Expiry <small>Date that this user account expires.</small></label>
												<div class="input-group date" id="user-expires-datetimepicker">
													<span class="input-group-addon input-sm">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
													<input type="text" class="form-control input-sm" name="user_expires" id="user-expires" placeholder="[Optional]"/>
												</div>
											</div>
											<!-- / end Server Only -->

											<div class="checkbox checkbox-primary">
												<input type="checkbox" class="styled" name="user_token" id="user-token" value="true">
												<label><strong>Generate Token</strong> <small class="text-muted">Generate an endpoint authentication token for this user.</small></label>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="button" id="save-user-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete User Modal -->
							<div class="modal fade" id="del-user-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-user-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="del-user-label">Delete User?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-user-msg">Are you sure you want to delete this user?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_user" id="del-user-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end users-form -->
					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="database-tab">
						<div class="page-content">
							<div id="database-success-alert" class="alert alert-success" role="alert">
								<span class="glyphicon glyphicon-ok-sign"></span><span id="database-success-msg" class="text-muted">SUCCESS</span>
							</div>

							<div id="database-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="database-error-msg" class="text-muted">ERROR</span>
							</div>

							<form method="post" id="dsn-form">
								<input type="hidden" name="dsn_save" id="dsn-save" disabled/>

								<div class="form-group">
									<label class="control-label" for="dsn-prefix">Database Type <small>Database connection type to use.</small></label>
									<select name="dsn_prefix" id="dsn-prefix" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
										<option value="sqlite">SQLite</option>
										<option value="mysql">MySQL</option>
									</select>
								</div>

								<div id="dsn-sqlite" class="hidden">
									<input type="hidden" name="dsn_dbpath" id="dsn-dbpath"/>

									<label class="control-label" for="dsn-dbfile">Database <small>SQLite database file.</small></label>
									<div class="form-group">
										<select id="dsn-dbfile-select" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/></select>
									</div>

									<div class="form-group has-feedback hidden">
										<input type="text" class="form-control input-sm" id="dsn-dbfile"/>
									</div>
								</div>

								<div id="dsn-mysql" class="hidden">
									<label class="control-label" for="dsn-host-port">Hostname &amp; Port <small>Hostname or IP address, and port number for the MySQL server.</small></label>
									<div id="dsn-host-port" class="text-center">
										<div class="form-group has-feedback pull-left" style="display: inline-block; width: 75%;">
											<input type="text" class="form-control input-sm" name="dsn_host" id="dsn-host"/>
										</div>

										<span class="text-muted text-center" style="display: inline-block; width: 2%; height: 30px; padding-top: 4px;">:</span>

										<div class="form-group has-feedback pull-right" style="display: inline-block; width: 22%;">
											<input type="text" class="form-control input-sm" name="dsn_port" id="dsn-port"/>
										</div>
									</div>

									<div class="form-group">
										<label class="control-label" for="dsn-dbname">Database <small>MySQL database used for patch definitions.</small></label>
										<input type="text" class="form-control input-sm" name="dsn_dbname" id="dsn-dbname"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="dsn-dbuser">Username <small>Username used to connect to the MySQL server.</small></label>
										<input type="text" class="form-control input-sm" name="dsn_dbuser" id="dsn-dbuser"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="dsn-dbpass">Password <small>Password used to authenticate with the MySQL server.</small></label>
										<input type="password" class="form-control input-sm" name="dsn_dbpass" id="dsn-dbpass"/>
									</div>
								</div>

								<button type="button" id="dsn-save-btn" class="btn btn-primary btn-sm" style="width: 75px; margin-bottom: 8px;">Save</button>
							</div>
						</form><!-- end dsn-form -->
					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="backup-tab">
						<div class="page-content">
							<div id="schedule-warning-alert" class="alert alert-warning hidden" role="alert">
								<span class="glyphicon glyphicon-warning-sign"></span><span id="schedule-warning-msg" class="text-muted">No backups scheduled.</span>
							</div>

							<label class="control-label">Backup Schedule <small>Days of the week for an automatic backup to run.<br><strong>Note:</strong> Backups will occur at 12:00 AM (this server's local time) on the specified days.</small></label>
							<table style="width: 100%;">
								<tr>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="0"/>
											<label for="sun"> Sun<span class="hidden-xs">day</span></label>
										</div>
									</td>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="1"/>
											<label for="mon"> Mon<span class="hidden-xs">day</span></label>
										</div>
									</td>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="2"/>
											<label for="tue"> Tue<span class="hidden-xs">sday</span></label>
										</div>
									</td>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="3"/>
											<label for="wed"> Wed<span class="hidden-xs">nesday</span></label>
										</div>
									</td>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="4"/>
											<label for="thu"> Thu<span class="hidden-xs">rsday</span></label>
										</div>
									</td>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="5"/>
											<label for="fri"> Fri<span class="hidden-xs">day</span></label>
										</div>
									</td>
									<td style="width: 14%;">
										<div class="checkbox checkbox-primary checkbox-inline">
											<input name="schedule" class="styled" type="checkbox" value="6"/>
											<label for="sat"> Sat<span class="hidden-xs">urday</span></label>
										</div>
									</td>
								</tr>
							</table>
						</div>

						<div class="page-content-alt">
							<label class="control-label" for="backup-retention">Backup Retention <small>Number of backup archives to be retained on the server.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="backup-retention" placeholder="[1 - 30]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>
						</div>

 						<div class="page-content">
							<div id="backup-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="backup-error-msg" class="text-muted">ERROR</span>
							</div>

							<div id="backup-success-alert" class="alert alert-success hidden" role="alert">
								<span class="glyphicon glyphicon-ok-sign"></span><span id="backup-success-msg" class="text-muted">SUCCESS</span>
							</div>

							<label class="control-label">Manual Backup <small>Perform a manual backup of the database.</small></label>
							<div class="text-left">
								<button type="button" id="manual-backup-btn" class="btn btn-primary btn-sm" style="width: 75px; margin-bottom: 8px;" data-toggle="modal" data-target="#backup-modal">Backup</button>
							</div>
						</div>

						<form method="post" id="backup-form">
							<!-- Manual Backup Modal -->
							<div class="modal fade" id="backup-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="backup-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="backup-label">Manual Backup</h3>
										</div>
										<div class="modal-body">
											<div class="form-group">
												<label for="title-current">Backup Format <small>Select the format for the database backup.<br><strong>Note:</strong> The backup may only be restored to the corresponding database type.</small></label>
												<select name="backup_type" id="backup-type" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value="sqlite">SQLite</option>
													<option value="mysql">MySQL</option>
												</select>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="backup" class="btn btn-primary btn-sm pull-right">Backup</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end backup-form -->
					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="restore-tab">
						<div class="page-content">
							<div id="restore-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="restore-error-msg" class="text-muted">ERROR</span>
							</div>

							<div id="restore-success-alert" class="alert alert-success hidden" role="alert">
								<span class="glyphicon glyphicon-ok-sign"></span><span id="restore-success-msg" class="text-muted">SUCCESS</span>
							</div>

							<label class="control-label" for="backups">Available Backups</label>
						</div>

						<table id="backups" class="table table-hover" style="min-width: 768px; width: 100%; border-bottom: 1px solid #ddd;">
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
							</tobdy>
						</table>

						<form method="post" id="restore-form" enctype="multipart/form-data">
							<!-- Backup Path Modal -->
							<div class="modal fade" id="backup-path-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="backup-path-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="backup-path-label">Backup Path</h3>
										</div>
										<div class="modal-body">
											<label class="control-label" for="backup-path">Backup Path <small>Location on this server to save backups.<br><strong>Note:</strong> The web server user requires write access for the backup path.</small></label>
											<div class="form-group">
												<input type="text" class="form-control input-sm" name="backup_path" id="backup-path" placeholder="[Required]"/>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="save_backup_path" id="save-backup-path-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Upload Modal -->
							<div class="modal fade" id="upload-backup-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="upload-backup-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="upload-backup-label">Upload Backup</h3>
										</div>
										<div class="modal-body">
											<label class="control-label" for="backup-file">Archive <small>Select a backup archive file (gzipped SQLite or MySQL dump file) to add to the list of available backups.</small></label>
											<div class="form-group">
												<input type="file" class="form-control input-sm" name="backup_file" id="backup-file"/>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="upload_backup" id="upload-backup-btn" class="btn btn-primary btn-sm pull-right">Upload</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Restore Backup Modal -->
							<div class="modal fade" id="restore-backup-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="restore-backup-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="restore-backup-label">Restore Backup?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="restore-backup-msg">Are you sure you want to restore this backup?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="restore_backup" id="restore-backup-btn" class="btn btn-primary btn-sm pull-right">Restore</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Backup Modal -->
							<div class="modal fade" id="del-backup-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-backup-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="del-backup-label">Delete Backup?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-backup-msg">Are you sure you want to delete this backup?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_backup" id="del-backup-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end restore-form -->
					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="subscription-tab">
						<div class="page-content">
							<div id="subs-info-alert" class="alert alert-info hidden" role="alert">
								<span class="glyphicon glyphicon-info-sign"></span><span id="subs-info-msg" class="text-muted">INFO</span>
							</div>

							<div id="subs-success-alert" class="alert alert-success hidden" role="alert">
								<span class="glyphicon glyphicon-ok-sign"></span><span id="subs-success-msg" class="text-muted">SUCCESS</span>
							</div>

							<div id="subs-warning-alert" class="alert alert-warning hidden" role="alert">
								<span class="glyphicon glyphicon-warning-sign"></span><span id="subs-warning-msg" class="text-muted">WARNING</span>
							</div>

							<div id="subs-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="subs-error-msg" class="text-muted">ERROR</span>
							</div>

							<!-- Disable for cloud -->
							<form method="post" id="subscription-form">
								<input type="hidden" name="subscribe" id="subscribe" disabled/>

								<div class="form-group">
									<label class="control-label" for="subs-url">Server URL <small>URL for the subscription server.</small></label>
									<input type="text" class="form-control input-sm" name="subs_url" id="subs-url" placeholder="[Required]"/>
								</div>

								<div class="form-group">
									<label class="control-label" for="subs-token">Token <small>Auth token for the subscription server.</small></label>
									<input type="text" class="form-control input-sm" name="subs_token" id="subs-token" placeholder="[Required]"/>
								</div>

								<div class="text-left">
									<button type="button" id="subscribe-btn" class="btn btn-primary btn-sm" style="width: 75px; margin-bottom: 8px;">Apply</button>
								</div>
							</form><!-- end subscription-form -->
							<!-- end Disable for cloud -->
						</div>

						<!-- Exclude for server -->
						<div class="page-content-alt hidden">
							<label class="control-label" for="subs-refresh">Check-In Frequency <small>Frequency at which imported titles are refreshed.</small></label>
							<div class="form-group">
								<select id="subs-refresh" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
									<!-- <option value="300">Every 5 minutes</option> -->
									<option value="900">Every 15 minutes</option>
									<option value="1800">Every 30 minutes</option>
									<option value="3600">Every 60 minutes</option>
								</select>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>
						</div>
						<!-- end Exclude for server -->

						<!-- Server only -->
						<div class="page-content-alt hidden">
							<div class="checkbox checkbox-primary">
								<input id="api-req-auth" class="styled" type="checkbox"/>
								<label><strong>Require Endpoint Authentication</strong><br><small>Require authentication for API endpoints.<br><strong>Note:</strong> Jamf Pro does not currently support authentication for reading from external patch sources.</small></label>
							</div>
						</div>
						<!-- end Server only -->

						<!-- Subscription only -->
						<div class="page-content hidden">
							<div class="checkbox checkbox-primary">
								<input id="api-auto-enable" class="styled" type="checkbox">
								<label><strong>API Auto-Enable</strong><br><small>Automatically enable items imported via the API.</small></label>
							</div>
						</div>
						<!-- end Subscription only -->
					</div> <!-- /.tab-pane -->
				</div> <!-- end .tab-content -->

				<!-- License Modal -->
				<div class="modal fade" id="license-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header text-center"></div>
							<div class="modal-body">
								<div id="license-file" class="well well-sm" style="max-height: 254px; overflow-y: scroll"></div>

								<div class="checkbox checkbox-primary hidden">
									<input id="license-agree" class="styled" type="checkbox"/>
									<label>I have read and accepted the terms of the license agreement.</label>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" data-dismiss="modal" id="license-close-btn" class="btn btn-default btn-sm pull-right">Close</button>
							</div>
						</div><!-- /.modal-content -->
					</div><!-- /.modal-dialog -->
				</div><!-- /.modal -->
<?php include "inc/footer.php"; ?>