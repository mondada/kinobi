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

// Re-direct to HTTPS if connecting via HTTP
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
	header("Location: https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
		<meta http-equiv="pragma" content="no-cache">
		<meta http-equiv="expires" content="0">

		<title><?php echo (isset($title) ? $title : "Kinobi"); ?></title>

		<!-- Bootstrap -->
		<link href="theme/bootstrap.css" rel="stylesheet" media="all">

		<!-- Roboto Font -->
		<link href="theme/roboto.font.css" rel="stylesheet" type="text/css">

		<!-- Custom styles for this project -->
		<link href="theme/custom.css" rel="stylesheet" type="text/css">

		<!-- JQuery -->
		<script type="text/javascript" src="scripts/jquery/jquery-2.2.4.js"></script>

		<!-- Bootstrap JavaScript -->
		<script type="text/javascript" src="scripts/bootstrap.min.js"></script>
	</head>

	<style>
		body {
			background-color: #292929;
		}
	</style>

	<body>

	<div id="wrapper">

		<!-- Page Content -->
		<div class="container-fluid" id="page-content-wrapper">

<?php
session_start();
// Settings
if (file_exists("inc/config.php")) {
	include "inc/config.php";
}
include "inc/patch/functions.php";

$cloud = $kinobi->getSetting("cloud");

// Re-configure Database Connection
if (isset($_POST['apply_db']) && isset($_POST['db_token']) && $kinobi->getSetting("db_token") !== null && $_POST['db_token'] == $kinobi->getSetting("db_token")) {
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

// Delete temporary database token
$kinobi->deleteSetting("db_token");

$db = $kinobi->getSetting("pdo");

if (!isset($pdo_error)) {
	include "inc/patch/database.php";
}

// License Agreement
if (isset($_POST['eula_next']) && isset($_POST['setup_token']) && $kinobi->getSetting("setup_token") !== null && $_POST['setup_token'] == $kinobi->getSetting("setup_token")) {
	$kinobi->setSetting("eula_accepted", true);
	$modal = "subscription";
}

// Subscription
if (isset($_POST['subs_prev']) && isset($_POST['setup_token']) && $kinobi->getSetting("setup_token") !== null && $_POST['setup_token'] == $kinobi->getSetting("setup_token")) {
	$kinobi->deleteSetting("eula_accepted");
	$modal = "license";
}

if (empty($pdo_error) && isset($_POST['subs_next']) && isset($_POST['setup_token']) && $kinobi->getSetting("setup_token") !== null && $_POST['setup_token'] == $kinobi->getSetting("setup_token")) {
	$subs = getSettingSubscription($pdo);

	$subs['url'] = (empty($_POST['subs_url']) ? null : $_POST['subs_url']);
	$subs['token'] = (empty($_POST['subs_token']) ? null : $_POST['subs_token']);

	setSettingSubscription($pdo, $subs);
	$modal = "adduser";

	if (null !== $subs['url'] && null !== $subs['token']) {
		$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
		if (!isset($subs_resp['expires'])) {
			$subs_err = "Please ensure the Server URL and Token values are entered exactly as they were provided.";
			$modal = "subscription";
		}
	}
}

// Create User Account
if (isset($_POST['user_prev']) && isset($_POST['setup_token']) && $kinobi->getSetting("setup_token") !== null && $_POST['setup_token'] == $kinobi->getSetting("setup_token")) {
	$modal = "subscription";
}

if (isset($_POST['user_next']) && isset($_POST['setup_token']) && $kinobi->getSetting("setup_token") !== null && $_POST['setup_token'] == $kinobi->getSetting("setup_token")) {
	createUser($pdo, $_POST['add_user'], hash("sha256", $_POST['add_pass']));
	setSettingUser($pdo, $_POST['add_user'], "web", true);
}

// Delete temporary setup token
$kinobi->deleteSetting("setup_token");

$isAuth = false;

if (empty($pdo_error)) {
	$sizeof_users = $pdo->query("SELECT COUNT(username) FROM users WHERE web = 1;")->fetch(PDO::FETCH_COLUMN);

	if ($sizeof_users == 0) {
		unset($_SESSION['isAuthUser']);
	
		$setup_token = bin2hex(openssl_random_pseudo_bytes(16));

		$kinobi->setSetting("setup_token", $setup_token);

		$subs = getSettingSubscription($pdo);

		if (!isset($modal)) {
			$modal = "license";
		}
	} else {
		$modal = "login";
		
		$amAuthURL = "patchTitles.php";
		if (isset($_SESSION['isAuthUser'])) {
			header("Location: ".$amAuthURL);
		}

		if ((isset($_POST['username'])) && (isset($_POST['password']))) {
			$username = $_POST['username'];
			$_SESSION['username'] = $username;

			$password = hash("sha256", $_POST['password']);

			if (($username != "") && ($password != "")) {
				$users = getSettingUsers($pdo);
				$isAuth = (array_key_exists($username, $users) ? $users[$username]['password'] == $password : false);
				if ($isAuth) {
					$isAuth = (isset($users[$username]['web']) ? $users[$username]['web'] : false);
					if ($isAuth) {
						$isAuth = (isset($users[$username]['expires']) ? $users[$username]['expires'] > time() : true);
						$loginerror = ($isAuth ? null : "User Account Expired");
					} else {
						$loginerror = "Access Denied.";
					}
				} else {
					$loginerror = "Invalid Username or Password.";
				}
			}
		}

		if ($isAuth) {
			$_SESSION['isAuthUser'] = 1;
			$sURL = "patchTitles.php";
			header("Location: ". $sURL);
		}
	}
} else {
	if (!$cloud) {
		unset($_SESSION['isAuthUser']);

		$sqlite_dir = dirname($_SERVER['DOCUMENT_ROOT']) . "/kinobi/db/";
		$sqlite_dbs = array();
		foreach (glob($sqlite_dir . "*") as $sqlite_db) {
			array_push($sqlite_dbs, basename($sqlite_db));
		}

		$db_token = bin2hex(openssl_random_pseudo_bytes(16));
		$kinobi->setSetting("db_token", $db_token);
	}
	$modal = "database";
}
?>
			<script type="text/javascript" src="scripts/patchValidation.js"></script>

			<script>
				$(window).load(function() {
					$('#<?php echo $modal; ?>-modal').modal('show');
				});
			</script>

			<form method="post" name="loginForm" id="login-form">
<?php if (!empty($pdo_error)) { ?>
				<script>
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
							$('#apply_db').prop('disabled', false);
						} else {
							$('#apply_db').prop('disabled', true);
						}
					}
				</script>

				<!-- Database Modal -->
				<div class="modal" id="database-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title">Database Connection Error</h3>
							</div>
							<div class="modal-body" style="padding-bottom: 0px;">
								<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
									<div class="panel-body">
										<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $pdo_error; ?></div>
									</div>
								</div>
<?php if (!$cloud) { ?>
								<input type="hidden" name="db_token" id="db_token" value="<?php echo $db_token; ?>"/>

								<h5 style="margin-top: 0px"><strong>Database Type</strong> <small>Database connection type to use.</small></h5>
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

								<div id="mysql_db" class="<?php echo ($db['dsn']['prefix'] == "mysql" ? "" : "hidden"); ?>">
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
<?php } ?>
							</div>
							<div class="modal-footer">
								<button type="submit" name="retry_db" id="retry_db" class="btn btn-<?php echo ($cloud ? "primary" : "default"); ?> btn-sm pull-<?php echo ($cloud ? "right" : "left"); ?>" style="width: 60px;">Retry</button>
								<button type="submit" name="apply_db" id="apply_db" class="btn btn-primary btn-sm pull-right <?php echo ($cloud ? "hidden" : ""); ?>" style="width: 60px;" disabled>Apply</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->
<?php } ?>
<?php if ($sizeof_users == 0) { ?>
				<script>
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
						if (/^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:/?#[\]@!\$&'\(\)\*\+,;=.]+$/.test(subs_url.value) && /^.{1,255}$/.test(subs_token.value)<?php echo ($cloud ? "" : "|| subs_url.value == \"\" && subs_token.value == \"\""); ?>) {
							hideError(subs_url, 'subs_url_label');
							hideError(subs_token, 'subs_token_label');
							$('#subs_next').prop('disabled', false);
						} else {
							$('#subs_next').prop('disabled', true);
						}
						if (subs_url.value == "" && subs_token.value == "") {
							$('#subs_next').html('Skip');
						} else {
							$('#subs_next').html('Next');
						}
					}

					function validUser() {
						var add_user = document.getElementById('add_user');
						var add_pass = document.getElementById('add_pass');
						var add_verify = document.getElementById('add_verify');
						if (/^([A-Za-z0-9 ._-]){1,64}$/.test(add_user.value)) {
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
						if (/^([A-Za-z0-9 ._-]){1,64}$/.test(add_user.value) && /^.{1,128}$/.test(add_verify.value) && add_verify.value == add_pass.value) {
							$('#user_next').prop('disabled', false);
						} else {
							$('#user_next').prop('disabled', true);
						}
					}

					$(window).load(function() {
						validSubscribe();
					});
				</script>

				<!-- EULA Modal -->
				<div class="modal" id="license-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title">License Agreement</h3>
							</div>
							<div class="modal-body">
								<input type="hidden" name="setup_token" id="setup_token" value="<?php echo $setup_token; ?>"/>

								<div class="well well-sm" style="max-height: 254px; overflow-y: scroll"><?php echo file_get_contents("../../kinobi/LICENSE"); ?></div>
							</div>
							<div class="modal-footer">
								<button type="submit" name="eula_next" id="eula_next" class="btn btn-primary btn-sm pull-right" style="width: 60px;" value="next">Agree</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->

				<!-- Subscription Modal -->
				<div class="modal" id="subscription-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title">Subscription Token</h3>
							</div>
							<div class="modal-body">
<?php if (isset($subs_err)) { ?>
								<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger" align="left">
									<div class="panel-body">
										<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $subs_err; ?></div>
									</div>
								</div>
<?php } ?>
								<input type="hidden" name="setup_token" id="setup_token" value="<?php echo $setup_token; ?>"/>

								<h5 style="margin-top: 0px" id="subs_url_label"><strong>Server URL</strong> <small>URL for the subscription server.</small></h5>
								<div class="form-group has-feedback">
									<input type="text" name="subs_url" id="subs_url" class="form-control input-sm" onFocus="validSubscribe();" onKeyUp="validSubscribe();" onBlur="validSubscribe();" placeholder="[Required]" value="<?php echo (isset($subs['url']) ? $subs['url'] : ($cloud ? "https://patch.kinobi.io/subscription/" : "")); ?>" <?php echo ($cloud ? "readonly" : ""); ?>/>
								</div>

								<h5 id="subs_token_label"><strong>Token</strong> <small>Auth token for the subscription server.</small></h5>
								<div class="form-group has-feedback">
									<input type="text" name="subs_token" id="subs_token" class="form-control input-sm" onFocus="validSubscribe();" onKeyUp="validSubscribe();" onBlur="validSubscribe();" placeholder="[Required]" value="<?php echo (isset($subs['token']) ? $subs['token'] : ""); ?>"/>
								</div>
							</div>
							<div class="modal-footer">
								<button type="submit" name="subs_prev" id="subs_prev" class="btn btn-default btn-sm pull-left" style="width: 60px;" value="prev">Back</button>
								<button type="submit" name="subs_next" id="subs_next" class="btn btn-primary btn-sm pull-right" style="width: 60px;" value="next" disabled>Next</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->

				<!-- Add User Modal -->
				<div class="modal" id="adduser-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title">Create User Account</h3>
							</div>
							<div class="modal-body">
								<input type="hidden" name="setup_token" id="setup_token" value="<?php echo $setup_token; ?>"/>

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
							</div>
							<div class="modal-footer">
								<button type="submit" name="user_prev" id="user_prev" class="btn btn-default btn-sm pull-left" style="width: 60px;" value="prev">Back</button>
								<button type="submit" name="user_next" id="user_next" class="btn btn-primary btn-sm pull-right" style="width: 60px;" value="next">Next</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->
<?php } ?>
				<!-- Login Modal -->
				<div class="modal" id="login-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
					<div class="modal-dialog modal-sm" role="document">
						<div class="modal-content">
							<div class="modal-header" align="center"><img src="images/kinobi-logo.svg" height="30"></div>
							<div class="modal-body">
<?php if (isset($loginerror)) { ?>
								<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
									<div class="panel-body">
										<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $loginerror; ?></div>
									</div>
								</div>
<?php } ?>
								<h5 style="margin-top: 0px"><strong>Username</strong></h5>
								<input type="text" name="username" id="username" class="form-control input-sm" placeholder="[Required]" />

								<h5><strong>Password</strong></h5>
								<input type="password" name="password" id="password" class="form-control input-sm" placeholder="[Required]" />
							</div>
							<div class="modal-footer">
								<button type="submit" name="login" id="login" class="btn btn-primary btn-sm pull-right" style="width: 60px;">Log In</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->
			</form>

		</div>
		<!-- /#page-content-wrapper -->

	</div>
	<!-- /#wrapper -->

</body>

</html>