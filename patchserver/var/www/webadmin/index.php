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

<?php
session_start();
// Settings
if (file_exists("inc/config.php")) {
	include "inc/config.php";
}
include "inc/patch/functions.php";

// Database
include "inc/patch/database.php";

$isAuth = false;

if (!$pdo) { ?>
		<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
			<div class="panel-body">
				<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $pdo_error; ?></div>
			</div>
		</div>
<?php } else {
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
?>
		<script>
			$(window).load(function() {
				$('#login-modal').modal('show');
			});
		</script>

		<form name="loginForm" class="form-horizontal" id="login-form" method="post">
			<!-- Delete Title Modal -->
			<div class="modal" id="login-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
				<div class="modal-dialog modal-sm" role="document">
					<div class="modal-content">
						<div class="modal-header" align="center">
							<a target="_blank" href="https://kinobi.io/"><img src="images/kinobi-logo.svg" height="30"></a>
						</div>
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
							<button type="submit" name="submit" id="submit" class="btn btn-primary btn-sm pull-right">Log In</button>
						</div>
					</div>
				</div>
			</div>
			<!-- /.modal -->
		</form>

<?php } ?>
	</body>
</html>
