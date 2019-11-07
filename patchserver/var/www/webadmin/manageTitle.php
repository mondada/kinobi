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
$title = array();
$ext_attrs = array();
$requirements = array();
$patches = array();
$name_ids = array();
$ext_attr_key_ids = array();
$override = null;
$patches_success_msg = null;

// Check for subscription
$subs = getSettingSubscription($pdo);
if (!empty($subs['url']) && !empty($subs['token'])) {
	$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
}
$subs_type = (isset($subs_resp['type']) ? $subs_resp['type'] : null);
$eula_accepted = $kinobi->getSetting("eula_accepted");

// Standalone
$netsus = (isset($conf) ? (strpos(file_get_contents("inc/header.php"), "NetSUS 4") !== false ? 4 : 5) : 0);

if ($pdo) {
	// Software Title
	$stmt = $pdo->prepare("SELECT id, name, publisher, app_name, bundle_id, modified, current, name_id, enabled, source_id FROM titles WHERE id = ?");
	$stmt->execute(array((isset($_GET['id']) ? $_GET['id'] : null)));
	$title = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!empty($title)) {
		// Set for legacy compatibility in older version with a subscription
		$title_id = $title['id'];

		// Create Extension Attribute
		if (isset($_POST['create_ea'])) {
			$ea_key_id = $_POST['ea_key_id'];
			$ea_script = $_POST['ea_script'];
			$ea_name = $_POST['ea_name'];
			$stmt = $pdo->prepare("INSERT INTO ext_attrs (title_id, key_id, script, name) VALUES (?, ?, ?, ?)");
			$stmt->execute(array($title['id'], $ea_key_id, $ea_script, $ea_name));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Save Extension Attribute
		if (isset($_POST['save_ea'])) {
			$ea_id = $_POST['save_ea'];
			$ea_key_id = $_POST['ea_key_id'];
			$ea_script = $_POST['ea_script'];
			$ea_name = $_POST['ea_name'];
			$stmt = $pdo->prepare("SELECT key_id FROM ext_attrs WHERE id = ?");
			$stmt->execute(array($ea_id));
			$old_key_id = $stmt->fetchColumn();
			$pdo->beginTransaction();
			$stmt = $pdo->prepare("UPDATE requirements SET name = ? WHERE name = ?");
			$stmt->execute(array($ea_key_id, $old_key_id));
			$stmt = $pdo->prepare("UPDATE capabilities SET name = ? WHERE name = ?");
			$stmt->execute(array($ea_key_id, $old_key_id));
			$stmt = $pdo->prepare("UPDATE dependencies SET name = ? WHERE name = ?");
			$stmt->execute(array($ea_key_id, $old_key_id));
			$stmt = $pdo->prepare("UPDATE criteria SET name = ? WHERE name = ?");
			$stmt->execute(array($ea_key_id, $old_key_id));
			$stmt = $pdo->prepare("UPDATE ext_attrs SET key_id = ?, script = ?, name = ? WHERE id = ?");
			$stmt->execute(array($ea_key_id, $ea_script, $ea_name, $ea_id));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
			$pdo->commit();
		}

		// Delete Extension Attribute
		if (isset($_POST['delete_ea'])) {
			// To Do: Add code to decrease sort_order of requirements / capabilities / dependencies / criteria when deleting EAs
			$ea_id = $_POST['delete_ea'];
			$stmt = $pdo->prepare("SELECT key_id FROM ext_attrs WHERE id = ?");
			$stmt->execute(array($ea_id));
			$ea_key_id = $stmt->fetchColumn();
			$pdo->beginTransaction();
			$stmt = $pdo->prepare("DELETE FROM requirements WHERE name = ?");
			$stmt->execute(array($ea_key_id));
			$stmt = $pdo->prepare("DELETE FROM capabilities WHERE name = ?");
			$stmt->execute(array($ea_key_id));
			$stmt = $pdo->prepare("DELETE FROM dependencies WHERE name = ?");
			$stmt->execute(array($ea_key_id));
			$stmt = $pdo->prepare("DELETE FROM criteria WHERE name = ?");
			$stmt->execute(array($ea_key_id));
			$stmt = $pdo->prepare("DELETE FROM ext_attrs WHERE id = ?");
			$stmt->execute(array($ea_id));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
			$pdo->commit();
		}

		// Create Requirement
		if (isset($_POST['create_rqmt'])) {
			$rqmt_name = $_POST['rqmt_name'];
			$rqmt_operator = $_POST['rqmt_operator'];
			$rqmt_value = $_POST['rqmt_value'];
			$rqmt_type = $_POST['rqmt_type'];
			$rqmt_is_and = $_POST['rqmt_is_and'];
			$rqmt_sort_order = $_POST['rqmt_sort_order'];
			$stmt = $pdo->prepare("UPDATE requirements SET sort_order = sort_order + 1 WHERE title_id = ? AND sort_order >= ?");
			$stmt->execute(array($title['id'], $rqmt_sort_order));
			$stmt = $pdo->prepare("INSERT INTO requirements (title_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute(array($title['id'], $rqmt_name, $rqmt_operator, $rqmt_value, $rqmt_type, $rqmt_is_and, $rqmt_sort_order));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Delete Requirement
		if (isset($_POST['del_rqmt'])) {
			$rqmt_id = $_POST['del_rqmt'];
			$stmt = $pdo->prepare("SELECT sort_order FROM requirements WHERE id = ?");
			$stmt->execute(array($rqmt_id));
			$rqmt_sort_order = $stmt->fetchColumn();
			$stmt = $pdo->prepare("UPDATE requirements SET sort_order = sort_order - 1 WHERE title_id = ? AND sort_order > ?");
			$stmt->execute(array($title['id'], $rqmt_sort_order));
			$stmt = $pdo->prepare("DELETE FROM requirements WHERE id = ?");
			$stmt->execute(array($rqmt_id));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Create Patch
		if (isset($_POST['create_patch'])) {
			$patch_sort_order = $_POST['patch_sort_order'];
			$patch_version = $_POST['patch_version'];
			$patch_released = date("U", strtotime($_POST['patch_released']));
			$patch_standalone = ($_POST['patch_standalone'] == "0") ? 0 : 1;
			$patch_reboot = ($_POST['patch_reboot'] == "1") ? 1 : 0;
			$patch_min_os = $_POST['patch_min_os'];
			$stmt = $pdo->prepare("UPDATE patches SET sort_order = sort_order + 1 WHERE title_id = ? AND sort_order >= ?");
			$stmt->execute(array($title['id'], $patch_sort_order));
			$stmt = $pdo->prepare("INSERT INTO patches (title_id, version, released, standalone, min_os, reboot, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute(array($title['id'], $patch_version, $patch_released, $patch_standalone, $patch_min_os, $patch_reboot, $patch_sort_order));
			if ($stmt->errorCode() == "00000") {
				$patches_success_msg = "Created Patch Version <a href='managePatch.php?id=" . $pdo->lastInsertId() . "'>" . $patch_version . "</a>.";
			} else {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		if (isset($subs_resp['upload'])) {
			include $subs_resp['upload'];
		}

		// Delete Patch
		if (isset($_POST['del_patch'])) {
			$patch_id = $_POST['del_patch'];
			$stmt = $pdo->prepare("SELECT sort_order, version FROM patches WHERE id = ?");
			$stmt->execute(array($patch_id));
			$patch = $stmt->fetch(PDO::FETCH_ASSOC);
			$stmt = $pdo->prepare("UPDATE patches SET sort_order = sort_order - 1 WHERE title_id = ? AND sort_order > ?");
			$stmt->execute(array($title['id'], $patch['sort_order']));
			$stmt = $pdo->prepare("DELETE FROM patches WHERE id = ?");
			$stmt->execute(array($patch_id));
			if ($stmt->errorCode() == "00000") {
				$patches_success_msg = "Deleted Patch Version " . $patch['version'] . ".";
			} else {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Update Title Modified
		if (isset($_POST['create_ea'])
		 || isset($_POST['save_ea'])
		 || isset($_POST['delete_ea'])
		 || isset($_POST['create_rqmt'])
		 || isset($_POST['del_rqmt'])
		 || isset($_POST['create_patch'])
		 || isset($_POST['del_patch'])
		 || !empty($patches_success_msg)) {
			$title_modified = time();
			$stmt = $pdo->prepare("UPDATE titles SET modified = ? WHERE id = ?");
			$stmt->execute(array($title_modified, $title['id']));
		}

		// ####################################################################
		// End of GET/POST parsing
		// ####################################################################

		// Software Title
		$title['error'] = array();

		// Software Title Name IDs
		$name_ids = $pdo->query("SELECT name_id FROM titles")->fetchAll(PDO::FETCH_COLUMN);

		// Extension Attributes
		$ext_attrs = $pdo->query("SELECT id, key_id, script, name FROM ext_attrs WHERE title_id = " . $title['id'])->fetchAll(PDO::FETCH_ASSOC);

		// Extension Attribute Keys
		$ext_attr_key_ids = $pdo->query("SELECT key_id FROM ext_attrs")->fetchAll(PDO::FETCH_COLUMN);

		// Requirements
		$requirements = array();
		$stmt = $pdo->query("SELECT id, name, operator, value, type, is_and, sort_order FROM requirements WHERE title_id = " . $title['id'] . " ORDER BY sort_order");
		while ($requirement = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$requirement['is_and'] = ($requirement['is_and'] == "0") ? "0": "1";
			array_push($requirements, $requirement);
		}
		if (sizeof($requirements) == 0) {
			array_push($title['error'], "requirements");
		}

		// Patches
		$patches = array();
		$stmt = $pdo->query("SELECT id, version, released, standalone, min_os, reboot, sort_order, enabled FROM patches WHERE title_id = " . $title['id'] . " ORDER BY sort_order ASC, id DESC");
		while ($patch = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$patch['standalone'] = ($patch['standalone'] == "0") ? "0": "1";
			$patch['reboot'] = ($patch['reboot'] == "1") ? "1": "0";
			$patch['enabled'] = ($patch['enabled'] == "1") ? "1" : "0";
			$patch['error'] = array();
			$patch['components'] = $pdo->query("SELECT id FROM components WHERE patch_id = " . $patch['id'])->fetchAll(PDO::FETCH_COLUMN);
			if (sizeof($patch['components']) == 0) {
				array_push($patch['error'], "components");
			}
			foreach ($patch['components'] as $component_id) {
				$criteria = $pdo->query("SELECT id FROM criteria WHERE component_id = " . $component_id)->fetchAll(PDO::FETCH_COLUMN);
				if (sizeof($criteria) == 0) {
					array_push($patch['error'], "criteria");
				}
			}
			$patch['capabilities'] = $pdo->query("SELECT id FROM capabilities WHERE patch_id = " . $patch['id'])->fetchAll(PDO::FETCH_COLUMN);
			if (sizeof($patch['capabilities']) == 0) {
				array_push($patch['error'], "capabilities");
			}
			if (sizeof($patch['error']) > 0 && $patch['enabled'] == "1") {
				$patch['enabled'] == "0";
				$disable = $pdo->query("UPDATE patches SET enabled = 0 WHERE id = ?");
				$disable->execute(array($patch['id']));
				if ($disable->errorCode() != "00000") {
					$errorInfo = $disable->errorInfo();
					$error_msg = $errorInfo[2];
				}
			}
			array_push($patches, $patch);
		}
		$patch_versions = array_map(function($el){ return $el['version']; }, $patches);

		// Current Version
		if (count($patches) > 0) {
			if (!in_array($title['current'], $patch_versions, TRUE)) {
				$stmt = $pdo->prepare("UPDATE titles SET current = ? WHERE id = ?");
				$stmt->execute(array($patch_versions[0], $title['id']));
				$title['current'] = $patch_versions[0];
			}
		}
		$override = $pdo->query("SELECT current FROM overrides WHERE name_id = '" . $title['name_id'] . "'")->fetch(PDO::FETCH_COLUMN);
		if (!empty($override)) {
			if (in_array($title['current'], $patch_versions, TRUE)) {
				$title['current'] = $override;
			} else {
				$stmt = $pdo->prepare("DELETE FROM overrides WHERE name_id = ?");
				$stmt->execute(array($title['name_id']));
				$override = false;
			}
		}

		// Enabled Pacthes
		if (!in_array(1, array_map(function($el){ return $el['enabled']; }, $patches))) {
			array_push($title['error'], "patches");
		}

		// Disable Incomplete Title
		if (sizeof($title['error']) > 0 && $title['enabled'] == "1") {
			$title['enabled'] = "0";
			$disable = $pdo->prepare("UPDATE titles SET enabled = 0 WHERE id = ?");
			$disable->execute(array($title['id']));
			if ($disable->errorCode() != "00000") {
				$errorInfo = $disable->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}
	}
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

				<!-- Custom styles for this project -->
				<link rel="stylesheet" href="theme/kinobi.css" type="text/css">

				<!-- Custom styles for this page -->
				<style>
					.script-editor {
						min-height: 306px;
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

				<!-- Ace -->
				<script type="text/javascript" src="scripts/ace/ace.js"></script>

				<script type="text/javascript">
					var pdo_error = "<?php echo $pdo_error; ?>";
					var subs_type = "<?php echo $subs_type; ?>";
					var eula_accepted = <?php echo json_encode($eula_accepted); ?>;
					var error_msg = "<?php echo $error_msg; ?>";
					var title_json = <?php echo json_encode($title); ?>;
					var name_ids = <?php echo json_encode($name_ids); ?>;
					var override = "<?php echo $override; ?>";
					var ext_attrs_json = <?php echo json_encode($ext_attrs); ?>;
					var key_ids = <?php echo json_encode($ext_attr_key_ids); ?>;
					var requirements_json = <?php echo json_encode($requirements); ?>;
					var patches_json = <?php echo json_encode($patches); ?>;
					var patches_success_msg = "<?php echo $patches_success_msg; ?>";
				</script>

				<script type="text/javascript" src="scripts/kinobi/manageTitle.js"></script>

				<div id="page-title-wrapper">
					<div id="page-title">
						<ol class="breadcrumb">
							<li class="active">&nbsp;</li>
						</ol>

						<h2 id="heading">Software Title Name</h2>
					</div>

					<div class="alert-wrapper">
						<div id="error-alert" class="alert alert-danger hidden" role="alert">
							<span class="glyphicon glyphicon-exclamation-sign"></span><span id="error-msg" class="text-muted">ERROR</span>
						</div>

						<div id="title-warning-alert" class="alert alert-warning hidden" role="alert">
							<span class="glyphicon glyphicon-warning-sign"></span><span id="title-warning-msg" class="text-muted">WARNING</span>
						</div>
					</div>

					<div class="nav-tabs-wrapper">
						<ul class="nav nav-tabs nav-justified" id="top-tabs">
							<li><a id="title-tab-link" href="#title-tab" role="tab" data-toggle="tab"><small>Software Title</small></a></li>
							<li><a id="ea-tab-link" href="#ea-tab" role="tab" data-toggle="tab"><small>Extension Attributes</small></a></li>
							<li><a id="rqmts-tab-link" href="#rqmts-tab" role="tab" data-toggle="tab"><small><span id="rqmts-tab-icon" class="glyphicon glyphicon-exclamation-sign text-danger hidden-xs hidden"></span> Requirements</small></a></li>
							<li><a id="patches-tab-link" href="#patches-tab" role="tab" data-toggle="tab"><small><span id="patches-tab-icon" class="glyphicon glyphicon-exclamation-sign text-danger hidden-xs hidden"></span> Patches</small></a></li>
						</ul>
					</div>
				</div>

				<!-- Page Title Spacer -->
				<div style="margin-top: -18px;">
					<div style="height: 79px;"></div>
					<div style="padding: 0 20px;">
						<div id="spacer-alert" class="alert alert-invisible hidden" role="alert">
							<span class="glyphicon glyphicon-warning-sign"></span><span id="spacer-msg">WARNING</span>
						</div>
					</div>
					<div style="height: 58px;"></div>
				</div>

				<div class="tab-content">
					<div class="tab-pane active fade in" id="title-tab">
						<div class="page-content">
							<div class="text-muted"><small>The information in the Software Title also provides the information for the Software Title Summary object.</small></div>
						</div>

						<div class="page-content">
							<label class="control-label" for="title-name">Name <small>Name of the patch management software title.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="title-name" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="title-publisher">Publisher <small>Publisher of the patch management software title.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="title-publisher" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="title-app-name">Application Name <small>Deprecated.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="title-app-name" placeholder="[Optional]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="title-bundle-id">Bundle Identifier <small>Deprecated.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="title-bundle-id" placeholder="[Optional]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="title-current">Current Version <small>Used for reporting the latest version of the patch management software title to Jamf Pro.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="title-current" placeholder="[Required]"/>
								<select id="title-current-select" class="form-control selectpicker hidden" data-style="btn-default btn-sm" data-container="body"/></select>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<div id="title-override" class="checkbox checkbox-primary hidden">
								<input id="title-override-checkbox" class="styled" type="checkbox">
								<label><strong>Override Current Version</strong> <small>Use selected version as the latest version within Jamf Pro.</small></label>
							</div>

							<label class="control-label" for="title-name-id">ID <small>Uniquely identifies this software title on this external source.<br><strong>Note:</strong> The <span style="font-family: monospace;">id</span> cannot include any special characters or spaces.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="title-name-id" aria-describedby="title-name-id-help" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
								<span id="title-name-id-help" class="help-block hidden"><small>Duplicate ID exists</small></span>
							</div>
						</div>
					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="ea-tab">
						<div class="page-content">
							<div class="text-muted"><small>Extension attributes that are required by Jamf Pro to use this software title. Terms must be accepted in Jamf Pro.<br><strong>Note:</strong> Extension Attributes should only be used when the version information is not available from inventory data.</small></div>
						</div>

						<table id="ext-attrs" class="table table-hover" style="min-width: 768px; width: 100%; border-bottom: 1px solid #ddd;">
							<thead>
								<tr>
									<th>Name</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							</tobdy>
						</table>

						<form method="post" id="ext-attrs-form">
							<!-- Edit Extension Attribute Modal -->
							<div class="modal fade" id="edit-ea-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="edit-ea-label" aria-hidden="true">
								<div class="modal-dialog modal-lg" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="edit-ea-label">Edit Extension Attribute</h3>
										</div>
										<div class="modal-body">
											<input type="hidden" name="create_ea" id="create-ea" disabled/>
											<input type="hidden" name="save_ea" id="save-ea" disabled/>

											<label class="control-label" for="ea-name">Display Name <small>Used on the Jamf Pro Patch Management &rsaquo; Extension Attributes tab.</small></label>
											<div class="form-group">
												<input type="text" class="form-control input-sm" name="ea_name" id="ea-name" placeholder="[Required]"/>
											</div>

											<label class="control-label" for="ea-key-id">Key <small>Identifier unique within Jamf Pro. It is used by criteria objects and displayed in the Jamf Pro computer inventory information.<br><strong>Note:</strong> Duplicate keys are not allowed.</small></label>
											<div class="form-group">
												<input type="text" class="form-control input-sm" name="ea_key_id" id="ea-key-id" placeholder="[Required]"/>
												<span id="ea-key-id-help" class="help-block hidden"><small>Duplicate Key exists</small></span>
											</div>

											<label class="control-label" for="ea-editor">Script <small>Standard extension attribute script which must return a <span style="font-family:monospace;">&lt;result&gt;</span>.</small></label>
											<div id="ea-editor" class="script-editor" tabindex="-1"></div>
											<input type="hidden" name="ea_script" id="ea-script"/>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" id="cancel-ea-btn" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="button" id="save-ea-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Extension Attribute Modal -->
							<div class="modal fade" id="del-ea-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-ea-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="del-ea-label">Delete Extension Attribute?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-ea-msg">Are you sure you want to delete this extension attribute?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="delete_ea" id="del-ea-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end ext-attrs-form -->
					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="rqmts-tab">
						<div class="page-content">
							<div id="rqmts-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="rqmts-error-msg" class="text-muted">ERROR</span>
							</div>

							<div class="text-muted"><small>Criteria used to determine which computers in your environment have this software title installed.<br>The following values are the same as a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria must be ordered in the same way that smart group criteria is ordered.</small></div>
						</div>

						<table id="requirements" class="table table-hover" style="min-width: 768px; width: 100%;">
							<thead>
								<tr>
									<th>and/or</th>
									<th>Criteria</th>
									<th>Operator</th>
									<th>Value</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							</tbody>
							<tfoot>
								<tr>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#new-rqmt-modal"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tfoot>
						</table>

						<form method="post" id="requirements-form">
							<!-- New Requirement Modal -->
							<div class="modal fade" id="new-rqmt-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-rqmt-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-rqmt-label">New Requirement</h3>
										</div>
										<div class="modal-body">
											<div class="form-group">
												<label for="title-current">Criteria <small>Any valid Jamf Pro smart group criteria.</small></label>
												<select name="rqmt_name" id="new-rqmt-name" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value=" ">Select...</option>
												</select>
											</div>

											<input type="hidden" name="rqmt_operator" id="new-rqmt-operator"/>

											<input type="hidden" name="rqmt_value" id="new-rqmt-value"/>

											<input type="hidden" name="rqmt_type" id="new-rqmt-type"/>

											<input type="hidden" name="rqmt_is_and" id="new-rqmt-is-and"/>

											<input type="hidden" name="rqmt_sort_order" id="new-rqmt-sort-order"/>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="create_rqmt" id="new-rqmt-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Requirement Modal -->
							<div class="modal fade" id="del-rqmt-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-rqmt-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-rqmt" id="del-rqmt-label">Delete Requirement?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-rqmt-msg">Are you sure you want to delete this requirement?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_rqmt" id="del-rqmt-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end requirements-form -->
					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="patches-tab">
						<div class="page-content">
							<div id="patches-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="patches-error-msg" class="text-muted">ERROR</span>
							</div>

							<div id="patches-success-alert" class="alert alert-success hidden" role="alert">
								<span class="glyphicon glyphicon-ok-sign"></span><span id="patches-success-msg" class="text-muted">SUCCESS</span>
							</div>

							<div class="text-muted"><small>Software title version information; one patch is one software title version.<br><strong>Note:</strong> Must be listed in descending order with the newest version at the top of the list.</small></div>
						</div>

						<table id="patches" class="table table-hover" style="min-width: 768px; width: 100%; border-bottom: 1px solid #ddd;">
							<thead>
								<tr>
									<th>Enable</th>
									<th>Order</th>
									<th>Version</th>
									<th>Release Date</th>
									<th>Stand Alone</th>
									<th>Reboot</th>
									<th>Minimum OS</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>

						<form method="post" id="patches-form">
							<!-- New Patch Modal -->
							<div class="modal fade" id="new-patch-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-patch-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-patch-label">New Patch Version</h3>
										</div>
										<div class="modal-body">
											<input type="hidden" name="create_patch" id="create-patch" disabled/>

											<div class="form-group">
												<label class="control-label" for="new-patch-sort-order">Sort Order</label>
												<input type="text" class="form-control input-sm" name="patch_sort_order" id="new-patch-sort-order" placeholder="[Required]" value="0"/>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-patch-version">Version <small>Version associated with this patch.</small></label>
												<input type="text" class="form-control input-sm" name="patch_version" id="new-patch-version" placeholder="[Required]"/>
												<span id="new-patch-version-help" class="help-block hidden"><small>Duplicate Version exists</small></span>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-patch-released">Release Date <small>Date that this patch version was released.</small></label>
												<div class="input-group date" id="new-patch-datetimepicker">
													<span class="input-group-addon input-sm">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
													<input type="text" class="form-control input-sm" name="patch_released" id="new-patch-released" placeholder="[Required]"/>
												</div>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-patch-standalone">Standalone <small><span style="font-family:monospace;">Yes</span> specifies a patch that can be installed by itself. <span style="font-family:monospace;">No</span> specifies a patch that must be installed incrementally.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></label>
												<select name="patch_standalone" id="new-patch-standalone" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value="1">Yes</option>
													<option value="0">No</option>
												</select>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-patch-reboot">Reboot <small><span style="font-family:monospace;">Yes</span> specifies that the computer must be restarted after the patch policy has completed successfully. <span style="font-family:monospace;">No</span> specifies that the computer will not be restarted.</small></label>
												<select name="patch_reboot" id="new-patch-reboot" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value="1">Yes</option>
													<option value="0">No</option>
												</select>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-patch-min-os">Minimum Operating System <small>Lowest macOS version capable of installing this patch.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></label>
												<input type="text" class="form-control input-sm" name="patch_min_os" id="new-patch-min-os" placeholder="[Required]"/>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="button" id="new-patch-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Patch Modal -->
							<div class="modal fade" id="del-patch-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-patch-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="del-patch-label">Delete Patch Version?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-patch-msg">Are you sure you want to delete this patch version?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_patch" id="del-patch-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end patches-form -->
					</div><!-- /.tab-pane -->
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