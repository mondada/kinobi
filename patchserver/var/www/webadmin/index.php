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

session_start();
// Settings
if (file_exists("inc/config.php")) {
	include "inc/config.php";
}
include "inc/patch/functions.php";

// Database
include "inc/patch/database.php";

$isAuth = false;

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
<!DOCTYPE html>

<html>
	<head>
		<title>Kinobi Login</title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
		<meta http-equiv="expires" content="0">
		<meta http-equiv="pragma" content="no-cache">
		<!-- Roboto Font CSS -->
		<link href="theme/roboto.font.css" rel="stylesheet" type="text/css">
		<!-- Bootstrap CSS -->
		<link href="theme/bootstrap.css" rel="stylesheet" media="all">
		<!-- Project CSS -->
		<link rel="stylesheet" href="theme/custom.css" type="text/css">
		<style>
			body {
				background-color: #292929;
			}
		</style>
		<script type="text/javascript" src="scripts/jquery/jquery-2.2.4.js"></script>
		<script type="text/javascript" src="scripts/bootstrap.min.js"></script>

		<script>
			$(window).load(function() {
				$('#login-modal').modal('show');
			});
		</script>
	</head>

	<body>

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

	</body>
</html>
