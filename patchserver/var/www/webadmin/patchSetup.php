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

// Re-direct to HTTPS if connecting via HTTP
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
	header("Location: https://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
}

include "inc/patch/functions.php";
include "inc/patch/database.php";

// Cloud Configuration
$cloud = $kinobi->getSetting("cloud");

// Setup Token
$setup_token = $kinobi->getSetting("setup_token");
if (empty($setup_token)) {
	header("Location: index.php");
}

if (isset($_POST['apply_setup'])) {
	// License
	$kinobi->setSetting("eula_accepted", true);

	// Subscription
	$subs = getSettingSubscription($pdo);
	if (!empty($_POST['subs_url']) && !empty($_POST['subs_token'])) {
		$subs['url'] = $_POST['subs_url'];
		$subs['token'] = $_POST['subs_token'];
	}
	setSettingSubscription($pdo, $subs);

	// Create User
	createUser($pdo, $_POST['user_username'], hash("sha256", $_POST['user_password']));
	setSettingUser($pdo, $_POST['user_username'], "web", true);
	setSettingUser($pdo, $_POST['user_username'], "api", 1);

	$kinobi->deleteSetting("setup_token");

	header("Location: index.php");
}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

// Usernames
$users = getSettingUsers($pdo);
$usernames = array_keys($users);
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

				<!-- Awesome Bootstrap Checkbox -->
				<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css" type="text/css"/>

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
					var setup_token = "<?php echo $setup_token; ?>";
					var usernames = <?php echo json_encode($usernames); ?>;
				</script>

				<script type="text/javascript" src="scripts/kinobi/patchSetup.js"></script>

				<form method="post" id="setup-form">
					<input type="hidden" name="apply_setup" id="apply-setup" disabled/>

					<!-- License Modal -->
					<div class="modal" id="license-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header text-center"></div>
								<div class="modal-body">
									<!-- <h3 class="modal-title" id="subscription-label">License Agreement</h3> -->
									<div id="license-file" class="well well-sm" style="max-height: 254px; overflow-y: scroll"></div>

									<div class="checkbox checkbox-primary">
										<input id="license-agree" class="styled" type="checkbox"/>
										<label>I have read and accepted the terms of the license agreement.</label>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" id="license-next-btn" class="btn btn-primary btn-sm pull-right" disabled>Next</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->

					<!-- Subscription Modal -->
					<div class="modal" id="subscription-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="subscription-label" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header text-center"></div>
								<div class="modal-body">
									<div id="subs-error-alert" class="alert alert-danger hidden" role="alert">
										<span class="glyphicon glyphicon-exclamation-sign"></span><span id="subs-error-msg" class="text-muted">ERROR</span>
									</div>

									<h4 id="subscription-label">Enter Your Subscription Token</h4>

									<div class="form-group">
										<label class="control-label" for="subs-url">Server URL <small>URL for the subscription server.</small></label>
										<input type="text" class="form-control input-sm" name="subs_url" id="subs-url" placeholder="[Optional]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="subs-token">Token <small>Auth token for the subscription server.</small></label>
										<input type="text" class="form-control input-sm" name="subs_token" id="subs-token" placeholder="[Optional]"/>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" id="subs-back-btn" class="btn btn-default btn-sm pull-left">Back</button>
									<button type="button" id="subs-next-btn" class="btn btn-primary btn-sm pull-right">Next</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->

					<!-- Create User Modal -->
					<div class="modal" id="user-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="user-label" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header text-center"></div>
								<div class="modal-body">
									<h4 id="user-label">Create User Account</h4>

									<div class="form-group">
										<label class="control-label" for="user-username">User Name <small>Username for the account.</small></label>
										<input type="text" class="form-control input-sm" name="user_username" id="user-username" placeholder="[Required]"/>
										<span id="user-username-help" class="help-block hidden"><small>Duplicate</small></span>
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
								</div>
								<div class="modal-footer">
									<button type="button" id="user-back-btn" class="btn btn-default btn-sm pull-left">Back</button>
									<button type="button" id="user-next-btn" class="btn btn-primary btn-sm pull-right">Next</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->
				</form><!-- end setup-form -->
			</div><!-- /#page-content-wrapper -->
		</div><!-- /#wrapper -->
	</body>
</html>