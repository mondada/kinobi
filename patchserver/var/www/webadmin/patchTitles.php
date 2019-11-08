<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.3.1
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
include "inc/patch/database.php";

$error_msg = null;
$warning_msg = null;
$success_msg = null;
$sw_titles = array();

// Cloud Configuration
$cloud = $kinobi->getSetting("cloud");

// Check for subscription
$subs = getSettingSubscription($pdo);
if (!empty($subs['url']) && !empty($subs['token'])) {
	$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
}
$subs_type = (isset($subs_resp['type']) ? $subs_resp['type'] : null);
if (isset($subs_resp['renew'])) {
	if ($cloud) {
		$subs_resp['renew'] = $subs_resp['renew'] . "?register=cloud";
	} else {
		$subs_resp['renew'] = $subs_resp['renew'] . "?register=self";
	}
}

$eula_accepted = $kinobi->getSetting("eula_accepted");

// Standalone
$netsus = (isset($conf) ? (strpos(file_get_contents("inc/header.php"), "NetSUS 4") !== false ? 4 : 5) : 0);

if ($pdo) {
	// Create Software Title
	if (isset($_POST['create_title'])) {
		$name = $_POST['name'];
		$publisher = $_POST['publisher'];
		$app_name = $_POST['app_name'];
		$bundle_id = $_POST['bundle_id'];
		$modified = time();
		$current = $_POST['current'];
		$name_id = $_POST['name_id'];
		$stmt = $pdo->prepare("INSERT INTO titles (name, publisher, app_name, bundle_id, modified, current, name_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute(array($name, $publisher, $app_name, $bundle_id, $modified, $current, $name_id));
		if ($stmt->errorCode() == "00000") {
			$success_msg = "Created Software Title <a href='manageTitle.php?id=" . $pdo->lastInsertId() . "'>" . $name . "</a>.";
		} else {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Software Title
	if (isset($_POST['del_title'])) {
		$del_title_id = $_POST['del_title'];
		$stmt = $pdo->prepare("SELECT name, name_id, source_id FROM titles WHERE id = ?");
		$stmt->execute(array($del_title_id));
		$title = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt = $pdo->prepare("DELETE FROM overrides WHERE name_id = ?");
		$stmt->execute(array($title['name_id']));
		$stmt = $pdo->prepare("DELETE FROM titles WHERE id = ?");
		$stmt->execute(array($del_title_id));
		if ($stmt->errorCode() == "00000") {
			$success_msg = ($title['source_id'] == 0 ? "Deleted" : "Removed") . " " . $title['name'] . ".";
		} else {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	if (isset($subs_resp['import'])) {
		include $subs_resp['import'];
	}

	if (isset($subs_resp['upload'])) {
		include $subs_resp['upload'];
	}

	// ####################################################################
	// End of GET/POST parsing
	// ####################################################################

	// Subscription Status
	if (!empty($subs['url']) || !empty($subs['token'])) {
		if (empty($subs_resp['expires'])) {
			$error_msg = "Invalid token. Please ensure the Server URL and Token values are entered exactly as they were provided.";
		} elseif ($subs_resp['expires'] < $subs_resp['timestamp']) {
			$error_msg = $subs_resp['type'] . " subscription expired: " . date("M j, Y", $subs_resp['expires']) . " <a target='_blank' href='" . $subs_resp['renew'] . "'>Click here to renew</a>.";
			if ($subs_resp['expires'] > $subs_resp['timestamp'] - (14*24*60*60)) {
				$warning_msg = "Patch Definitions imported from Kinobi will be removed in " . (14 + ($subs_resp['expires'] - $subs_resp['timestamp']) / (24*60*60)) . " days.";
			}
		} elseif ($subs_resp['expires'] < $subs_resp['timestamp'] + (14*24*60*60)) {
			$warning_msg = $subs_resp['type'] . " subscription expires: " . date("M j, Y", $subs_resp['expires']) . " <a target='_blank' href='" . $subs_resp['renew'] . "'>Click here to renew</a>.";
		}
	}

	// Refresh
	include "inc/patch/refresh.php";

	// Software Title Summary
	$stmt = $pdo->query("SELECT id, name_id, name, publisher, current, modified, enabled, source_id FROM titles ORDER BY publisher, name");
	while ($sw_title = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sw_title['enabled'] = (bool)$sw_title['enabled'];
		$sw_title['error'] = array();
		$sw_title['requirements'] = $pdo->query("SELECT id FROM requirements WHERE title_id = " . $sw_title['id'])->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($sw_title['requirements']) == 0) {
			array_push($sw_title['error'], "requirements");
		}
		$sw_title['patches'] = $pdo->query("SELECT id FROM patches WHERE title_id = " . $sw_title['id'] . " AND enabled = 1")->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($sw_title['patches']) == 0) {
			array_push($sw_title['error'], "patches");
		}
		$override = $pdo->query("SELECT current FROM overrides WHERE name_id = '" . $sw_title['name_id'] . "'")->fetch(PDO::FETCH_COLUMN);
		if (!empty($override)) {
			$sw_title['current'] = $override;
		}
		if (sizeof($sw_title['error']) > 0 && $sw_title['enabled'] == true) {
			$sw_title['enabled'] = false;
			$disable = $pdo->prepare("UPDATE titles SET enabled = 0 WHERE id = ?");
			$disable->execute(array($sw_title['id']));
			if ($disable->errorCode() == "00000") {
				$warning_msg = "<a href='manageTitle.php?id=" . $sw_title['id'] . "'>" . $sw_title['name'] . "</a> has been disabled.";
			} else {
				$errorInfo = $disable->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}
		array_push($sw_titles, $sw_title);
	}
}
?>
				<!-- Awesome Bootstrap Checkbox -->
				<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css" type="text/css"/>

				<!-- DataTables -->
				<link rel="stylesheet" href="theme/dataTables.bootstrap.css" type="text/css"/>
				<link rel="stylesheet" href="theme/buttons.bootstrap.css" type="text/css"/>

				<!-- bootstrap-select -->
				<link rel="stylesheet" href="theme/bootstrap-select.css" type="text/css"/>

				<!-- Custom styles for this project -->
				<link rel="stylesheet" href="theme/kinobi.css" type="text/css">

				<!-- Custom styles for this page -->
				<style>
					#page-title-wrapper {
						box-shadow: none;
					}
					.dataTables-header {
						background-color: #f9f9f9;
						border-bottom: 1px solid #ddd;
						padding: 2px 20px 8px;
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

				<!-- DataTables -->
				<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
				<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
				<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
				<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

				<!-- Bootstrap Add Clear -->
				<script type="text/javascript" src="scripts/bootstrap-add-clear/bootstrap-add-clear.min.js"></script>

				<!-- bootstrap-select -->
				<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>

				<script type="text/javascript">
					var pdo_error = "<?php echo $pdo_error; ?>";
					var subs_type = "<?php echo $subs_type; ?>";
					var eula_accepted = <?php echo json_encode($eula_accepted); ?>;
					var error_msg = "<?php echo $error_msg; ?>";
					var warning_msg = "<?php echo $warning_msg; ?>";
					var success_msg = "<?php echo $success_msg; ?>";
					var titles_json = <?php echo json_encode($sw_titles); ?>;
				</script>

				<script type="text/javascript" src="scripts/kinobi/patchTitles.js"></script>

				<div id="page-title-wrapper">
					<div id="page-title">
						<ol class="breadcrumb">
							<li class="active">&nbsp;</li>
						</ol>

						<h2 id="heading">Software Titles</h2>
					</div>
				</div>

				<!-- Page Title Spacer -->
				<div style="margin-top: -18px;">
					<div style="height: 78px;"></div>
				</div>

				<div class="alert-wrapper">
					<div id="error-alert" class="alert alert-danger hidden" role="alert">
						<span class="glyphicon glyphicon-exclamation-sign"></span><span id="error-msg" class="text-muted">ERROR</span>
					</div>

					<div id="warning-alert" class="alert alert-warning hidden" role="alert">
						<span class="glyphicon glyphicon-warning-sign"></span><span id="warning-msg" class="text-muted">WARNING</span>
					</div>

					<div id="success-alert" class="alert alert-success hidden" role="alert">
						<span class="glyphicon glyphicon-ok-sign"></span><span id="success-msg" class="text-muted">SUCCESS</span>
					</div>

					<div id="info-alert" class="alert alert-info hidden" role="alert">
						<span class="glyphicon glyphicon-info-sign"></span><span id="success-msg" class="text-muted">INFO</span>
					</div>
				</div>

				<table id="sw-titles" class="table table-hover" style="width: 100%; border-bottom: 1px solid #ddd;">
					<thead>
						<tr>
							<th>Enable</th>
							<th>Name</th>
							<th>Publisher</th>
							<th>Current Version</th>
							<th>Last Modified</th>
							<th></th>
						</tr>
					</thead>
					<tbody/>
				</table>

				<form method="post" id="titles-form">
					<!-- New Title Modal -->
					<div class="modal fade" id="new-title-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-title-label" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="new-title-label">New Software Title</h3>
								</div>
								<div class="modal-body">
									<input type="hidden" name="create_title" id="create-title" disabled/>

									<div class="form-group">
										<label class="control-label" for="new-name">Name <small>Name of the patch management software title.</small></label>
										<input type="text" class="form-control input-sm" name="name" id="new-name" placeholder="[Required]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="new-publisher">Publisher <small>Publisher of the patch management software title.</small></label>
										<input type="text" class="form-control input-sm" name="publisher" id="new-publisher" placeholder="[Required]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="new-app-name">Application Name <small>Deprecated.</small></label>
										<input type="text" class="form-control input-sm" name="app_name" id="new-app-name" placeholder="[Optional]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="new-bundle-id">Bundle Identifier <small>Deprecated.</small></label>
										<input type="text" class="form-control input-sm" name="bundle_id" id="new-bundle-id" placeholder="[Optional]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="new-current">Current Version <small>Used for reporting the latest version of the patch management software title to Jamf Pro.</small></label>
										<input type="text" class="form-control input-sm" name="current" id="new-current" placeholder="[Required]"/>
									</div>

									<div class="form-group">
										<label class="control-label" for="new-name-id">ID <small>Uniquely identifies this software title on this external source.<br><strong>Note:</strong> The <span style="font-family: monospace;">id</span> cannot include any special characters or spaces.</small></label>
										<input type="text" class="form-control input-sm" name="name_id" id="new-name-id" aria-describedby="new-name-id-help" placeholder="[Required]"/>
										<span id="new-name-id-help" class="help-block hidden"><small>Duplicate ID exists</small></span>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
									<button type="button" id="new-title-btn" class="btn btn-primary btn-sm pull-right">Save</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->

					<!-- Delete Title Modal -->
					<div class="modal fade" id="del-title-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-title-label" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="del-title-label">Delete Title?</h3>
								</div>
								<div class="modal-body">
									<div class="text-muted" id="del-title-msg">Are you sure you want to delete this title?<br><small>This action is permanent and cannot be undone.</small></div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
									<button type="submit" name="del_title" id="del-title-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
								</div>
							</div><!-- /.modal-content -->
						</div><!-- /.modal-dialog -->
					</div><!-- /.modal -->
				</form><!-- end titles-form -->

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