<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.2.1
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

$title = "Software Title";

include "inc/header.php";
include "inc/patch/database.php";

// Check for subscription
$subs = getSettingSubscription($pdo);
if (!empty($subs['url']) && !empty($subs['token'])) {
	$subs_resp = fetchJsonArray($subs['url'], $subs['token']);
}

$sw_title_name_ids = array();
$ext_attr_keys = array();
$error_msg = "";
$success_msg = "";

// Standalone
$netsus = isset($conf);

if ($pdo) {

	$stmt = $pdo->prepare('SELECT id FROM titles WHERE id = ?');
	$stmt->execute(array((isset($_GET['id']) ? $_GET['id'] : null)));
	$title_id = $stmt->fetchColumn();

}

if (!empty($title_id)) {

	// Software Title
	$sw_title = $pdo->query('SELECT name, publisher, app_name, bundle_id, modified, current, name_id, enabled, source_id FROM titles WHERE id = "'.$title_id.'"')->fetch(PDO::FETCH_ASSOC);
	$sw_title['error'] = array();

	// Create Extension Attribute
	if (isset($_POST['create_ea'])) {
		$ea_key_id = $_POST['ea_key_id'][0];
		$ea_script = $_POST['ea_script'][0];
		$ea_name = $_POST['ea_name'][0];
		$stmt = $pdo->prepare('INSERT INTO ext_attrs (title_id, key_id, script, name) VALUES (?, ?, ?, ?)');
		$stmt->execute(array($title_id, $ea_key_id, $ea_script, $ea_name));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Save Extension Attribute
	if (isset($_POST['save_ea'])) {
		$ea_id = implode($_POST['save_ea']);
		$ea_key_id = $_POST['ea_key_id'][$ea_id];
		$ea_script = $_POST['ea_script'][$ea_id];
		$ea_name = $_POST['ea_name'][$ea_id];
		$stmt = $pdo->prepare('SELECT key_id FROM ext_attrs WHERE id = ?');
		$stmt->execute(array($ea_id));
		$old_key_id = $stmt->fetchColumn();
		$pdo->beginTransaction();
		$stmt = $pdo->prepare('UPDATE requirements SET name = ? WHERE name = ?');
		$stmt->execute(array($ea_key_id, $old_key_id));
		$stmt = $pdo->prepare('UPDATE capabilities SET name = ? WHERE name = ?');
		$stmt->execute(array($ea_key_id, $old_key_id));
		$stmt = $pdo->prepare('UPDATE dependencies SET name = ? WHERE name = ?');
		$stmt->execute(array($ea_key_id, $old_key_id));
		$stmt = $pdo->prepare('UPDATE criteria SET name = ? WHERE name = ?');
		$stmt->execute(array($ea_key_id, $old_key_id));
		$stmt = $pdo->prepare('UPDATE ext_attrs SET key_id = ?, script = ?, name = ? WHERE id = ?');
		$stmt->execute(array($ea_key_id, $ea_script, $ea_name, $ea_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
		$pdo->commit();
	}

	// Delete Extension Attribute
	if (isset($_POST['delete_ea'])) {
		$ea_id = $_POST['delete_ea'];
		$ea_key_id = $_POST['delete_ea_key_id'];
		$pdo->beginTransaction();
		$stmt = $pdo->prepare('DELETE FROM requirements WHERE name = ?');
		$stmt->execute(array($ea_key_id));
		$stmt = $pdo->prepare('DELETE FROM capabilities WHERE name = ?');
		$stmt->execute(array($ea_key_id));
		$stmt = $pdo->prepare('DELETE FROM dependencies WHERE name = ?');
		$stmt->execute(array($ea_key_id));
		$stmt = $pdo->prepare('DELETE FROM criteria WHERE name = ?');
		$stmt->execute(array($ea_key_id));
		$stmt = $pdo->prepare('DELETE FROM ext_attrs WHERE id = ?');
		$stmt->execute(array($ea_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
		$pdo->commit();
	}

	// Create Requirement
	if (isset($_POST['create_rqmt'])) {
		$rqmt_name = $_POST['rqmt_name'][0];
		$rqmt_operator = $_POST['rqmt_operator'][0];
		$rqmt_value = $_POST['rqmt_value'][0];
		$rqmt_type = $_POST['rqmt_type'][0];
		$rqmt_order = $_POST['rqmt_order'][0];
		$rqmt_and = "1";
		$stmt = $pdo->prepare('UPDATE requirements SET sort_order = sort_order + 1 WHERE title_id = ? AND sort_order >= ?');
		$stmt->execute(array($title_id, $rqmt_order));
		$stmt = $pdo->prepare('INSERT INTO requirements (title_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute(array($title_id, $rqmt_name, $rqmt_operator, $rqmt_value, $rqmt_type, $rqmt_and, $rqmt_order));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Requirement
	if (isset($_POST['delete_rqmt'])) {
		$rqmt_id = $_POST['delete_rqmt'];
		$rqmt_order = $_POST['delete_rqmt_order'];
		$stmt = $pdo->prepare('UPDATE requirements SET sort_order = sort_order - 1 WHERE title_id = ? AND sort_order > ?');
		$stmt->execute(array($title_id, $rqmt_order));
		$stmt = $pdo->prepare('DELETE FROM requirements WHERE id = ?');
		$stmt->execute(array($rqmt_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Create Patch
	if (isset($_POST['create_patch'])) {
		$patch_version = $_POST['patch_version'][0];
		$patch_released = date("U",strtotime($_POST['patch_released'][0]));
		$patch_standalone = ($_POST['patch_standalone'][0] == "0") ? "0" : "1";
		$patch_min_os = $_POST['patch_min_os'][0];
		$patch_reboot = ($_POST['patch_reboot'][0] == "1") ? "1" : "0";
		$patch_order = $_POST['patch_order'][0];
		$stmt = $pdo->prepare('UPDATE patches SET sort_order = sort_order + 1 WHERE title_id = ? AND sort_order >= ?');
		$stmt->execute(array($title_id, $patch_order));
		$stmt = $pdo->prepare('INSERT INTO patches (title_id, version, released, standalone, min_os, reboot, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute(array($title_id, $patch_version, $patch_released, $patch_standalone, $patch_min_os, $patch_reboot, $patch_order));
		if ($stmt->errorCode() == '00000') {
            $success_msg = "Created Patch Version '<a href=\"managePatch.php?id=".$pdo->lastInsertId()."\">".$patch_version."</a>'.";
		} else {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	if (isset($subs_resp['upload'])) {
		include $subs_resp['upload'];
	}

	// Delete Patch
	if (isset($_POST['delete_patch'])) {
		$patch_id = $_POST['delete_patch'];
		$patch_order = $_POST['delete_patch_order'];
		$patch_version = $_POST['delete_patch_version'];
		$stmt = $pdo->prepare('UPDATE patches SET sort_order = sort_order - 1 WHERE title_id = ? AND sort_order > ?');
		$stmt->execute(array($title_id, $patch_order));
		$stmt = $pdo->prepare('DELETE FROM patches WHERE id = ?');
		$stmt->execute(array($patch_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
		if ($stmt->errorCode() == '00000') {
			$success_msg = "Deleted Patch Version '".$patch_version."'.";
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
	 || isset($_POST['delete_rqmt'])
	 || isset($_POST['create_patch'])
	 || isset($_POST['delete_patch'])
	 || !empty($success_msg)) {
		$title_modified = time();
		$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
		$stmt->execute(array($title_modified, $title_id));
	}

	// ####################################################################
	// End of GET/POST parsing
	// ####################################################################

	// Software Title Name IDs
	$sw_title_name_ids = $pdo->query('SELECT name_id FROM titles WHERE id <> "'.$title_id.'" ORDER BY name_id')->fetchAll(PDO::FETCH_COLUMN);

	// Extension Attributes
	$ext_attrs = $pdo->query('SELECT id, key_id, script, name FROM ext_attrs WHERE title_id = "'.$title_id.'"')->fetchAll(PDO::FETCH_ASSOC);

	// Extension Attribute Keys
	$ext_attr_keys = $pdo->query('SELECT key_id FROM ext_attrs')->fetchAll(PDO::FETCH_COLUMN);

	// Requirements
	$requirements = array();
	$stmt = $pdo->query('SELECT id, name, operator, value, type, is_and, sort_order FROM requirements WHERE title_id = "'.$title_id.'" ORDER BY sort_order');
	while ($requirement = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$requirement['is_and'] = ($requirement['is_and'] == "0") ? "0": "1";
		array_push($requirements, $requirement);
	}
	if (sizeof($requirements) == 0) {
		array_push($sw_title['error'], "requirements");
	}

	// Patches
	$patches = array();
	$stmt = $pdo->query('SELECT id, version, released, standalone, min_os, reboot, sort_order, enabled FROM patches WHERE title_id = "'.$title_id.'" ORDER BY sort_order');
	while ($patch = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$patch['standalone'] = ($patch['standalone'] == "0") ? "0": "1";
		$patch['reboot'] = ($patch['reboot'] == "1") ? "1": "0";
		$patch['enabled'] = ($patch['enabled'] == "1") ? "1" : "0";
		$patch['error'] = array();
		$patch['components'] = $pdo->query('SELECT id FROM components WHERE patch_id = "'.$patch['id'].'"')->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($patch['components']) == 0) {
			array_push($patch['error'], "components");
		}
		foreach ($patch['components'] as $component) {
			$criteria = $pdo->query('SELECT id FROM criteria WHERE component_id = "'.$component.'"')->fetchAll(PDO::FETCH_COLUMN);
			if (sizeof($criteria) == 0) {
				array_push($patch['error'], "criteria");
			}
		}
		$patch['capabilities'] = $pdo->query('SELECT id FROM capabilities WHERE patch_id = "'.$patch['id'].'"')->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($patch['capabilities']) == 0) {
			array_push($patch['error'], "capabilities");
		}
		if (sizeof($patch['error']) > 0 && $patch['enabled'] == "1") {
			$patch['enabled'] == "0";
			$disable = $pdo->query('UPDATE patches SET enabled = 0 WHERE id = ?');
			$disable->execute(array($patch['id']));
			if ($disable->errorCode() != '00000') {
				$errorInfo = $disable->errorInfo();
				$error_msg = $errorInfo[2];
			}
		}
		array_push($patches, $patch);
	}
	$patch_versions = array_map(function($el){ return $el['version']; }, $patches);

	// Current Version
	if (count($patches) > 0) {
		if (!in_array($sw_title['current'], $patch_versions, TRUE)) {
			$stmt = $pdo->prepare('UPDATE titles SET current = ? WHERE id = ?');
			$stmt->execute(array($patch_versions[0], $title_id));
			$sw_title['current'] = $patch_versions[0];
		}
	}
	$override = $pdo->query('SELECT current FROM overrides WHERE name_id = "'.$sw_title['name_id'].'"')->fetch(PDO::FETCH_COLUMN);
	if (!empty($override)) {
		if (in_array($sw_title['current'], $patch_versions, TRUE)) {
			$sw_title['current'] = $override;
		} else {
			$stmt = $pdo->prepare('DELETE FROM overrides WHERE name_id = ?');
			$stmt->execute(array($sw_title['name_id']));
			$override = false;
		}
	}

	// Enabled Pacthes
	if (!in_array(1, array_map(function($el){ return $el['enabled']; }, $patches))) {
		array_push($sw_title['error'], "patches");
	}

	// Disable Incomplete Title
	if (sizeof($sw_title['error']) > 0 && $sw_title['enabled'] == "1") {
		$sw_title['enabled'] = "0";
		$disable = $pdo->prepare('UPDATE titles SET enabled = 0 WHERE id = ?');
		$disable->execute(array($title_id));
		if ($disable->errorCode() != '00000') {
			$errorInfo = $disable->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

} else {

	$error_msg = "Invalid Software Title ID '".(isset($_GET['id']) ? $_GET['id'] : null)."'";

}
?>
			<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css"/>
			<link rel="stylesheet" href="theme/bootstrap-datetimepicker.css" />
			<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />
			<link rel="stylesheet" href="theme/bootstrap-select.css" />

			<style>
				.btn-table {
					width: 75px;
				}
				.checkbox-error {
					font-size: 17px;
				}
				.script-editor {
					-webkit-box-flex: 1;
					-ms-flex: 1;
					flex: 1;
					border-radius: 3px;
					border: 1px solid #bfbfbf;
					margin-top: 5px;
					padding: 4px;
					pointer-events: auto;
					min-height: 306px;
				}
				#tab-content {
					margin-top: 252px;
				}
				#nav-title {
					top: 51px;
					height: 83px;
					border-bottom: 1px solid #eee;
					background: #fff;
					-webkit-transition: all 0.5s ease;
					-moz-transition: all 0.5s ease;
					-o-transition: all 0.5s ease;
					transition: all 0.5s ease;
					z-index: 90;
				}
				@media(min-width:768px) {
					.checkbox-error {
						padding-left: 16px;
					}
					#tab-content {
						margin-top: 119px;
					}
<?php if ($netsus) { ?>
					#nav-title {
						left: 220px;
					}
<?php } ?>
				}
			</style>

			<script type="text/javascript" src="scripts/moment/moment.min.js"></script>
			<script type="text/javascript" src="scripts/bootstrap/transition.js"></script>
			<script type="text/javascript" src="scripts/bootstrap/collapse.js"></script>
			<script type="text/javascript" src="scripts/datetimepicker/bootstrap-datetimepicker.min.js"></script>

			<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
			<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

			<script type="text/javascript" src="scripts/bootstrap-add-clear/bootstrap-add-clear.min.js"></script>

			<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>

			<script type="text/javascript" src="scripts/ace/ace.js"></script>
<?php if (!empty($title_id)) { ?>
			<script type="text/javascript">
				var titleEnabled = <?php echo $sw_title['enabled']; ?>;
				var existingIds = [<?php echo (sizeof($sw_title_name_ids) > 0 ? "\"".implode('", "', $sw_title_name_ids)."\"" : ""); ?>];
				var existingKeys = [<?php echo (sizeof($ext_attr_keys) > 0 ? "\"".implode('", "', $ext_attr_keys)."\"" : ""); ?>];
				var extAttrKeys = [<?php echo (sizeof($ext_attrs) > 0 ? "\"".implode('", "', array_map(function($el){ return $el['key_id']; }, $ext_attrs))."\"" : ""); ?>];
				var sizeOfEas = <?php echo sizeof($ext_attrs); ?>;
				var sizeOfRqmts = <?php echo sizeof($requirements); ?>;
				var patchVersions = [<?php echo (sizeof($patches) > 0 ? "\"".implode('", "', $patch_versions)."\"" : ""); ?>];
				var enabledPatches = <?php echo array_sum(array_map(function($el){ return $el['enabled']; }, $patches)); ?>;
			</script>

			<script type="text/javascript" src="scripts/patchValidation.js"></script>

			<script type="text/javascript">
				function showTitleDisabled() {
					$('#title-tab-icon').removeClass('hidden');
					$('#title-disabled-msg').removeClass('hidden');
				}
				function hideTitleDisabled() {
					$('#title-tab-icon').addClass('hidden');
					$('#title-disabled-msg').addClass('hidden');
				}
				function toggleTitleEnable() {
					if (sizeOfRqmts == 0 || enabledPatches == 0) {
						$('#enable_title').addClass('hidden');
					} else {
						$('#enable_title').removeClass('hidden');
					}
				}
				function showRqmtsError() {
					$('#rqmts-tab-link').css('color', '#a94442');
					$('#rqmts-tab-icon').removeClass('hidden');
					$('#rqmts-alert-msg').removeClass('hidden');
				}
				function showPatchesError() {
					$('#patches-tab-link').css('color', '#a94442');
					$('#patches-tab-icon').removeClass('hidden');
					$('#patches-alert-msg').removeClass('hidden');
				}
				function hidePatchesError() {
					$('#patches-tab-link').removeAttr('style');
					$('#patches-tab-icon').addClass('hidden');
					$('#patches-alert-msg').addClass('hidden');
				}
				function enableTitle() {
					if (titleEnabled == 0 && sizeOfRqmts > 0 && enabledPatches > 0) {
						ajaxPost('patchCtl.php?title_id=<?php echo $title_id; ?>', 'title_enabled=true');
						hideTitleDisabled();
						titleEnabled = 1;
					}
				}
				function disableTitle() {
					if (titleEnabled == 1) {
						ajaxPost('patchCtl.php?title_id=<?php echo $title_id; ?>', 'title_enabled=false');
						showTitleDisabled();
						titleEnabled = 0;
					}
				}
				function updateCurrent() {
					current = document.getElementById('current');
					override = document.getElementById('override');
					if (override.checked) {
						ajaxPost('patchCtl.php?override=<?php echo $sw_title['name_id']; ?>', 'current='+current.value);
						showSuccess(current, true);
					} else {
						updateString(current, 'titles', 'current', <?php echo $title_id; ?>, true);
						updateTimestamp(<?php echo $title_id; ?>);
					}
				}
				function toggleOverride() {
					current = document.getElementById('current');
					override = document.getElementById('override');
					current.disabled = !override.checked;
					if (override.checked) {
						ajaxPost('patchCtl.php?override=<?php echo $sw_title['name_id']; ?>', 'current='+current.value);
					} else {
						current.value = '<?php echo $patch_versions[0]; ?>';
						ajaxPost('patchCtl.php?override=<?php echo $sw_title['name_id']; ?>', 'current=');
					}
				}
				function togglePatch(element) {
					if (element.checked == true) {
						enabledPatches++
					} else {
						enabledPatches--
					}
					if (enabledPatches == 0) {
						showPatchesError();
						disableTitle();
					} else {
						hidePatchesError();
					}
					toggleTitleEnable();
					ajaxPost('patchCtl.php?patch_id='+element.value, 'patch_enabled='+element.checked);
				}
				function newRqmtModal() {
					var app_name = document.getElementById('app_name');
					var bundle_id = document.getElementById('bundle_id');
					var rqmt_name = document.getElementById('rqmt_name[0]');
					var rqmt_value = document.getElementById('rqmt_value[0]');
					if (sizeOfRqmts == 0 && sizeOfEas == 0) { rqmt_name.value = 'Application Bundle ID'; }
					if (sizeOfRqmts == 0 && sizeOfEas == 1) { rqmt_name.value = extAttrKeys[0]; }
					selectCriteria(rqmt_name, 'rqmt_type[0]', 'rqmt_operator[0]');
					if (rqmt_name.value == 'Application Bundle ID') {
						rqmt_value.value = bundle_id.value;
					} else {
						rqmt_value.value = '';
					}
					validCriteria('create_rqmt', 'rqmt_order[0]', 'rqmt_name[0]', 'rqmt_operator[0]', 'rqmt_type[0]');
				}
				function newPatchModal() {
					var current = document.getElementById('current');
					var new_version = document.getElementById('patch_version[0]');
					if (patchVersions.indexOf(current.value) == -1) {
						new_version.value = current.value;
					} else {
						new_version.value = '';
					}
					validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');
				}
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					if (titleEnabled == 0) { showTitleDisabled(); };
					if (sizeOfRqmts == 0) { showRqmtsError(); };
					if (enabledPatches == 0) { showPatchesError(); };
					toggleTitleEnable();
				});
			</script>

			<script type="text/javascript">
				$(function () {
					$('#patch_datepicker').datetimepicker({
						format: 'YYYY-MM-DDTHH:mm:ss\\Z'
					});
				});
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					$('#ext_attrs').DataTable( {
						buttons: [
							{
								text: '<span class="glyphicon glyphicon-plus"></span> New',
								className: 'btn-primary btn-sm btn-table',
								action: function ( e, dt, node, config ) {
									$("#create_ea-modal").modal();
								}
							}
						],
						"dom": "<'row'<'col-sm-12'<'dataTables_paginate'B>>>" + "<'row'<'col-sm-12'tr>>",
						"order": [ 0, 'asc' ],
						"columns": [
							null,
							{ "orderable": false }
						],
						"stateSave": true
					});

					$('#patches').DataTable( {
						buttons: [
<?php if (isset($subs_resp['upload'])) { ?>
							{
								extend: 'collection',
								text: '<span class="glyphicon glyphicon-share-alt"></span> Import</span>',
								className: 'btn-primary btn-sm btn-table',
								buttons: [
									{
										text: 'Upload JSON',
										action: function ( e, dt, node, config ) {
											$("#upload_patch-modal").modal();
										}
									}
								]
							},
<?php } ?>
							{
								text: '<span class="glyphicon glyphicon-plus"></span> New',
								className: 'btn-primary btn-sm btn-table',
								action: function ( e, dt, node, config ) {
									newPatchModal();
									$("#create_patch-modal").modal();
								}
							}
						],
						"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'<'dataTables_paginate'B>>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
						"order": [ 1, 'asc' ],
						"lengthMenu": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
						"pageLength": 10,
						"columns": [
							{ "orderable": false },
							null,
							null,
							null,
							null,
							{ "orderable": false },
							{ "orderable": false },
							{ "orderable": false }
						],
						"stateSave": true
					});
					$(':input[type=search][aria-controls=patches]').addClear({
						symbolClass: "glyphicon glyphicon-remove",
						onClear: function() {
							$('#patches').DataTable().search('').draw();
						}
					});
					$('select[name=patches_length]').addClass('table-select');
<?php if ($sw_title['source_id'] > 0) { ?>
					$('#ext_attrs').DataTable().buttons().disable();
					$('#patches').DataTable().buttons().disable();
<?php } ?>
				});
			</script>
<?php } ?>

			<script type="text/javascript">
				$(document).ready(function(){
					$('a[data-toggle="tab"]').on('show.bs.tab', function(e) {
						localStorage.setItem('activeTitleTab', $(e.target).attr('href'));
					});
					var activeTitleTab = localStorage.getItem('activeTitleTab');
					if(activeTitleTab){
						$('#top-tabs a[href="' + activeTitleTab + '"]').tab('show');
					}
				});
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					$('#settings').attr('onclick', 'document.location.href="patchSettings.php"');
				});
			</script>

			<nav id="nav-title" class="navbar navbar-default navbar-fixed-top">
				<div style="padding: 19px 20px 1px;">
					<div class="description"><a href="patchTitles.php">Patch Definitions</a> <span class="glyphicon glyphicon-chevron-right"> </span></div>
					<h2 id="heading"><?php echo (empty($title_id) ? "Error" : htmlentities($sw_title['name'])); ?></h2>
				</div>
<?php if (!empty($title_id)) { ?>
				<div style="padding: 16px 20px 0px; background-color: #f9f9f9; border-bottom: 1px solid #ddd;">
					<ul class="nav nav-tabs nav-justified" id="top-tabs" style="margin-bottom: -1px;">
						<li class="active"><a id="title-tab-link" class="tab-font" href="#title-tab" role="tab" data-toggle="tab"><span id="title-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Software Title</a></li>
						<li><a id="ea-tab-link" class="tab-font" href="#ea-tab" role="tab" data-toggle="tab"> Extension Attributes</a></li>
						<li><a id="rqmts-tab-link" class="tab-font" href="#rqmts-tab" role="tab" data-toggle="tab"><span id="rqmts-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Requirements</a></li>
						<li><a id="patches-tab-link" class="tab-font" href="#patches-tab" role="tab" data-toggle="tab"><span id="patches-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Patches</a></li>
					</ul>
				</div>
<?php } ?>
			</nav>

<?php if (!empty($title_id)) { ?>
			<form action="manageTitle.php?id=<?php echo $title_id; ?>" method="post" name="patchDefinition" id="patchDefinition">

				<div id="tab-content" class="tab-content">

					<div class="tab-pane active fade in" id="title-tab">

						<div style="padding: 16px 20px 1px;">
							<div id="title-disabled-msg" style="margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning hidden">
								<div class="panel-body">
									<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>This software title is disabled.<span id="enable_title"> <a href="" onClick="enableTitle();">Click here to enable it</a>.<span></div>
								</div>
							</div>

							<div class="text-muted" style="font-size: 12px;">The information in the Software Title also provides the information for the Software Title Summary object.</div>
						</div>

						<div style="padding: 8px 20px 1px;">
							<h5 id="name_label"><strong>Name</strong> <small>Name of the patch management software title.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" class="form-control input-sm" onFocus="validString(this, 'name_label');" onKeyUp="validString(this, 'name_label');" onChange="updateString(this, 'titles', 'name', <?php echo $title_id; ?>); updateTimestamp(<?php echo $title_id; ?>); document.getElementById('heading').innerHTML = this.value;" placeholder="[Required]" value="<?php echo htmlentities($sw_title['name']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5 id="publisher_label"><strong>Publisher</strong> <small>Publisher of the patch management software title.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" class="form-control input-sm" onFocus="validString(this, 'publisher_label');" onKeyUp="validString(this, 'publisher_label');" onChange="updateString(this, 'titles', 'publisher', <?php echo $title_id; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo htmlentities($sw_title['publisher']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5 id="app_name_label"><strong>Application Name</strong> <small>Deprecated.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" id="app_name" class="form-control input-sm" onFocus="validOrEmptyString(this, 'app_name_label');" onKeyUp="validOrEmptyString(this, 'app_name_label');" onChange="updateOrEmptyString(this, 'titles', 'app_name', <?php echo $title_id; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Optional]" value="<?php echo htmlentities($sw_title['app_name']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5 id="bundle_id_label"><strong>Bundle Identifier</strong> <small>Deprecated.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" id="bundle_id" class="form-control input-sm" onFocus="validOrEmptyString(this, 'bundle_id_label');" onKeyUp="validOrEmptyString(this, 'bundle_id_label');" onChange="updateOrEmptyString(this, 'titles', 'bundle_id', <?php echo $title_id; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Optional]" value="<?php echo htmlentities($sw_title['bundle_id']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5 id="current_label"><strong>Current Version</strong> <small>Used for reporting the latest version of the patch management software title to Jamf Pro.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
<?php if (count($patches) == 0) { ?>
								<input id="current" type="text" class="form-control input-sm" onFocus="validString(this, 'current_label');" onKeyUp="validString(this, 'current_label');" onChange="updateString(this, 'titles', 'current', <?php echo $title_id; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo htmlentities($sw_title['current']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
<?php } else { ?>
								<select id="current" class="selectpicker" data-style="btn-default btn-sm" data-width="449px" data-container="body" onChange="updateCurrent();" <?php echo ($sw_title['source_id'] > 0 ? (empty($override) ? "disabled" : "") : ""); ?>>
<?php foreach($patches as $patch) { ?>
									<option value="<?php echo htmlentities($patch['version']); ?>" <?php echo ($patch['version'] == $sw_title['current'] ? " selected" : ""); ?>><?php echo htmlentities($patch['version']); ?></option>
<?php } ?>
								</select>
<?php } ?>
							</div>
							<div class="checkbox checkbox-primary <?php echo ($sw_title['source_id'] == 0 ? "hidden" : ""); ?>">
								<input name="override" id="override" class="styled" type="checkbox" value="override" onChange="toggleOverride();" <?php echo (empty($override) ? "" : "checked") ?>>
								<label><strong>Override Current Version</strong> <span style="font-size: 75%; color: #777;">Use selected version as the latest version within Jamf Pro.</span></label>
							</div>
							<h5 id="name_id_label"><strong>ID</strong> <small>Uniquely identifies this software title on this external source.<br><strong>Note:</strong> The <span style="font-family:monospace;">id</span> cannot include any special characters or spaces.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" class="form-control input-sm" onFocus="validNameId(this, 'name_id_label');" onKeyUp="validNameId(this, 'name_id_label');" onChange="updateNameId(this, 'titles', 'name_id', <?php echo $title_id; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo htmlentities($sw_title['name_id']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
							</div>
						</div>

					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="ea-tab">

						<div style="padding: 16px 20px 1px;">
							<div class="text-muted" style="font-size: 12px;">Extension attributes that are required by Jamf Pro to use this software title. Terms must be accepted in Jamf Pro.<br><strong>Note:</strong> Extension Attributes should only be used when the version information is not available from inventory data.</div>
						</div>

						<div style="padding: 8px 20px 1px; overflow-x: auto;">
							<table id="ext_attrs" class="table table-hover" style="border-bottom: 1px solid #eee;">
								<thead>
									<tr>
										<th>Name</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
<?php foreach ($ext_attrs as $ext_attr) { ?>
									<tr>
										<td><a data-toggle="modal" href="#edit_ea-<?php echo $ext_attr['id']; ?>" data-backdrop="static" onClick="existingKeys.splice(existingKeys.indexOf('<?php echo $ext_attr['key_id']; ?>'), 1); eaNameValue = document.getElementById('ea_name[<?php echo $ext_attr['id']; ?>]').value; eaKeyValue = document.getElementById('ea_key_id[<?php echo $ext_attr['id']; ?>]').value; eaScriptValue = document.getElementById('ea_script[<?php echo $ext_attr['id']; ?>]').value;"><?php echo htmlentities($ext_attr['name']); ?></a></td>
										<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_ea-modal" onClick="$('#delete_ea-title').text('<?php echo htmlentities($ext_attr['name']); ?>'); $('#delete_ea_key_id').val('<?php echo $ext_attr['key_id']; ?>'); $('#delete_ea').val('<?php echo $ext_attr['id']; ?>');" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>>Delete</button></td>
									</tr>
<?php } ?>
								</tobdy>
							</table>
						</div>

						<!-- New EA Modal -->
						<div class="modal fade" id="create_ea-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog modal-lg" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Extension Attribute</h3>
									</div>
									<div class="modal-body">

										<h5 id="ea_name_label[0]"><strong>Display Name</strong> <small>Used on the Jamf Pro Patch Management &gt; Extension Attributes tab.</small></h5>
										<div class="form-group" style="max-width: 452px;">
											<input type="text" name="ea_name[0]" id="ea_name[0]" class="form-control input-sm" onKeyUp="validString(this, 'ea_name_label[0]'); validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');" onBlur="validString(this, 'ea_name_label[0]'); validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');" placeholder="[Required]" />
										</div>

										<h5 id="ea_key_id_label[0]"><strong>Key</strong> <small>Unique identifier within Jamf Pro. It is used by criteria objects and displayed in the Jamf Pro computer inventory information.<!-- <br><strong>Note:</strong> Duplicate keys are not allowed. --></small></h5>
										<div class="form-group" style="max-width: 452px;">
											<input type="text" name="ea_key_id[0]" id="ea_key_id[0]" class="form-control input-sm" onKeyUp="validEaKeyid(this, 'ea_key_id_label[0]'); validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');" onBlur="validEaKeyid(this, 'ea_key_id_label[0]'); validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');" placeholder="[Required]" />
										</div>

										<h5><strong>Script</strong> <small>Standard extension attribute script which must return an XML <span style="font-family:monospace;">&lt;result&gt;</span>.</small></h5>
										<input type="hidden" name="ea_script[0]" id="ea_script[0]" value="">
										<div id="ea_script0" class="script-editor" tabindex="-1"></div>
										<script>
										function setModeEditor0() {
											var currentMode = "ace/mode/" + getScriptType(editor0.getValue());
											if (editor0.getValue().indexOf("#!/bin/sh")==0) {
												currentMode = "ace/mode/sh";
											}
											editor0.session.setMode(currentMode);
											editor0.setTheme("ace/theme/xcode");
											editor0.session.setFoldStyle("markbegin");
										}
										var editor0 = ace.edit("ea_script0");
										editor0.setShowPrintMargin(false);
										setModeEditor0();
										editor0.getSession().on('change', function(e) {
											document.getElementById('ea_script[0]').value = editor0.getValue();
											setModeEditor0();
										});
										$('#ea_script0').focusin(function() {
											validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');
										});
										$('#ea_script0').focusout(function() {
											validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');
										});
										</script>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_ea" id="create_ea" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

<?php foreach ($ext_attrs as $ext_attr) { ?>
						<!-- Edit EA Modal -->
						<div class="modal fade" id="edit_ea-<?php echo $ext_attr['id']; ?>" tabindex="-1" role="dialog">
							<div class="modal-dialog modal-lg" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Edit Extension Attribute</h3>
									</div>
									<div class="modal-body">

										<h5 id="ea_name_label[<?php echo $ext_attr['id']; ?>]"><strong>Display Name</strong> <small>Used on the Jamf Pro Patch Management &gt; Extension Attributes tab.</small></h5>
										<div class="form-group" style="max-width: 452px;">
											<input type="text" name="ea_name[<?php echo $ext_attr['id']; ?>]" id="ea_name[<?php echo $ext_attr['id']; ?>]" class="form-control input-sm" onKeyUp="validString(this, 'ea_name_label[<?php echo $ext_attr['id']; ?>]'); validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');" onBlur="validString(this, 'ea_name_label[<?php echo $ext_attr['id']; ?>]'); validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');" placeholder="[Required]" value="<?php echo htmlentities($ext_attr['name']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
										</div>

										<h5 id="ea_key_id_label[<?php echo $ext_attr['id']; ?>]"><strong>Key</strong> <small>Identifier unique within Jamf Pro. It is used by criteria objects and displayed in the Jamf Pro computer inventory information.<!-- <br><strong>Note:</strong> Duplicate keys are not allowed. --></small></h5>
										<div class="form-group" style="max-width: 452px;">
											<input type="text" name="ea_key_id[<?php echo $ext_attr['id']; ?>]" id="ea_key_id[<?php echo $ext_attr['id']; ?>]" class="form-control input-sm" onKeyUp="validEaKeyid(this, 'ea_key_id_label[<?php echo $ext_attr['id']; ?>]'); validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');" onBlur="validEaKeyid(this, 'ea_key_id_label[<?php echo $ext_attr['id']; ?>]'); validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');" placeholder="[Required]" value="<?php echo htmlentities($ext_attr['key_id']); ?>" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
										</div>

										<h5><strong>Script</strong> <small>Standard extension attribute script which must return a <span style="font-family:monospace;">&lt;result&gt;</span>.</small></h5>
										<input type="hidden" name="ea_script[<?php echo $ext_attr['id']; ?>]" id="ea_script[<?php echo $ext_attr['id']; ?>]" value="<?php echo htmlentities($ext_attr['script']); ?>">
										<div id="ea_script<?php echo $ext_attr['id']; ?>" class="script-editor" tabindex="-1"><?php echo htmlentities($ext_attr['script']); ?></div>
										<script>
										function setModeEditor<?php echo $ext_attr['id']; ?>() {
											var currentMode = "ace/mode/" + getScriptType(editor<?php echo $ext_attr['id']; ?>.getValue());
											if (editor<?php echo $ext_attr['id']; ?>.getValue().indexOf("#!/bin/sh")==0) {
												currentMode = "ace/mode/sh";
											}
											editor<?php echo $ext_attr['id']; ?>.session.setMode(currentMode);
											editor<?php echo $ext_attr['id']; ?>.setTheme("ace/theme/xcode");
											editor<?php echo $ext_attr['id']; ?>.session.setFoldStyle("markbegin");
										}
										var editor<?php echo $ext_attr['id']; ?> = ace.edit("ea_script<?php echo $ext_attr['id']; ?>");
										editor<?php echo $ext_attr['id']; ?>.setShowPrintMargin(false);
										setModeEditor<?php echo $ext_attr['id']; ?>();
<?php if ($sw_title['source_id'] > 0) { ?>
										editor<?php echo $ext_attr['id']; ?>.setReadOnly(true);
										editor<?php echo $ext_attr['id']; ?>.session.setMode("ace/mode/text");
<?php } ?>
										editor<?php echo $ext_attr['id']; ?>.getSession().on('change', function(e) {
											document.getElementById('ea_script[<?php echo $ext_attr['id']; ?>]').value = editor<?php echo $ext_attr['id']; ?>.getValue();
											setModeEditor<?php echo $ext_attr['id']; ?>();
										});
<?php if ($sw_title['source_id'] == 0) { ?>
										$('#ea_script<?php echo $ext_attr['id']; ?>').focusin(function() {
											validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');
										});
										$('#ea_script<?php echo $ext_attr['id']; ?>').focusout(function() {
											validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');
										});
<?php } ?>
										</script>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" onClick="existingKeys.push('<?php echo $ext_attr['key_id']; ?>'); document.getElementById('ea_name[<?php echo $ext_attr['id']; ?>]').value = eaNameValue; document.getElementById('ea_key_id[<?php echo $ext_attr['id']; ?>]').value = eaKeyValue; hideError(document.getElementById('ea_key_id[<?php echo $ext_attr['id']; ?>]')); hideError(document.getElementById('ea_name[<?php echo $ext_attr['id']; ?>]'));">Cancel</button>
										<button type="submit" name="save_ea[<?php echo $ext_attr['id']; ?>]" id="save_ea[<?php echo $ext_attr['id']; ?>]" class="btn btn-primary btn-sm" value="<?php echo $ext_attr['id']; ?>" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->
<?php } ?>

						<!-- Delete EA Modal -->
						<div class="modal fade" id="delete_ea-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete <span id="delete_ea-title">Extension Attribute</span>?</h3>
									</div>
									<div class="modal-body">
										<input type="hidden" id="delete_ea_key_id" name="delete_ea_key_id" value=""/>
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
										<button type="submit" name="delete_ea" id="delete_ea" class="btn btn-danger btn-sm pull-right" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="rqmts-tab">

						<div style="padding: 16px 20px 1px;">
							<div id="rqmts-alert-msg" class="panel panel-danger hidden" style="margin-bottom: 16px; border-color: #d43f3a;">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>At least one requirement is required for the definition to be valid.</div>
								</div>
							</div>

							<div class="text-muted" style="font-size: 12px;">Criteria used to determine which computers in your environment have this software title installed.<br>The following values are the same as a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria must be ordered in the same way that smart group criteria is ordered.</div>
						</div>

						<div style="padding: 8px 20px 1px; overflow-x: auto;">
							<table class="table">
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
<?php foreach ($requirements as $requirement) { ?>
									<tr>
										<td>
<?php if ($requirement['sort_order'] == 0) { ?>
											<input type="hidden" value="<?php echo $requirement['is_and']; ?>" />
<?php } else { ?>
											<div class="has-feedback">
												<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateInteger(this, 'requirements', 'is_and', <?php echo $requirement['id']; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>>
													<option value="1"<?php echo ($requirement['is_and'] == "1" ? " selected" : "") ?>>and</option>
													<option value="0"<?php echo ($requirement['is_and'] == "0" ? " selected" : "") ?>>or</option>
												</select>
											</div>
<?php } ?>
										</td>
										<td>
											<div class="has-feedback">
												<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateCriteria(this, 'rqmt_operator[<?php echo $requirement['id']; ?>]', 'rqmt_type[<?php echo $requirement['id']; ?>]', 'requirements', <?php echo $requirement['id']; ?>, true); $('.selectpicker').selectpicker('refresh'); updateTimestamp(<?php echo $title_id; ?>);" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>>
<?php foreach ($ext_attrs as $ext_attr) { ?>
													<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($requirement['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
<?php } ?>
													<option value="Application Bundle ID"<?php echo ($requirement['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
													<option value="Application Title"<?php echo ($requirement['name'] == "Application Title" ? " selected" : "") ?> >Application Title</option>
													<option value="Application Version"<?php echo ($requirement['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
													<option value="Architecture Type"<?php echo ($requirement['name'] == "Architecture Type" ? " selected" : "") ?> >Architecture Type</option>
													<option value="Boot Drive Available MB"<?php echo ($requirement['name'] == "Boot Drive Available MB" ? " selected" : "") ?> >Boot Drive Available MB</option>
													<option value="Drive Capacity MB"<?php echo ($requirement['name'] == "Drive Capacity MB" ? " selected" : "") ?> >Drive Capacity MB</option>
													<option value="Make"<?php echo ($requirement['name'] == "Make" ? " selected" : "") ?> >Make</option>
													<option value="Model"<?php echo ($requirement['name'] == "Model" ? " selected" : "") ?> >Model</option>
													<option value="Model Identifier"<?php echo ($requirement['name'] == "Model Identifier" ? " selected" : "") ?> >Model Identifier</option>
													<option value="Number of Processors"<?php echo ($requirement['name'] == "Number of Processors" ? " selected" : "") ?> >Number of Processors</option>
													<option value="Operating System"<?php echo ($requirement['name'] == "Operating System" ? " selected" : "") ?> >Operating System</option>
													<option value="Operating System Build"<?php echo ($requirement['name'] == "Operating System Build" ? " selected" : "") ?> >Operating System Build</option>
													<option value="Operating System Name"<?php echo ($requirement['name'] == "Operating System Name" ? " selected" : "") ?> >Operating System Name</option>
													<option value="Operating System Version"<?php echo ($requirement['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
													<option value="Optical Drive"<?php echo ($requirement['name'] == "Optical Drive" ? " selected" : "") ?> >Optical Drive</option>
													<option value="Platform"<?php echo ($requirement['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
													<option value="Processor Speed MHz"<?php echo ($requirement['name'] == "Processor Speed MHz" ? " selected" : "") ?> >Processor Speed MHz</option>
													<option value="Processor Type"<?php echo ($requirement['name'] == "Processor Type" ? " selected" : "") ?> >Processor Type</option>
													<option value="SMC Version"<?php echo ($requirement['name'] == "SMC Version" ? " selected" : "") ?> >SMC Version</option>
													<option value="Total Number of Cores"<?php echo ($requirement['name'] == "Total Number of Cores" ? " selected" : "") ?> >Total Number of Cores</option>
													<option value="Total RAM MB"<?php echo ($requirement['name'] == "Total RAM MB" ? " selected" : "") ?> >Total RAM MB</option>
												</select>
											</div>
											<input type="hidden" id="rqmt_type[<?php echo $requirement['id']; ?>]" value="<?php echo $requirement['type']; ?>"/>
										</td>
										<td>
											<div class="has-feedback">
												<select id="rqmt_operator[<?php echo $requirement['id']; ?>]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onFocus="hideWarning(this);" onChange="updateString(this, 'requirements', 'operator', <?php echo $requirement['id']; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>>
													<option value="is"<?php echo ($requirement['operator'] == "is" ? " selected" : "") ?> >is</option>
													<option value="is not"<?php echo ($requirement['operator'] == "is not" ? " selected" : "") ?> >is not</option>
<?php
switch($requirement['name']) {
	case "Application Title": ?>
													<option value="has"<?php echo ($requirement['operator'] == "has" ? " selected" : "") ?> >has</option>
													<option value="does not have"<?php echo ($requirement['operator'] == "does not have" ? " selected" : "") ?> >does not have</option>
<?php break;
	case "Boot Drive Available MB":
	case "Drive Capacity MB":
	case "Number of Processors":
	case "Processor Speed MHz":
	case "Total Number of Cores":
	case "Total RAM MB": ?>
													<option value="more than"<?php echo ($requirement['operator'] == "more than" ? " selected" : "") ?> >more than</option>
													<option value="less than"<?php echo ($requirement['operator'] == "less than" ? " selected" : "") ?> >less than</option>
<?php break;
	case "Operating System Version": ?>
													<option value="like"<?php echo ($requirement['operator'] == "like" ? " selected" : "") ?> >like</option>
													<option value="not like"<?php echo ($requirement['operator'] == "not like" ? " selected" : "") ?> >not like</option>
													<option value="greater than"<?php echo ($requirement['operator'] == "greater than" ? " selected" : "") ?> >greater than</option>
													<option value="less than"<?php echo ($requirement['operator'] == "less than" ? " selected" : "") ?> >less than</option>
													<option value="greater than or equal"<?php echo ($requirement['operator'] == "greater than or equal" ? " selected" : "") ?> >greater than or equal</option>
													<option value="less than or equal"<?php echo ($requirement['operator'] == "less than or equal" ? " selected" : "") ?> >less than or equal</option>
<?php default: ?>
													<option value="like"<?php echo ($requirement['operator'] == "like" ? " selected" : "") ?> >like</option>
													<option value="not like"<?php echo ($requirement['operator'] == "not like" ? " selected" : "") ?> >not like</option>
													<option value="matches regex"<?php echo ($requirement['operator'] == "matches regex" ? " selected" : "") ?> >matches regex</option>
													<option value="does not match regex"<?php echo ($requirement['operator'] == "does not match regex" ? " selected" : "") ?> >does not match regex</option>
<?php } ?>
												</select>
											</div>
										</td>
										<td>
											<div class="has-feedback">
												<input type="text" class="form-control input-sm" style="min-width: 84px;" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'requirements', 'value', <?php echo $requirement['id']; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="" value="<?php echo htmlentities($requirement['value']); ?>"  <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
											</div>
										</td>
										<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_rqmt-modal" onClick="$('#delete_rqmt_order').val('<?php echo $requirement['sort_order']; ?>'); $('#delete_rqmt').val('<?php echo $requirement['id']; ?>');" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>>Delete</button></td>
									</tr>
<?php } ?>
									<tr>
										<td colspan="5" align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#create_rqmt-modal" onClick="newRqmtModal();" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>><span class="glyphicon glyphicon-plus"></span> Add</button></td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- New Requirement Modal -->
						<div class="modal fade" id="create_rqmt-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Requirement</h3>
									</div>
									<div class="modal-body">

										<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.</small></h5>
										<div class="form-group">
											<input type="hidden" name="rqmt_order[0]" id="rqmt_order[0]" value="<?php echo sizeof($requirements); ?>" />
											<select id="rqmt_name[0]" name="rqmt_name[0]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" title="Select..." onChange="selectCriteria(this, 'rqmt_type[0]', 'rqmt_operator[0]'); validCriteria('create_rqmt', 'rqmt_order[0]', 'rqmt_name[0]', 'rqmt_operator[0]', 'rqmt_type[0]');" >
<?php foreach ($ext_attrs as $ext_attr) { ?>
												<option value="<?php echo $ext_attr['key_id']; ?>"><?php echo $ext_attr['name']; ?></option>
<?php } ?>
												<option value="Application Bundle ID">Application Bundle ID</option>
												<option value="Application Title">Application Title</option>
												<option value="Application Version">Application Version</option>
												<option value="Architecture Type">Architecture Type</option>
												<option value="Boot Drive Available MB">Boot Drive Available MB</option>
												<option value="Drive Capacity MB">Drive Capacity MB</option>
												<option value="Make">Make</option>
												<option value="Model">Model</option>
												<option value="Model Identifier">Model Identifier</option>
												<option value="Number of Processors">Number of Processors</option>
												<option value="Operating System">Operating System</option>
												<option value="Operating System Build">Operating System Build</option>
												<option value="Operating System Name">Operating System Name</option>
												<option value="Operating System Version">Operating System Version</option>
												<option value="Optical Drive">Optical Drive</option>
												<option value="Platform">Platform</option>
												<option value="Processor Speed MHz">Processor Speed MHz</option>
												<option value="Processor Type">Processor Type</option>
												<option value="SMC Version">SMC Version</option>
												<option value="Total Number of Cores">Total Number of Cores</option>
												<option value="Total RAM MB">Total RAM MB</option>
											</select>
											<input type="hidden" name="rqmt_type[0]" id="rqmt_type[0]" value="recon" />
											<input type="hidden" name="rqmt_operator[0]" id="rqmt_operator[0]" value="is" />
											<input type="hidden" name="rqmt_value[0]" id="rqmt_value[0]" value="" />
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_rqmt" id="create_rqmt" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete Requirement Modal -->
						<div class="modal fade" id="delete_rqmt-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete Requirement?</h3>
									</div>
									<div class="modal-body">
										<input type="hidden" id="delete_rqmt_order" name="delete_rqmt_order" value=""/>
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_rqmt" id="delete_rqmt" class="btn btn-danger btn-sm" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="patches-tab">

						<div style="padding: 16px 20px 1px;">
							<div id="patches-alert-msg" class="panel panel-danger hidden" style="margin-bottom: 16px; border-color: #d43f3a;">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>At least one patch must be enabled for the definition to be valid.</div>
								</div>
							</div>

							<div style="margin-top: 0px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (!empty($success_msg) ? "" : "hidden"); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $success_msg; ?></div>
								</div>
							</div>

							<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (!empty($error_msg) ? "" : "hidden"); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $error_msg; ?></div>
								</div>
							</div>

							<div class="text-muted" style="font-size: 12px;">Software title version information; one patch is one software title version.<br><strong>Note:</strong> Must be listed in descending order with the newest version at the top of the list.</div>
						</div>

						<div style="padding: 8px 20px 1px; overflow-x: auto;">
							<table id="patches" class="table table-hover" style="border-bottom: 1px solid #eee;">
								<thead>
									<tr>
										<th>Enable</th>
										<th>Order</th>
										<th>Version</th>
										<th>Release Date</th>
										<th>Minimum OS</th>
										<th>Stand Alone</th>
										<th>Reboot</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
<?php foreach ($patches as $patch) { ?>
									<tr>
										<td>
<?php if (sizeof($patch['error']) == 0) { ?>
											<div class="checkbox checkbox-primary checkbox-inline">
												<input type="checkbox" class="styled" name="enable_patch" id="enable_patch" value="<?php echo $patch['id']; ?>" onChange="togglePatch(this); updateTimestamp(<?php echo $title_id; ?>);" <?php echo ($patch['enabled'] == "1") ? "checked" : ""; ?> <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>/>
												<label/>
											</div>
<?php } else { ?>
											<div style="checkbox checkbox-danger checkbox-inline">
												<a href="managePatch.php?id=<?php echo $patch['id']; ?>"><span class="text-danger glyphicon glyphicon-exclamation-sign checkbox-error"></span></a>
											</div>
<?php } ?>
										</td>
										<td><input type="hidden" name="patch_order[<?php echo $patch['id']; ?>]" value="<?php echo $patch['sort_order']; ?>"/><?php echo $patch['sort_order']; ?></td>
										<td><a href="managePatch.php?id=<?php echo $patch['id']; ?>"><?php echo htmlentities($patch['version']); ?></a></td>
										<td><?php echo gmdate("Y-m-d\TH:i:s\Z", $patch['released']); ?></td>
										<td><?php echo htmlentities($patch['min_os']); ?></td>
										<td><?php echo ($patch['standalone'] == "1" ? "Yes" : "No") ?></td>
										<td><?php echo ($patch['reboot'] == "1" ? "Yes" : "No") ?></td>
										<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_patch-modal" onClick="$('#delete_patch-title').text('<?php echo htmlentities($patch['version']); ?>'); $('#delete_patch_version').val('<?php echo $patch['version']; ?>'); $('#delete_patch_order').val('<?php echo $patch['sort_order']; ?>'); $('#delete_patch').val('<?php echo $patch['id']; ?>');" <?php echo ($sw_title['source_id'] > 0 ? "disabled" : ""); ?>>Delete</button></td>
									</tr>
<?php } ?>
								</tbody>
							</table>
						</div>

						<!-- New Patch Modal -->
						<div class="modal fade" id="create_patch-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Patch</h3>
									</div>
									<div class="modal-body">

										<h5 id="patch_order_label[0]"><strong>Sort Order</strong></h5>
										<div class="form-group">
											<input type="text" name="patch_order[0]" id="patch_order[0]" class="form-control input-sm" onKeyUp="validInteger(this, 'patch_order_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" onBlur="validInteger(this, 'patch_order_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" value="0" />
										</div>

										<h5 id="patch_version_label[0]"><strong>Version</strong> <small>Version associated with this patch.</small></h5>
										<div class="form-group">
											<input type="text" name="patch_version[0]" id="patch_version[0]" class="form-control input-sm" onKeyUp="validVersion(this, 'patch_version_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" onBlur="validVersion(this, 'patch_version_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" value="" />
										</div>

										<h5 id="patch_released_label[0]"><strong>Release Date</strong> <small>Date that this patch version was released.</small></h5>
										<div class="form-group">
											<div class="input-group date" id="patch_datepicker">
												<span class="input-group-addon input-sm" style="color: #555; background-color: #eee; border: 1px solid #ccc; border-right: 0;">
													<span class="glyphicon glyphicon-calendar"></span>
												</span>
												<input type="text" name="patch_released[0]" id="patch_released[0]" class="form-control input-sm" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" onFocus="validDate(this, 'patch_released_label[0]');" onKeyUp="validDate(this, 'patch_released_label[0]');" onBlur="validDate(this, 'patch_released_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" value="<?php echo gmdate("Y-m-d\TH:i:s\Z", time()); ?>" />
											</div>
										</div>

										<h5><strong>Standalone</strong> <small><span style="font-family:monospace;">Yes</span> specifies a patch that can be installed by itself. <span style="font-family:monospace;">No</span> specifies a patch that must be installed incrementally.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></h5>
										<select id="patch_standalone[0]" name="patch_standalone[0]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body">
											<option value="1">Yes</option>
											<option value="0">No</option>
										</select>

										<h5 id="patch_min_os_label[0]"><strong>Minimum Operating System</strong> <small>Lowest macOS version capable of installing this patch.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></h5>
										<div class="form-group">
											<input type="text" name="patch_min_os[0]" id="patch_min_os[0]" class="form-control input-sm" onKeyUp="validString(this, 'patch_min_os_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" onBlur="validString(this, 'patch_min_os_label[0]'); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" value="<?php echo (sizeof($patches) > 0 ? $patches[0]['min_os'] : ""); ?>" />
										</div>

										<h5><strong>Reboot</strong> <small><span style="font-family:monospace;">Yes</span> specifies that the computer must be restarted after the patch policy has completed successfully. <span style="font-family:monospace;">No</span> specifies that the computer will not be restarted.</small></h5>
										<select id="patch_reboot[0]" name="patch_reboot[0]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body">
											<option value="0">No</option>
											<option value="1">Yes</option>
										</select>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_patch" id="create_patch" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete Patch Modal -->
						<div class="modal fade" id="delete_patch-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete Patch <span id="delete_patch-title">Version</span>?</h3>
									</div>
									<div class="modal-body">
										<input type="hidden" id="delete_patch_order" name="delete_patch_order" value=""/>
										<input type="hidden" id="delete_patch_version" name="delete_patch_version" value=""/>
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_patch" id="delete_patch" class="btn btn-danger btn-sm pull-right" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div><!-- /.tab-pane -->

				</div> <!-- end .tab-content -->
			</form><!-- end form patchDefinition -->

			<script type="text/javascript">
				$(document).ready(function() {
					$('.table-select').selectpicker({
						style: 'btn-default btn-sm',
						width: 'fit',
						container: 'body'
					});
				});
			</script>
<?php } else { ?>
			<div style="padding: 64px 20px 0px; background-color: #f9f9f9; border-bottom: 1px solid #ddd;">
				<div id="error-msg" style="margin-top: 16px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
					<div class="panel-body">
						<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $error_msg; ?></div>
					</div>
				</div>
			</div>
<?php }
include "inc/footer.php"; ?>