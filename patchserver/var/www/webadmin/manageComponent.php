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
$component = array();
$criteria = array();
$ext_attrs = array();

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
	// Component
	$stmt = $pdo->prepare("SELECT components.id, components.name, components.version, source_id, title_id, titles.name AS 'title_name', app_name, bundle_id, patch_id, patches.version AS 'patch_version', patches.enabled AS 'enabled' FROM components JOIN patches ON patches.id = components.patch_id JOIN titles ON titles.id = patches.title_id WHERE components.id = ?");
	$stmt->execute(array((isset($_GET['id']) ? $_GET['id'] : null)));
	$component = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!empty($component)) {
		// Create Criteria
		if (isset($_POST['create_criteria'])) {
			$criteria_name = $_POST['criteria_name'];
			$criteria_operator = $_POST['criteria_operator'];
			$criteria_value = $_POST['criteria_value'];
			$criteria_type = $_POST['criteria_type'];
			$criteria_is_and = $_POST['criteria_is_and'];
			$criteria_sort_order = $_POST['criteria_sort_order'];
			$stmt = $pdo->prepare("UPDATE criteria SET sort_order = sort_order + 1 WHERE component_id = ? AND sort_order >= ?");
			$stmt->execute(array($component['id'], $criteria_sort_order));
			$stmt = $pdo->prepare("INSERT INTO criteria (component_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute(array($component['id'], $criteria_name, $criteria_operator, $criteria_value, $criteria_type, $criteria_is_and, $criteria_sort_order));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Delete Criteria
		if (isset($_POST['del_criteria'])) {
			$criteria_id = $_POST['del_criteria'];
			$stmt = $pdo->prepare("SELECT sort_order FROM criteria WHERE id = ?");
			$stmt->execute(array($criteria_id));
			$criteria_sort_order = $stmt->fetchColumn();
			$stmt = $pdo->prepare("UPDATE criteria SET sort_order = sort_order - 1 WHERE component_id = ? AND sort_order > ?");
			$stmt->execute(array($component['id'], $criteria_sort_order));
			$stmt = $pdo->prepare("DELETE FROM criteria WHERE id = ?");
			$stmt->execute(array($criteria_id));
			if ($stmt->errorCode() != "00000") {
				$errorInfo = $stmt->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}

		// Update Title Modified
		if (isset($_POST['create_criteria'])
		 || isset($_POST['del_criteria'])) {
			$stmt = $pdo->prepare("SELECT title_id FROM components JOIN patches ON patches.id = components.patch_id WHERE components.id = ?");
			$stmt->execute(array($component['id']));
			$title_id = $stmt->fetchColumn();
			$title_modified = time();
			$stmt = $pdo->prepare("UPDATE titles SET modified = ? WHERE id = ?");
			$stmt->execute(array($title_modified, $title_id));
		}

		// ####################################################################
		// End of GET/POST parsing
		// ####################################################################

		// Components
		$components = $pdo->query("SELECT components.id, components.version FROM components JOIN patches ON components.patch_id = patches.id WHERE title_id = " . $component['title_id'] . " ORDER BY patches.sort_order ASC, components.id DESC")->fetchAll(PDO::FETCH_ASSOC);

		// Criteria
		$stmt = $pdo->query("SELECT id, name, operator, value, type, is_and, sort_order FROM criteria WHERE component_id = " . $component['id']);
		while ($criterion = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$criterion['is_and'] = ($criterion['is_and'] == "0") ? 0 : 1;
			array_push($criteria, $criterion);
		}

		// Extension Attributes
		$ext_attrs = $pdo->query("SELECT key_id, ext_attrs.name FROM components JOIN patches ON patches.id = components.patch_id JOIN ext_attrs ON ext_attrs.title_id = patches.title_id WHERE components.id = " . $component['id'])->fetchAll(PDO::FETCH_ASSOC);
	}
}
?>
				<!-- DataTables -->
				<link rel="stylesheet" href="theme/dataTables.bootstrap.css" type="text/css"/>

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
				<!-- DataTables -->
				<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
				<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
				<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
				<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

				<!-- bootstrap-select -->
				<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>

				<script type="text/javascript">
					var pdo_error = "<?php echo $pdo_error; ?>";
					var subs_type = "<?php echo $subs_type; ?>";
					var eula_accepted = <?php echo json_encode($eula_accepted); ?>;
					var error_msg = "<?php echo $error_msg; ?>";
					var component_json = <?php echo json_encode($component); ?>;
					var components_json = <?php echo json_encode($components); ?>;
					var criteria_json = <?php echo json_encode($criteria); ?>;
					var ext_attrs_json = <?php echo json_encode($ext_attrs); ?>;
				</script>

				<script type="text/javascript" src="scripts/kinobi/manageComponent.js"></script>

				<div id="page-title-wrapper">
					<div id="page-title">
						<ol class="breadcrumb">
							<li class="active">&nbsp;</li>
						</ol>

						<div class="row">
							<div class="col-xs-9">
								<h2 id="heading">Component (Version)</h2>
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
					</div>

					<div class="nav-tabs-wrapper">
						<ul class="nav nav-tabs nav-justified" id="top-tabs">
							<li><a id="component-tab-link" href="#component-tab" role="tab" data-toggle="tab"><small>Component</small></a></li>
							<li><a id="criteria-tab-link" href="#criteria-tab" role="tab" data-toggle="tab"><small><span id="criteria-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Criteria</small></a></li>
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
					<div class="tab-pane active fade in" id="component-tab">
						<div class="page-content">
							<div class="text-muted"><small>Defines the elements that comprise this patch version.</small></div>
						</div>

						<div class="page-content">
							<label class="control-label" for="component-name">Name <small>Name of the patch management software title.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="component-name" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>

							<label class="control-label" for="component-version">Version <small>Version associated with this patch.</small></label>
							<div class="form-group">
								<input type="text" class="form-control input-sm" id="component-version" placeholder="[Required]"/>
								<span class="glyphicon glyphicon-ok form-control-feedback" aria-hidden="true"></span>
							</div>
						</div>
					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="criteria-tab">
						<div class="page-content">
							<div id="criteria-error-alert" class="alert alert-danger hidden" role="alert">
								<span class="glyphicon glyphicon-exclamation-sign"></span><span id="criteria-error-msg" class="text-muted">ERROR</span>
							</div>

							<div class="text-muted"><small>Criteria used to determine which computers in your environment have this patch version installed.<br>The following values are the same as a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria must be ordered in the same way that smart group criteria is ordered.</small></div>
						</div>

						<table id="criteria" class="table table-hover" style="min-width: 768px; width: 100%;">
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
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#new-criteria-modal"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tfoot>
						</table>

						<form method="post" id="criteria-form">
							<!-- New Criteria Modal -->
							<div class="modal fade" id="new-criteria-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="new-criteria-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-title" id="new-criteria-label">New Criteria</h3>
										</div>
										<div class="modal-body">
											<div class="form-group">
												<label for="new-criteria-name">Criteria <small>Any valid Jamf Pro smart group criteria.</small></label>
												<select name="criteria_name" id="new-criteria-name" class="form-control selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body"/>
													<option value=" ">Select...</option>
												</select>
											</div>

											<input type="hidden" name="criteria_operator" id="new-criteria-operator"/>

											<input type="hidden" name="criteria_value" id="new-criteria-value"/>

											<input type="hidden" name="criteria_type" id="new-criteria-type"/>

											<input type="hidden" name="criteria_is_and" id="new-criteria-is-and"/>

											<input type="hidden" name="criteria_sort_order" id="new-criteria-sort-order"/>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="create_criteria" id="new-criteria-btn" class="btn btn-primary btn-sm pull-right">Save</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->

							<!-- Delete Criteria Modal -->
							<div class="modal fade" id="del-criteria-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="del-criteria-label" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h3 class="modal-criteria" id="del-criteria-label">Delete Criteria?</h3>
										</div>
										<div class="modal-body">
											<div class="text-muted" id="del-criteria-msg">Are you sure you want to delete this requirement?<br><small>This action is permanent and cannot be undone.</small></div>
										</div>
										<div class="modal-footer">
											<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
											<button type="submit" name="del_criteria" id="del-criteria-btn" class="btn btn-danger btn-sm pull-right">Delete</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
						</form><!-- end criteria-form -->
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