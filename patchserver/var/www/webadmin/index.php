<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.3.2
 *
 */

// Re-direct to HTTPS if connecting via HTTP
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
	header("Location: https://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
}

session_start();

// Settings
include "inc/patch/functions.php";
include "inc/patch/database.php";

$is_auth = false;
$auth_url = "patchTitles.php";
$subs_type = null;
$login_error = null;
$username = null;
$modal = "login";

// Cloud Configuration
$cloud = $kinobi->getSetting("cloud");

// Delete temporary tokens
$kinobi->deleteSetting("dsn_token");
$kinobi->deleteSetting("setup_token");

$db = $kinobi->getSetting("pdo");

if (empty($pdo_error)) {
	$subs = getSettingSubscription($pdo);
	if (!empty($subs['url']) && !empty($subs['token'])) {
		$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
	}
	$subs_type = (isset($subs_resp['type']) ? $subs_resp['type'] : null);

	$sizeof_users = $pdo->query("SELECT COUNT(username) FROM users WHERE web = 1;")->fetch(PDO::FETCH_COLUMN);


	if ($sizeof_users == 0) {
		unset($_SESSION['isAuthUser']);

		$setup_token = bin2hex(openssl_random_pseudo_bytes(16));
		$kinobi->setSetting("setup_token", $setup_token);

		header("Location: patchSetup.php");
	} else {
		if (isset($_SESSION['isAuthUser'])) {
			header("Location: " . $auth_url);
		}

		if (isset($_POST['username']) && isset($_POST['password'])) {
			$username = $_POST['username'];
			$_SESSION['username'] = $username;

			$password = hash("sha256", $_POST['password']);

			if (($username != "") && ($password != "")) {
				$users = getSettingUsers($pdo);
				$is_auth = (array_key_exists($username, $users) ? $users[$username]['password'] == $password : false);
				if ($is_auth) {
					$is_auth = (isset($users[$username]['web']) ? $users[$username]['web'] : false);
					if ($is_auth) {
						$is_auth = (isset($users[$username]['expires']) ? $users[$username]['expires'] > time() : true);
						$login_error = ($is_auth ? null : "User Account Expired");
					} else {
						$login_error = "Access Denied.";
					}
				} else {
					$login_error = "Invalid Username or Password.";
				}
			}
		}

		if (isset($_POST['change_passwd']) && isset($_POST['new_passwd'])) {
			setSettingUser($pdo, $_POST['change_passwd'], "reset", 0);
			setSettingUser($pdo, $_POST['change_passwd'], "password", hash("sha256", $_POST['new_passwd']));
			$_SESSION['isAuthUser'] = 1;
			header("Location: " . $auth_url);
		}

		if ($is_auth) {
			if (isset($users[$username]['reset']) && (bool)$users[$username]['reset']) {
				$modal = "change-passwd";
			} else {
				$_SESSION['isAuthUser'] = 1;
				header("Location: " . $auth_url);
			}
		}
	}
} else {
	unset($_SESSION['isAuthUser']);

	$dsn_token = bin2hex(openssl_random_pseudo_bytes(16));
	$kinobi->setSetting("dsn_token", $dsn_token);

	header("Location: patchDatabase.php");
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

		<title>Kinobi</title>

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

	<body>
		<div id="wrapper">
			<!-- Page Content -->
			<div class="container-fluid" id="page-content-wrapper">

				<!-- Custom styles for this project -->
				<link rel="stylesheet" href="theme/kinobi.css" type="text/css">

				<!-- Custom styles for this page -->
				<style>
					body {
						background-color: #292929;
					}

					.modal-body > h4 {
						margin: 0;
						line-height: 1.42857143;
						padding-bottom: 12px;
						font-weight: bold;
					}
				</style>

				<script type="text/javascript">
					var cloud = <?php echo json_encode($cloud); ?>;
					var subs_type = "<?php echo $subs_type; ?>";
					var login_error = "<?php echo $login_error; ?>";
					var username = "<?php echo $username; ?>";
					var modal = "<?php echo $modal; ?>";
				</script>

				<script type="text/javascript" src="scripts/kinobi/login.js"></script>

				<form method="post" id="login-form">
					<!-- Login Modal -->
					<div class="modal" id="login-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
						<div class="modal-dialog modal-sm" role="document">
							<div class="modal-content">
								<div class="modal-header text-center"></div>
								<div class="modal-body">
									<div id="login-error-alert" class="alert alert-danger hidden" role="alert">
										<span class="glyphicon glyphicon-exclamation-sign"></span><span id="login-error-msg" class="text-muted">ERROR</span>
									</div>

									<div class="form-group">
										<label class="control-label" for="username">Username</label>
										<input type="text" class="form-control input-sm" name="username" id="username" placeholder="[Required]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="password">Password</label>
										<input type="password" class="form-control input-sm" name="password" id="password" placeholder="[Required]"/>
									</div>
								</div>
								<div class="modal-footer">
									<button type="submit" name="login" id="login" class="btn btn-primary btn-sm pull-right">Log In</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->
				</form><!-- end login-form -->

				<form method="post" id="change-passwd-form">
					<!-- Change Password Modal -->
					<div class="modal" id="change-passwd-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="change-passwd-label" aria-hidden="true">
						<div class="modal-dialog modal-sm" role="document">
							<div class="modal-content">
								<div class="modal-header text-center"></div>
								<div class="modal-body">
									<h4 id="change-passwd-label">Change Password</h4>

									<input type="hidden" name="change_passwd" id="change-passwd" disabled/>

									<div class="form-group">
										<label class="control-label" for="new-passwd">New Password</label>
										<input type="password" autocomplete="off" class="form-control input-sm" name="new_passwd" id="new-passwd" aria-describedby="new-passwd-help" placeholder="[Required]"/>
										<span id="new-passwd-help" class="help-block hidden"><small>Did not match</small></span>
									</div>

									<div class="form-group">
										<label class="control-label" for="new-passwd-verify">Verify Password</label>
										<input type="password" autocomplete="off" class="form-control input-sm" name="new_passwd_verify" id="new-passwd-verify" aria-describedby="new-passwd-verify-help" placeholder="[Required]"/>
										<span id="new-passwd-verify-help" class="help-block hidden"><small>Did not match</small></span>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" id="change-passwd-save" class="btn btn-primary btn-sm">Save</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->
				</form><!-- end change-passwd-form -->
			</div><!-- /#page-content-wrapper -->
		</div><!-- /#wrapper -->
	</body>
</html>