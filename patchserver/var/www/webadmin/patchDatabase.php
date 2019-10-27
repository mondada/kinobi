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
$pdo_error = null;

// Cloud Configuration
$cloud = $kinobi->getSetting("cloud");

// Database Token
$dsn_token = $kinobi->getSetting("dsn_token");
if (empty($dsn_token)) {
	header("Location: index.php");
}

if (isset($_POST['dsn_apply'])) {
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
		$username = $_POST['db_username'];
		$passwd = $_POST['db_passwd'];

		try {
			$pdo = new PDO($dsn, $username, $passwd);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			$pdo_error = $e->getMessage();
		}
	}

	$db = array(
		"dsn" => array(
			"prefix" => $_POST['dsn_prefix'],
			"dbpath" => $_POST['dsn_dbpath'],
			"host" => $_POST['dsn_host'],
			"port" => $_POST['dsn_port'],
			"dbname" => $_POST['dsn_dbname']
		),
		"username" => $_POST['db_username']
	);

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
				"username" => $_POST['db_username'],
				"passwd" => openssl_encrypt($_POST['db_passwd'], "AES-128-CTR", $kinobi->getSetting("uuid"), 0, substr(md5($_POST['db_username']), 0, 16))
			)
		);

		header("Location: index.php");
	}
}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

if (!isset($db)) {
	include "inc/patch/database.php";
	unset($db['passwd']);
}

if (empty($pdo_error)) {
	header("Location: index.php");
}

$sqlite_dir = dirname($_SERVER['DOCUMENT_ROOT']) . "/kinobi/db/";
$sqlite_dbs = array();
foreach (glob($sqlite_dir . "*") as $sqlite_db) {
	array_push($sqlite_dbs, basename($sqlite_db));
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

				<!-- bootstrap-select -->
				<link rel="stylesheet" href="theme/bootstrap-select.css" type="text/css"/>

				<!-- Custom styles for this project -->
				<link rel="stylesheet" href="theme/kinobi.css" type="text/css">

				<!-- Custom styles for this page -->
				<style>
					body {
						background-color: #292929;
					}
				</style>

				<!-- bootstrap-select -->
				<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>

				<script type="text/javascript">
					var cloud = <?php echo json_encode($cloud); ?>;
					var pdo_error = "<?php echo $pdo_error; ?>";
					var dsn_token = "<?php echo $dsn_token; ?>";
					var dsn_json = <?php echo json_encode($db); ?>;
					var sqlite_dir = "<?php echo $sqlite_dir; ?>";
					var sqlite_dbs_json = <?php echo json_encode($sqlite_dbs); ?>;
				</script>

				<script type="text/javascript" src="scripts/kinobi/patchDatabase.js"></script>

				<form method="post" id="dsn-form">
					<!-- Database Modal -->
					<div class="modal" id="database-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="database-label" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="database-label">Database Connection</h3>
								</div>
								<div class="modal-body">
									<div id="database-error-alert" class="alert alert-danger hidden" role="alert">
										<span class="glyphicon glyphicon-exclamation-sign"></span><span id="database-error-msg" class="text-muted">ERROR</span>
									</div>

									<input type="hidden" name="dsn_token" id="dsn-token"/>
									<input type="hidden" name="dsn_retry" id="dsn-retry" disabled/>
									<input type="hidden" name="dsn_apply" id="dsn-apply" disabled/>

									<div class="form-group hidden">
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
											<input type="text" class="form-control input-sm" name="db_username" id="dsn-dbuser"/>
										</div>

										<div class="form-group">
											<label class="control-label" for="dsn-dbpass">Password <small>Password used to authenticate with the MySQL server.</small></label>
											<input type="password" class="form-control input-sm" name="db_passwd" id="dsn-dbpass"/>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" id="dsn-retry-btn" class="btn btn-default btn-sm pull-left">Retry</button>
									<button type="button" id="dsn-apply-btn" class="btn btn-primary btn-sm pull-right hidden">Apply</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->
				</form><!-- end dsn-form -->
			</div><!-- /#page-content-wrapper -->
		</div><!-- /#wrapper -->
	</body>
</html>