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
include "inc/patch/database.php";

$error_msg = null;
$patch = array();
$patches = array();
$ext_attrs = array();
$prev_id = null;
$next_id = null;
$components = array();
$title_comp_names = array();
$dependencies = array();
$capabilities = array();
$kill_apps = array();
$title_kill_apps = array();

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
	// Patch
	$stmt = $pdo->prepare("SELECT name, app_name, bundle_id, title_id, source_id, patches.id, version, released, standalone, min_os, reboot, sort_order, patches.enabled FROM patches JOIN titles ON titles.id = patches.title_id WHERE patches.id = ?");
	$stmt->execute(array((isset($_GET['id']) ? $_GET['id'] : null)));
	$patch = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!empty($patch)) {

		// Create Component
		if (isset($_POST['create_comp'])) {
			$comp_name = $_POST['comp_name'];
			$comp_version = $_POST['comp_version'];
			$stmt = $pdo->prepare('INSERT INTO components (patch_id, name, version) VALUES (?, ?, ?)');
			$stmt->execute(array($patch['id'], $comp_name, $comp_version));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Delete Component
		if (isset($_POST['del_comp'])) {
			$comp_id = $_POST['del_comp'];
			$stmt = $pdo->prepare('DELETE FROM components WHERE id = ?');
			$stmt->execute(array($comp_id));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Create Dependency
		/* if (isset($_POST['create_dep'])) {
			$dep_name = $_POST['dep_name'];
			$dep_operator = $_POST['dep_operator'];
			$dep_value = $_POST['dep_value'];
			$dep_type = $_POST['dep_type'];
			$dep_is_and = $_POST['dep_is_and'];
			$dep_sort_order = $_POST['dep_sort_order'];
			$stmt = $pdo->prepare('UPDATE dependencies SET sort_order = sort_order + 1 WHERE patch_id = ? AND sort_order >= ?');
			$stmt->execute(array($patch['id'], $dep_sort_order));
			$stmt = $pdo->prepare('INSERT INTO dependencies (patch_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
			$stmt->execute(array($patch['id'], $dep_name, $dep_operator, $dep_value, $dep_type, $dep_is_and, $dep_sort_order));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		} */

		// Delete Dependency
		/* if (isset($_POST['del_dep'])) {
			$dep_id = $_POST['del_dep'];
			$stmt = $pdo->prepare('SELECT sort_order FROM dependencies WHERE id = ?');
			$stmt->execute(array($dep_id));
			$dep_sort_order = $stmt->fetchColumn();
			$stmt = $pdo->prepare('UPDATE dependencies SET sort_order = sort_order - 1 WHERE patch_id = ? AND sort_order > ?');
			$stmt->execute(array($patch['id'], $dep_sort_order));
			$stmt = $pdo->prepare('DELETE FROM dependencies WHERE id = ?');
			$stmt->execute(array($dep_id));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		} */

		// Create Capability
		if (isset($_POST['create_cap'])) {
			$cap_name = $_POST['cap_name'];
			$cap_operator = $_POST['cap_operator'];
			$cap_value = $_POST['cap_value'];
			$cap_type = $_POST['cap_type'];
			$cap_is_and = $_POST['cap_is_and'];
			$cap_sort_order = $_POST['cap_sort_order'];
			$stmt = $pdo->prepare('UPDATE capabilities SET sort_order = sort_order + 1 WHERE patch_id = ? AND sort_order >= ?');
			$stmt->execute(array($patch['id'], $cap_sort_order));
			$stmt = $pdo->prepare('INSERT INTO capabilities (patch_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
			$stmt->execute(array($patch['id'], $cap_name, $cap_operator, $cap_value, $cap_type, $cap_is_and, $cap_sort_order));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Delete Capability
		if (isset($_POST['del_cap'])) {
			$cap_id = $_POST['del_cap'];
			$stmt = $pdo->prepare('SELECT sort_order FROM capabilities WHERE id = ?');
			$stmt->execute(array($cap_id));
			$cap_sort_order = $stmt->fetchColumn();
			$stmt = $pdo->prepare('UPDATE capabilities SET sort_order = sort_order - 1 WHERE patch_id = ? AND sort_order > ?');
			$stmt->execute(array($patch['id'], $cap_sort_order));
			$stmt = $pdo->prepare('DELETE FROM capabilities WHERE id = ?');
			$stmt->execute(array($cap_id));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Create Kill Application
		if (isset($_POST['create_kill_app'])) {
			$kill_app_bundle_id = $_POST['kill_app_bundle_id'];
			$kill_app_name = $_POST['kill_app_name'];
			$stmt = $pdo->prepare('INSERT INTO kill_apps (patch_id, bundle_id, app_name) VALUES (?, ?, ?)');
			$stmt->execute(array($patch['id'], $kill_app_bundle_id, $kill_app_name));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Delete Kill Application
		if (isset($_POST['del_kill_app'])) {
			$kill_app_id = $_POST['del_kill_app'];
			$stmt = $pdo->prepare('DELETE FROM kill_apps WHERE id = ?');
			$stmt->execute(array($kill_app_id));
			if ($stmt->errorCode() != '00000') {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Update Title Modified
		if (isset($_POST['create_comp'])
		 || isset($_POST['del_comp'])
		 || isset($_POST['create_dep'])
		 || isset($_POST['del_dep'])
		 || isset($_POST['create_cap'])
		 || isset($_POST['del_cap'])
		 || isset($_POST['create_kill_app'])
		 || isset($_POST['del_kill_app'])) {
			$stmt = $pdo->prepare('SELECT title_id FROM patches WHERE id = ?');
			$stmt->execute(array($patch['id']));
			$title_id = $stmt->fetchColumn();
			$title_modified = time();
			$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
			$stmt->execute(array($title_modified, $title_id));
		}

		// ####################################################################
		// End of GET/POST parsing
		// ####################################################################

		// Patch
		$patch['standalone'] = ($patch['standalone'] == "0") ? "0": "1";
		$patch['reboot'] = ($patch['reboot'] == "1") ? "1": "0";
		$patch['enabled'] = ($patch['enabled'] == "1") ? "1" : "0";
		$patch['error'] = array();

		// Patch Versions
		$patches = $pdo->query("SELECT id, version FROM patches WHERE title_id = " . $patch['title_id'] . " ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

		// Extension Attributes
		$ext_attrs = $pdo->query("SELECT key_id, name FROM ext_attrs WHERE title_id = " . $patch['title_id'])->fetchAll(PDO::FETCH_ASSOC);

		// Begin Legacy to remove
		// Previous Patch
		$prev_id = $pdo->query('SELECT id FROM patches WHERE title_id = ' . $patch['title_id'] . ' AND sort_order = ' . (+$patch['sort_order'] - 1))->fetch(PDO::FETCH_COLUMN);

		// Next Patch
		$next_id = $pdo->query('SELECT id FROM patches WHERE title_id = ' . $patch['title_id'] . ' AND sort_order = ' . (+$patch['sort_order'] + 1))->fetch(PDO::FETCH_COLUMN);
		// End Legacy

		// Components
		$stmt = $pdo->query('SELECT id, name, version FROM components WHERE patch_id = "'.$patch['id'].'"');
		while ($component = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$component['criteria'] = $pdo->query('SELECT COUNT(id) FROM criteria WHERE component_id = ' . $component['id'])->fetch(PDO::FETCH_COLUMN);
			if ($component['criteria'] == 0) {
				array_push($patch['error'], "criteria");
			}
			array_push($components, $component);
		}
		if (sizeof($components) == 0) {
			array_push($patch['error'], "components");
		}
		$title_comp_names = $pdo->query('SELECT DISTINCT name FROM patches JOIN components ON patches.id = components.patch_id WHERE patches.title_id = ' . $patch['title_id'])->fetchAll(PDO::FETCH_COLUMN);

		// Dependencies
		/* $stmt = $pdo->query('SELECT id, name, operator, value, type, is_and, sort_order FROM dependencies WHERE patch_id = ' . $patch['id']);
		while ($dependency = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$dependency['is_and'] = ($dependency['is_and'] == "0") ? "0": "1";
			array_push($dependencies, $dependency);
		} */

		// Capabilities
		$stmt = $pdo->query('SELECT id, name, operator, value, type, is_and, sort_order FROM capabilities WHERE patch_id = "'.$patch['id'].'"');
		while ($capability = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$capability['is_and'] = ($capability['is_and'] == "0") ? "0": "1";
			array_push($capabilities, $capability);
		}
		if (sizeof($capabilities) == 0) {
			array_push($patch['error'], "capabilities");
		}

		// Kill Applications
		$kill_apps = $pdo->query('SELECT id, bundle_id, app_name FROM kill_apps WHERE patch_id = ' . $patch['id'])->fetchAll(PDO::FETCH_ASSOC);
		$title_kill_apps = $pdo->query('SELECT DISTINCT bundle_id, app_name FROM patches JOIN kill_apps ON patches.id = kill_apps.patch_id WHERE patches.title_id = ' . $patch['title_id'])->fetchAll(PDO::FETCH_ASSOC);

		// Disable Incomplete Patch
		if (sizeof($patch['error']) > 0 && $patch['enabled'] == "1") {
			$patch['enabled'] = "0";
			$disable = $pdo->prepare('UPDATE patches SET enabled = 0 WHERE id = ?');
			$disable->execute(array($patch['id']));
			if ($disable->errorCode() != '00000') {
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

<?php if ($netsus) { ?>
				<!-- Custom styles for this page -->
				<style>
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
<?php if ($netsus == 4) { ?>
						#page-content-wrapper {
							padding-left: 220px;
						}
<?php } ?>
					}
				</style>

<?php } ?>
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

				<!-- bootstrap-select -->
				<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>

				<script type="text/javascript">
					var pdo_error = "<?php echo htmlentities($pdo_error); ?>";
					var subs_type = "<?php echo $subs_type; ?>";
					var eula_accepted = <?php echo json_encode($eula_accepted); ?>;
					var error_msg = '<?php echo $error_msg; ?>';
					var patch_json = <?php echo json_encode($patch); ?>;
					var patches_json = <?php echo json_encode($patches); ?>;
					var ext_attrs_json = <?php echo json_encode($ext_attrs); ?>;
					var components_json = <?php echo json_encode($components); ?>;
					var title_comp_names_json = <?php echo json_encode($title_comp_names); ?>;
					// var dependencies_json = <?php echo json_encode($dependencies); ?>;
					var capabilities_json = <?php echo json_encode($capabilities); ?>;
					var kill_apps_json = <?php echo json_encode($kill_apps); ?>;
					var title_kill_apps_json = <?php echo json_encode($title_kill_apps); ?>;
				</script>

				<script type="text/javascript" src="scripts/kinobi/managePatch.js"></script>

				<div id="page-title-wrapper">
					<div id="page-title">
						<ol class="breadcrumb">
							<li class="active">&nbsp;</li>
						</ol>

						<div class="row">
							<div class="col-xs-9">
								<h2 id="heading">Patch Version</h2>
							</div>

							<div class="col-xs-3 text-right">
								<div class="btn-group btn-group-sm" role="group">
									<a id="prev-btn" class="btn btn-default disabled"><span class="glyphicon glyphicon-chevron-left"></span></a>
									<a id="next-btn" class="btn btn-default disabled"><span class="glyphicon glyphicon-chevron-right"></span></a>
								</div>
							</div>
						</div>
					</div>

					<div class="alert-wrapper">
						<div id="error-alert" class="alert alert-danger hidden" role="alert">
							<span class="glyphicon glyphicon-exclamation-sign"></span><span id="error-msg" class="text-muted">ERROR</span>
						</div>

						<div id="patch-warning-alert" class="alert alert-warning hidden" role="alert">
							<span class="glyphicon glyphicon-warning-sign"></span><span id="patch-warning-msg" class="text-muted">WARNING</span>
						</div>
					</div>

					<div class="nav-tabs-wrapper">
						<ul class="nav nav-tabs nav-justified" id="top-tabs">
							<li class="active"><a id="patch-tab-link" href="#patch-tab" role="tab" data-toggle="tab"><small>Patch</small></a></li>
							<li><a id="components-tab-link" href="#components-tab" role="tab" data-toggle="tab"><small><span id="components-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Components</small></a></li>
							<!-- <li><a id="dependencies-tab-link" href="#dependencies-tab" role="tab" data-toggle="tab"><small>Dependencies</small></a></li> -->
							<li><a id="capabilities-tab-link" href="#capabilities-tab" role="tab" data-toggle="tab"><small><span id="capabilities-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Capabilities</small></a></li>
							<li><a id="killapps-tab-link" href="#killapps-tab" role="tab" data-toggle="tab"><small>Kill Applications</small></a></li>
						</ul>
					</div>
				</div>

				<!-- Page Title Spacer -->
				<div style="margin-top: -18px;">
					<div style="height: 79px;"></div>
					<div style="padding: 0 20px;">
						<div id="spacer-alert" class="alert alert-invisible hidden" role="alert">
							<span class="glyphicon glyphicon-warning-sign"></span><span id="spacer-msg">&nbsp;</span>
						</div>
					</div>
					<div style="height: 58px;"></div>
				</div>

				<div id="tab-content" class="tab-content">
					<div class="tab-pane active fade in" id="patch-tab">
						<div class="page-content">
							<div class="text-muted"><small>Software title version information; one patch is one software title version.</small></div>
						</div>

						<div class="page-content">
							<label class="control-label" for="patch-sort-order">Sort Order</label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="patch-sort-order" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="patch-version">Version <small>Version associated with this patch.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="patch-version" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
								<span id="patch-version-help" class="help-block hidden"><small>Duplicate Version exists</small></span>
							</div>

							<label class="control-label" for="patch-released">Release Date <small>Date that this patch version was released.</small></label>
							<div class="form-group">
								<div class="input-group date" id="patch-datetimepicker">
									<span class="input-group-addon input-sm">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
									<input type="text" class="form-control input-sm" id="patch-released" placeholder="[Required]"/>
								</div>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="patch-standalone">Standalone <small><span style="font-family:monospace;">Yes</span> specifies a patch that can be installed by itself. <span style="font-family:monospace;">No</span> specifies a patch that must be installed incrementally.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></label>
							<div class="form-group">
								<select id="patch-standalone" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
									<option value="1">Yes</option>
									<option value="0">No</option>
								</select>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="patch-reboot">Reboot <small><span style="font-family:monospace;">Yes</span> specifies that the computer must be restarted after the patch policy has completed successfully. <span style="font-family:monospace;">No</span> specifies that the computer will not be restarted.</small></label>
							<div class="form-group">
								<select id="patch-reboot" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
									<option value="1">Yes</option>
									<option value="0">No</option>
								</select>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="patch-min-os">Minimum Operating System <small>Lowest macOS version capable of installing this patch.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="patch-min-os" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>
						</div>
					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="components-tab">
						<div class="page-content">
							<div id="components-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="components-error-msg" class="text-muted">ERROR</span>
							</div>

							<div class="text-muted"><small>Defines the elements that comprise this patch version.<br><strong>Note:</strong> Only one element is supported by Jamf Pro at this time.</small></div>
						</div>

						<table id="components" class="table table-hover" style="min-width: 768px; width: 100%; border-bottom: 1px solid #ddd;">
							<thead>
								<tr>
									<th></th>
									<th>Name</th>
									<th>Version</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>

						<form method="post" id="components-form">
							<!-- New Component Modal -->
							<div class="modal fade" id="new-comp-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-comp-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-comp-label">New Component</h3>
										</div>
										<div class="modal-body">
											<input type="hidden" name="create_comp" id="create-comp" disabled/>

											<div class="form-group">
												<label class="control-label" for="new-comp-name">Name <small>Name of the patch management software title.</small></label>
												<input type="text" class="form-control input-sm" name="comp_name" id="new-comp-name" placeholder="[Required]"/>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-comp-version">Version <small>Version associated with this patch.</small></label>
												<input type="text" class="form-control input-sm" name="comp_version" id="new-comp-version" placeholder="[Required]"/>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
											<button type="button" id="new-comp-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Component Modal -->
							<div class="modal fade" id="del-comp-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-comp-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="del-comp-label">Delete Component?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-comp-msg">Are you sure you want to delete this component?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_comp" id="del-comp-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end components-form -->
					</div><!-- /.tab-pane -->

					<!--
					<div class="tab-pane fade in" id="dependencies-tab">
						<div class="page-content">
							<div class="text-muted"><small>Not currently used by Jamf Pro.</small></div>
						</div>

						<table id="dependencies" class="table table-hover" style="min-width: 768px; width: 100%;">
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
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#new-dep-modal"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tfoot>
						</table>

						<form method="post" id="dependencies-form">
							<!~~ New Dependency Modal ~~>
							<div class="modal fade" id="new-dep-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-dep-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-dep-label">New Dependency</h3>
										</div>
										<div class="modal-body">
											<div class="form-group">
												<label for="title-current">Criteria <small>Any valid Jamf Pro smart group criteria.</small></label>
												<select name="dep_name" id="new-dep-name" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value=" ">Select...</option>
												</select>
											</div>

											<input type="hidden" name="dep_operator" id="new-dep-operator"/>

											<input type="hidden" name="dep_value" id="new-dep-value"/>

											<input type="hidden" name="dep_type" id="new-dep-type"/>

											<input type="hidden" name="dep_is_and" id="new-dep-is-and"/>

											<input type="hidden" name="dep_sort_order" id="new-dep-sort-order"/>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="create_dep" id="new-dep-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!~~ /.modal-content ~~>
								</div><!~~ /.modal-dialog ~~>
							</div><!~~ /.modal ~~>

							<!~~ Delete Dependency Modal ~~>
							<div class="modal fade" id="del-dep-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-dep-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-dep" id="del-dep-label">Delete Dependency?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-dep-msg">Are you sure you want to delete this dependency?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_dep" id="del-dep-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!~~ /.modal-content ~~>
								</div><!~~ /.modal-dialog ~~>
							</div><!~~ /.modal ~~>
						</form><!~~ end dependencies-form ~~>
					</div> <!~~ /.tab-pane ~~>
 					-->

					<div class="tab-pane fade in" id="capabilities-tab">
						<div class="page-content">
							<div id="capabilities-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="capabilities-error-msg" class="text-muted">ERROR</span>
							</div>

							<div class="text-muted"><small>Criteria used to determine which computers in your environment have the ability to install and run this patch.<br>The following values are the same as a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria must be ordered in the same way that smart group criteria is ordered.</small></div>
						</div>

						<table id="capabilities" class="table table-hover" style="min-width: 768px; width: 100%;">
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
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#new-cap-modal"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tfoot>
						</table>

						<form method="post" id="capabilities-form">
							<!-- New Capability Modal -->
							<div class="modal fade" id="new-cap-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-cap-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-cap-label">New Capability</h3>
										</div>
										<div class="modal-body">
											<div class="form-group">
												<label for="title-current">Criteria <small>Any valid Jamf Pro smart group criteria.</small></label>
												<select name="cap_name" id="new-cap-name" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value=" ">Select...</option>
												</select>
											</div>

											<input type="hidden" name="cap_operator" id="new-cap-operator"/>

											<input type="hidden" name="cap_value" id="new-cap-value"/>

											<input type="hidden" name="cap_type" id="new-cap-type"/>

											<input type="hidden" name="cap_is_and" id="new-cap-is-and"/>

											<input type="hidden" name="cap_sort_order" id="new-cap-sort-order"/>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="create_cap" id="new-cap-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Capability Modal -->
							<div class="modal fade" id="del-cap-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-cap-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-cap" id="del-cap-label">Delete Capability?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-cap-msg">Are you sure you want to delete this capability?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_cap" id="del-cap-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end capabilities-form -->
					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="killapps-tab">
						<div class="page-content">
							<div class="text-muted"><small>Specifies processes that will be stopped before a patch policy runs.</small></div>
						</div>

						<table id="kill-apps" class="table table-hover" style="min-width: 768px; width: 100%; border-bottom: 1px solid #ddd;">
							<thead>
								<tr>
									<th>Application Name</th>
									<th>Bundle Identifier</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>

						<form method="post" id="kill-apps-form">
							<!-- New Kill App Modal -->
							<div class="modal fade" id="new-kill-app-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-kill-app-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-kill-app-label">New Kill App</h3>
										</div>
										<div class="modal-body">
											<input type="hidden" name="create_kill_app" id="create-kill-app" disabled/>

											<div class="form-group">
												<label class="control-label" for="new-kill-app-name">Application Name <small>Name of the application that will be stopped before a patch policy runs.</small></label>
												<input type="text" class="form-control input-sm" name="kill_app_name" id="new-kill-app-name" placeholder="[Required]"/>
											</div>

											<div class="form-group">
												<label class="control-label" for="new-kill-app-version">Bundle Identifier <small>Bundle identifier of the applications that will be stopped before a patch policy runs.</small></label>
												<input type="text" class="form-control input-sm" name="kill_app_bundle_id" id="new-kill-app-bundle-id" placeholder="[Required]"/>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
											<button type="button" id="new-kill-app-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Kill App Modal -->
							<div class="modal fade" id="del-kill-app-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-kill-app-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="del-kill-app-label">Delete Kill App?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-kill-app-msg">Are you sure you want to delete this kill app?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_kill_app" id="del-kill-app-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end kill-apps-form -->
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