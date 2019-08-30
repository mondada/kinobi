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

$title = "Patch";

include "inc/header.php";
include "inc/patch/database.php";

$patches_select = array();
$error_msg = "";

// Standalone
$netsus = isset($conf);

if ($pdo) {
	$stmt = $pdo->prepare('SELECT patches.id, source_id FROM patches JOIN titles ON titles.id = patches.title_id WHERE patches.id = ?');
	$stmt->execute(array((isset($_GET['id']) ? $_GET['id'] : null)));
	while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$patch_id = $result['id'];
		$source_id = $result['source_id'];
	}
} else {
	$patch_id = null;
}

if (!empty($patch_id)) {

	// Create Component
	if (isset($_POST['create_comp'])) {
		$comp_name = $_POST['comp_name'][0];
		$comp_version = $_POST['comp_version'][0];
		$stmt = $pdo->prepare('INSERT INTO components (patch_id, name, version) VALUES (?, ?, ?)');
		$stmt->execute(array($patch_id, $comp_name, $comp_version));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Component
	if (isset($_POST['delete_comp'])) {
		$comp_id = $_POST['delete_comp'];
		$stmt = $pdo->prepare('DELETE FROM components WHERE id = ?');
		$stmt->execute(array($comp_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Create Criteria
	if (isset($_POST['create_criteria'])) {
		$criteria_comp_id = implode($_POST['create_criteria']);
		$criteria_name = $_POST['new_criteria_name'][$criteria_comp_id];
		$criteria_operator = $_POST['new_criteria_operator'][$criteria_comp_id];
		$criteria_value = $_POST['new_criteria_value'][$criteria_comp_id];
		$criteria_type = $_POST['new_criteria_type'][$criteria_comp_id];
		$criteria_order = $_POST['new_criteria_order'][$criteria_comp_id];
		$criteria_and = "1";
		$stmt = $pdo->prepare('UPDATE criteria SET sort_order = sort_order + 1 WHERE component_id = ? AND sort_order >= ?');
		$stmt->execute(array($criteria_comp_id, $criteria_order));
		$stmt = $pdo->prepare('INSERT INTO criteria (component_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute(array($criteria_comp_id, $criteria_name, $criteria_operator, $criteria_value, $criteria_type, $criteria_and, $criteria_order));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Criteria
	if (isset($_POST['delete_criteria'])) {
		$criteria_id = $_POST['delete_criteria'];
		$criteria_comp_id = $_POST['delete_criteria_comp_id'];
		$criteria_order = $_POST['delete_criteria_order'];
		$stmt = $pdo->prepare('UPDATE criteria SET sort_order = sort_order - 1 WHERE component_id = ? AND sort_order > ?');
		$stmt->execute(array($criteria_comp_id, $criteria_order));
		$stmt = $pdo->prepare('DELETE FROM criteria WHERE id = ?');
		$stmt->execute(array($criteria_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Create Dependency
	if (isset($_POST['create_dep'])) {
		$dep_name = $_POST['dep_name'][0];
		$dep_operator = $_POST['dep_operator'][0];
		$dep_value = "";
		$dep_type = $_POST['dep_type'][0];
		$dep_order = $_POST['dep_order'][0];
		$dep_and = "1";
		$stmt = $pdo->prepare('UPDATE dependencies SET sort_order = sort_order + 1 WHERE patch_id = ? AND sort_order >= ?');
		$stmt->execute(array($patch_id, $dep_order));
		$stmt = $pdo->prepare('INSERT INTO dependencies (patch_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute(array($patch_id, $dep_name, $dep_operator, $dep_value, $dep_type, $dep_and, $dep_order));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Dependency
	if (isset($_POST['delete_dep'])) {
		$dep_id = $_POST['delete_dep'];
		$dep_order = $_POST['delete_dep_order'];
		$stmt = $pdo->prepare('UPDATE dependencies SET sort_order = sort_order - 1 WHERE patch_id = ? AND sort_order > ?');
		$stmt->execute(array($patch_id, $dep_order));
		$stmt = $pdo->prepare('DELETE FROM dependencies WHERE id = ?');
		$stmt->execute(array($dep_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Create Capability
	if (isset($_POST['create_cap'])) {
		$cap_name = $_POST['cap_name'][0];
		$cap_operator = $_POST['cap_operator'][0];
		$cap_value = $_POST['cap_value'][0];
		$cap_type = $_POST['cap_type'][0];
		$cap_order = $_POST['cap_order'][0];
		$cap_and = "1";
		$stmt = $pdo->prepare('UPDATE capabilities SET sort_order = sort_order + 1 WHERE patch_id = ? AND sort_order >= ?');
		$stmt->execute(array($patch_id, $cap_order));
		$stmt = $pdo->prepare('INSERT INTO capabilities (patch_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute(array($patch_id, $cap_name, $cap_operator, $cap_value, $cap_type, $cap_and, $cap_order));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Capability
	if (isset($_POST['delete_cap'])) {
		$cap_id = $_POST['delete_cap'];
		$cap_order = $_POST['delete_cap_order'];
		$stmt = $pdo->prepare('UPDATE capabilities SET sort_order = sort_order - 1 WHERE patch_id = ? AND sort_order > ?');
		$stmt->execute(array($patch_id, $cap_order));
		$stmt = $pdo->prepare('DELETE FROM capabilities WHERE id = ?');
		$stmt->execute(array($cap_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Create Kill Application
	if (isset($_POST['create_kill_app'])) {
		$kill_app_name = $_POST['kill_app_name'][0];
		$kill_bundle_id = $_POST['kill_bundle_id'][0];
		$stmt = $pdo->prepare('INSERT INTO kill_apps (patch_id, bundle_id, app_name) VALUES (?, ?, ?)');
		$stmt->execute(array($patch_id, $kill_bundle_id, $kill_app_name));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Delete Kill Application
	if (isset($_POST['delete_kill_app'])) {
		$kill_app_id = $_POST['delete_kill_app'];
		$stmt = $pdo->prepare('DELETE FROM kill_apps WHERE id = ?');
		$stmt->execute(array($kill_app_id));
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

	// Update Title Modified
	if (isset($_POST['create_kill_app'])
	 || isset($_POST['delete_kill_app'])
	 || isset($_POST['create_comp'])
	 || isset($_POST['delete_comp'])
	 || isset($_POST['create_criteria'])
	 || isset($_POST['delete_criteria'])
	 || isset($_POST['create_cap'])
	 || isset($_POST['delete_cap'])
	 || isset($_POST['create_dep'])
	 || isset($_POST['delete_dep'])) {
		$stmt = $pdo->prepare('SELECT title_id FROM patches WHERE id = ?');
		$stmt->execute(array($patch_id));
		$title_id = $stmt->fetchColumn();
		$title_modified = time();
		$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
		$stmt->execute(array($title_modified, $title_id));
	}

	// ####################################################################
	// End of GET/POST parsing
	// ####################################################################

	// Patch
	$patch = $pdo->query('SELECT name, app_name, bundle_id, title_id, version, released, standalone, min_os, reboot, sort_order, patches.enabled FROM patches JOIN titles ON titles.id = patches.title_id WHERE patches.id = "'.$patch_id.'"')->fetch(PDO::FETCH_ASSOC);
	$patch['standalone'] = ($patch['standalone'] == "0") ? "0": "1";
	$patch['reboot'] = ($patch['reboot'] == "1") ? "1": "0";
	$patch['enabled'] = ($patch['enabled'] == "1") ? "1" : "0";
	$patch['error'] = array();

	// Patch Versions
	$patches = $pdo->query('SELECT version FROM patches WHERE title_id = "'.$patch['title_id'].'" AND id <> "'.$patch_id.'" ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
	
	// Previous Patch
	$prev_id = $pdo->query('SELECT id FROM patches WHERE title_id = ' . $patch['title_id'] . ' AND sort_order = ' . (+$patch['sort_order'] - 1))->fetch(PDO::FETCH_COLUMN);
	
	// Next Patch
	$next_id = $pdo->query('SELECT id FROM patches WHERE title_id = ' . $patch['title_id'] . ' AND sort_order = ' . (+$patch['sort_order'] + 1))->fetch(PDO::FETCH_COLUMN);

	// Kill Applications
	$kill_apps = $pdo->query('SELECT id, bundle_id, app_name FROM kill_apps WHERE patch_id = "'.$patch_id.'"')->fetchAll(PDO::FETCH_ASSOC);
	$new_kill_apps = $pdo->query('SELECT DISTINCT bundle_id, app_name FROM patches JOIN kill_apps ON patches.id = kill_apps.patch_id WHERE patches.title_id = '.$patch['title_id'])->fetchAll(PDO::FETCH_ASSOC);
	if (empty($new_kill_apps)) {
		$new_kill_apps = array(array("bundle_id" => $patch['bundle_id'], "app_name" => $patch['app_name']));
	}
	foreach ($new_kill_apps as $key => $value) {
		if (in_array($value, array_map(function($el){ return array("bundle_id" => $el['bundle_id'], "app_name" => $el['app_name']); }, $kill_apps))) {
			unset($new_kill_apps[$key]);
		}
	}
	$new_kill_apps = array_values($new_kill_apps);

	// Components
	$components = array();
	$stmt = $pdo->query('SELECT id, name, version FROM components WHERE patch_id = "'.$patch_id.'"');
	while ($component = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$component['criteria'] = array();
		$criteria_stmt = $pdo->query('SELECT id, name, operator, value, type, is_and, sort_order FROM criteria WHERE component_id = "'.$component['id'].'" ORDER BY sort_order');
		while ($criteria = $criteria_stmt->fetch(PDO::FETCH_ASSOC)) {
			$criteria['is_and'] = ($criteria['is_and'] == "0") ? "0": "1";
			array_push($component['criteria'], $criteria);
		}
		if (sizeof($component['criteria']) == 0) {
			array_push($patch['error'], "criteria");
		}
		array_push($components, $component);
	}
	if (sizeof($components) == 0) {
		array_push($patch['error'], "components");
	}
	$new_components = $pdo->query('SELECT DISTINCT name FROM patches JOIN components ON patches.id = components.patch_id WHERE patches.title_id = '.$patch['title_id'])->fetchAll(PDO::FETCH_COLUMN);
	if (empty($new_components)) {
		$new_components = array($patch['name']);
	}
	foreach ($new_components as $key => $value) {
		if (in_array($value, array_map(function($el){ return $el['name']; }, $components))) {
			unset($new_components[$key]);
		}
	}
	$new_components = array_values($new_components);

	// Capabilities
	$capabilities = array();
	$stmt = $pdo->query('SELECT id, name, operator, value, type, is_and, sort_order FROM capabilities WHERE patch_id = "'.$patch_id.'"');
	while ($capability = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$capability['is_and'] = ($capability['is_and'] == "0") ? "0": "1";
		array_push($capabilities, $capability);
	}
	if (sizeof($capabilities) == 0) {
		array_push($patch['error'], "capabilities");
	}

	// Dependencies
	$dependencies = array();
	$stmt = $pdo->query('SELECT id, name, operator, value, type, is_and, sort_order FROM dependencies WHERE patch_id = "'.$patch_id.'"');
	while ($dependency = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$dependency['is_and'] = ($dependency['is_and'] == "0") ? "0": "1";
		array_push($dependencies, $dependency);
	}

	// Extension Attributes
	$ext_attrs = $pdo->query('SELECT key_id, name FROM ext_attrs WHERE title_id = "'.$patch['title_id'].'"')->fetchAll(PDO::FETCH_ASSOC);

	// Disable Incomplete Patch
	if (sizeof($patch['error']) > 0 && $patch['enabled'] == "1") {
		$patch['enabled'] = "0";
		$disable = $pdo->prepare('UPDATE patches SET enabled = 0 WHERE id = ?');
		$disable->execute(array($patch_id));
		if ($disable->errorCode() != '00000') {
			$errorInfo = $disable->errorInfo();
			$error_msg = $errorInfo[2];
		}
	}

} else {

	$error_msg = "Invalid Patch ID '".(isset($_GET['id']) ? $_GET['id'] : null)."'";

}
?>
			<link rel="stylesheet" href="theme/bootstrap-datetimepicker.css" />
			<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />
			<link rel="stylesheet" href="theme/bootstrap-select.css" />

			<style>
				.btn-table {
					width: 75px;
				}
				#tab-content {
					margin-top: 119px;
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
				.nav-tabs.nav-justified > li {
					white-space: nowrap;
					display: table-cell;
					width: 1%;
				}
				.nav-tabs.nav-justified > li > a {
					margin-bottom: 0;
					border-bottom: 1px solid #ddd;
					border-radius: 4px 4px 0 0;
				}
				.nav-tabs.nav-justified > .active > a,
				.nav-tabs.nav-justified > .active > a:hover,
				.nav-tabs.nav-justified > .active > a:focus {
					border-bottom-color: #fff;
				}
<?php if ($netsus) { ?>
				@media(min-width:768px) {
					#nav-title {
						left: 220px;
					}
				}
<?php } ?>
			</style>

			<script type="text/javascript" src="scripts/moment/moment.min.js"></script>
			<script type="text/javascript" src="scripts/bootstrap/transition.js"></script>
			<script type="text/javascript" src="scripts/bootstrap/collapse.js"></script>
			<script type="text/javascript" src="scripts/datetimepicker/bootstrap-datetimepicker.min.js"></script>

			<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
			<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

			<script type="text/javascript" src="scripts/bootstrap-select/bootstrap-select.min.js"></script>
<?php if (!empty($patch_id)) { ?>
			<script type="text/javascript">
				var patchVersions = [<?php echo (sizeof($patches) > 0 ? "\"".implode('", "', $patches)."\"" : ""); ?>];
				var patchEnabled = <?php echo $patch['enabled']; ?>;
				var extAttrKeys = [<?php echo (sizeof($ext_attrs) > 0 ? "\"".implode('", "', array_map(function($el){ return $el['key_id']; }, $ext_attrs))."\"" : ""); ?>];
				var sizeOfEas = <?php echo sizeof($ext_attrs); ?>;
				var sizeOfCriteria = [];
<?php foreach ($components as $component) { ?>
					sizeOfCriteria[<?php echo $component['id']; ?>] = <?php echo sizeof($component['criteria']); ?>;
<?php } ?>
				var componentsError = <?php echo (in_array("components", $patch['error']) ? "1" : "0"); ?>;
				var criteriaError = <?php echo (in_array("criteria", $patch['error']) ? "1" : "0"); ?>;
				var sizeOfCaps = <?php echo sizeof($capabilities); ?>;
				var capabilitiesError = <?php echo (in_array("capabilities", $patch['error']) ? "1" : "0"); ?>;
			</script>

			<script type="text/javascript" src="scripts/patchValidation.js"></script>

			<script type="text/javascript">
				function showPatchDisabled() {
					$('#patch-tab-link').css('color', '#8a6d3b');
					$('#patch-tab-icon').removeClass('hidden');
					$('#patch-disabled-msg').removeClass('hidden');
				}
				function hidePatchDisabled() {
					$('#patch-tab-link').removeAttr('style');
					$('#patch-tab-icon').addClass('hidden');
					$('#patch-disabled-msg').addClass('hidden');
				}
				function togglePatchEnable() {
					if (componentsError == 1 || criteriaError == 1 || capabilitiesError == 1) {
						$('#enable_patch').addClass('hidden');
					} else {
						$('#enable_patch').removeClass('hidden');
					}
				}
				function showComponentsError() {
					$('#components-tab-link').css('color', '#a94442');
					$('#components-tab-icon').removeClass('hidden');
					if (componentsError == 1) { $('#components-alert-msg').removeClass('hidden'); };
				}
				function showCapabilitiesError() {
					$('#capabilities-tab-link').css('color', '#a94442');
					$('#capabilities-tab-icon').removeClass('hidden');
					$('#capabilities-alert-msg').removeClass('hidden');
				}
				function enablePatch() {
					if (patchEnabled == 0 && componentsError == 0 && criteriaError == 0 && capabilitiesError == 0) {
						ajaxPost('patchCtl.php?patch_id=<?php echo $patch_id; ?>', 'patch_enabled=true');
						hidePatchDisabled();
						patchEnabled = 1;
					}
				}
				function newCompModal() {
					var version = document.getElementById('version');
					var comp_name = document.getElementById('comp_name[0]');
					var comp_version = document.getElementById('comp_version[0]');
					comp_name.value = '<?php echo htmlentities(empty($new_components) ? "" : $new_components[0]); ?>';
					comp_version.value = version.value;
					validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');
				}
				function newCriteriaModal(compId) {
					var version = document.getElementById('version');
					var criteria_name = document.getElementById('new_criteria_name['+compId+']');
					var criteria_value = document.getElementById('new_criteria_value['+compId+']');
					if (sizeOfCriteria[compId] == 0 && sizeOfEas == 0) { criteria_name.value = 'Application Bundle ID'; }
					if (sizeOfCriteria[compId] == 1 && sizeOfEas == 0) { criteria_name.value = "Application Version"; }
					if (sizeOfCriteria[compId] == 0 && sizeOfEas == 1) { criteria_name.value = extAttrKeys[0]; }
					selectCriteria(criteria_name, 'new_criteria_type['+compId+']', 'new_criteria_operator['+compId+']');
					switch (criteria_name.value) {
						case extAttrKeys[0]:
						case "Application Version":
							criteria_value.value = version.value;
							break;
						case "Application Bundle ID":
							criteria_value.value = "<?php echo htmlentities($patch['bundle_id']); ?>";
							break;
						case "Application Title":
							criteria_value.value = "<?php echo htmlentities($patch['app_name']); ?>";
							break;
						default:
							criteria_value.value = "";
					}
					validCriteria('create_criteria['+compId+']', 'new_criteria_order['+compId+']', 'new_criteria_name['+compId+']', 'new_criteria_operator['+compId+']', 'new_criteria_type['+compId+']');
				}
				function newCapModal() {
					var min_os = document.getElementById("min_os");
					var cap_name = document.getElementById('cap_name[0]');
					var cap_operator = document.getElementById('cap_operator[0]');
					var cap_value = document.getElementById('cap_value[0]');
					selectCriteria(cap_name, 'cap_type[0]', 'cap_operator[0]');
					if (sizeOfCaps == 0) {
						cap_name.value = 'Operating System Version';
						cap_operator.value = 'greater than or equal';
					}
					if (cap_name.value == 'Operating System Version') {
						cap_value.value = min_os.value;
					} else {
						cap_value.value = '';
					}
					validCriteria('create_cap', 'cap_order[0]', 'cap_name[0]', 'cap_operator[0]', 'cap_type[0]');
				}
				function newKillAppModal() {
					var kill_app_name = document.getElementById('kill_app_name[0]');
					var kill_bundle_id = document.getElementById('kill_bundle_id[0]');
					kill_app_name.value = "<?php echo htmlentities(empty($new_kill_apps) ? "" : $new_kill_apps[0]['app_name']); ?>";
					kill_bundle_id.value = "<?php echo htmlentities(empty($new_kill_apps) ? "" : $new_kill_apps[0]['bundle_id']); ?>";
					validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');
				}
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					if (patchEnabled == 0) { showPatchDisabled(); };
					if (componentsError == 1 || criteriaError == 1) { showComponentsError(); };
					if (capabilitiesError == 1) { showCapabilitiesError(); };
					togglePatchEnable();
				});
			</script>

			<script type="text/javascript">
				$(function () {
					$('#released').datetimepicker({
						format: 'YYYY-MM-DDTHH:mm:ss\\Z'
					});
				});
			</script>
<?php } ?>
			<script type="text/javascript">
				$(document).ready(function() {
					$('#components').DataTable( {
						buttons: [
							{
								text: '<span class="glyphicon glyphicon-plus"></span> New',
								className: 'btn-primary btn-sm btn-table',
								action: function ( e, dt, node, config ) {
									newCompModal();
									$("#create_comp-modal").modal();
								}
							}
						],
						"dom": '<"row"<"col-xs-12"B>>' + '<"row"<"col-xs-12 table-responsive"t>>',
						"info": false,
						"lengthChange": false,
						"ordering": false,
						"paging": false,
						"searching": false,
						"language": {
							"emptyTable": "No Components",
							"loadingRecords": "Please wait - loading...",
						},
						"stateSave": true
					});

					$('#criteria').DataTable( {
						"dom": '<"row"<"col-xs-12 table-responsive"t>>',
						"info": false,
						"lengthChange": false,
						"ordering": false,
						"paging": false,
						"searching": false
					});

					$('#dependencies').DataTable( {
						"dom": '<"row"<"col-xs-12 table-responsive"t>>',
						"info": false,
						"lengthChange": false,
						"ordering": false,
						"paging": false,
						"searching": false
					});

					$('#capabilities').DataTable( {
						"dom": '<"row"<"col-xs-12 table-responsive"t>>',
						"info": false,
						"lengthChange": false,
						"ordering": false,
						"paging": false,
						"searching": false
					});

					$('#kill_apps').DataTable( {
						buttons: [
							{
								text: '<span class="glyphicon glyphicon-plus"></span> New',
								className: 'btn-primary btn-sm btn-table',
								action: function ( e, dt, node, config ) {
									newKillAppModal();
									$("#create_kill_app-modal").modal();
								}
							}
						],
						"dom": '<"row"<"col-xs-12"B>>' + '<"row"<"col-xs-12 table-responsive"t>>',
						"info": false,
						"lengthChange": false,
						"ordering": false,
						"paging": false,
						"searching": false,
						"language": {
							"emptyTable": "No Kill Applications",
							"loadingRecords": "Please wait - loading...",
						},
						"stateSave": true
					});

					$('.dataTables_wrapper').css('padding', '0px 20px');
					$('.dataTables_wrapper').removeClass('form-inline');

					$('.dt-buttons').addClass('pull-right');
					$('.dt-buttons').removeClass('dt-buttons');
					$('.btn-primary').removeClass('btn-default');

					$('.table-responsive').css('border', 0);
					
					$('#criteria_wrapper').css({"background-color": "#f9f9f9", "border-bottom": "1px solid #ddd"});

<?php if ($sw_title['source_id'] > 0) { ?>
					$('#components').DataTable().buttons().disable();
					$('#kill_apps').DataTable().buttons().disable();
<?php } ?>
<?php if (sizeof($components) > 0) { ?>
					$('#components').DataTable().buttons().disable();
<?php } ?>
				});
			</script>

			<script type="text/javascript">
				$(document).ready(function(){
					$('a[data-toggle="tab"]').on('show.bs.tab', function(e) {
						localStorage.setItem('activePatchTab', $(e.target).attr('href'));
					});
					var activePatchTab = localStorage.getItem('activePatchTab');
					if(activePatchTab){
						$('#top-tabs a[href="' + activePatchTab + '"]').tab('show');
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
					<div class="description"><a href="patchTitles.php">Patch Definitions</a> <span class="glyphicon glyphicon-chevron-right"></span><?php if (!empty($patch_id)) { ?> <a href="manageTitle.php?id=<?php echo $patch['title_id']; ?>"><?php echo $patch['name']; ?></a> <span class="glyphicon glyphicon-chevron-right"></span><?php } ?></div>
					<div class="row">
						<div class="col-xs-9">
							<h2 id="heading"><?php echo (empty($patch_id) ? "Error" : $patch['version']); ?></h2>
						</div>
						<div class="col-xs-3 text-right">
							<div class="btn-group btn-group-sm" role="group">
								<a href="<?php echo ($prev_id ? "managePatch.php?id=".$prev_id : "#"); ?>" class="btn btn-default <?php echo ($prev_id ? "" : "disabled"); ?>"><span class="glyphicon glyphicon-chevron-left"></span></a>
								<a href="<?php echo ($next_id ? "managePatch.php?id=".$next_id : "#"); ?>" class="btn btn-default <?php echo ($next_id ? "" : "disabled"); ?>"><span class="glyphicon glyphicon-chevron-right"></span></a>
							</div>
						</div>
					</div>
				</div>
<?php if (!empty($patch_id)) { ?>
				<div style="padding: 16px 20px 0px; background-color: #f9f9f9; border-bottom: 1px solid #ddd;">
					<ul class="nav nav-tabs nav-justified" id="top-tabs" style="margin-bottom: -1px;">
						<li class="active"><a id="patch-tab-link" class="tab-font" href="#patch-tab" role="tab" data-toggle="tab"><span id="patch-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Patch</a></li>
						<li><a id="components-tab-link" class="tab-font" href="#components-tab" role="tab" data-toggle="tab"><span id="components-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Components</a></li>
						<!-- <li><a id="dependencies-tab-link" class="tab-font" href="#dependencies-tab" role="tab" data-toggle="tab">Dependencies</a></li> -->
						<li><a id="capabilities-tab-link" class="tab-font" href="#capabilities-tab" role="tab" data-toggle="tab"><span id="capabilities-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden-xs hidden"></span> Capabilities</a></li>
						<li><a id="killapps-tab-link" class="tab-font" href="#killapps-tab" role="tab" data-toggle="tab">Kill Applications</a></li>
					</ul>
				</div>
<?php } ?>
			</nav>

<?php if (!empty($patch_id)) { ?>
			<form action="managePatch.php?id=<?php echo $patch_id; ?>" method="post" name="editPatch" id="editPatch">

				<div id="tab-content" class="tab-content">

					<div class="tab-pane active fade in" id="patch-tab">

						<div style="padding: 16px 20px 8px;">
							<div id="patch-disabled-msg" style="margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning hidden">
								<div class="panel-body">
									<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>This patch is disabled.<span id="enable_patch"> <a href="" onClick="enablePatch();">Click here to enable it</a>.<span></div>
								</div>
							</div>

							<div class="text-muted" style="font-size: 12px;">Software title version information; one patch is one software title version.</div>
						</div>

						<div style="padding: 0px 20px;">
							<h5 id="sort_order_label"><strong>Sort Order</strong></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" class="form-control input-sm" onFocus="validInteger(this, 'sort_order_label');" onKeyUp="validInteger(this, 'sort_order_label');" onChange="updateInteger(this, 'patches', 'sort_order', <?php echo $patch_id; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $patch['sort_order']; ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5 id="version_label"><strong>Version</strong> <small>Version associated with this patch.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" id="version" class="form-control input-sm" onFocus="validVersion(this, 'version_label');" onKeyUp="validVersion(this, 'version_label');" onChange="updateVersion(this, 'patches', 'version', <?php echo $patch_id; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>); document.getElementById('heading').innerHTML = this.value;" placeholder="[Required]" value="<?php echo htmlentities($patch['version']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5 id="released_label"><strong>Release Date</strong> <small>Date that this patch version was released.</small></h5>
							<div class="form-group">
								<div class="input-group has-feedback date" id="released" style="max-width: 449px;">
									<span class="input-group-addon input-sm" style="color: #555; background-color: #eee; border: 1px solid #ccc; border-right: 0;">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
									<input type="text" class="form-control input-sm" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" onFocus="validDate(this, 'released_label');" onKeyUp="validDate(this, 'released_label');" onBlur="validDate(this, 'released_label'); updateDate(this, 'patches', 'released', <?php echo $patch_id; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo gmdate("Y-m-d\TH:i:s\Z", $patch['released']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
								</div>
							</div>
							<h5><strong>Standalone</strong> <small><span style="font-family:monospace;">Yes</span> specifies a patch that can be installed by itself. <span style="font-family:monospace;">No</span> specifies a patch that must be installed incrementally.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<select class="selectpicker" data-style="btn-default btn-sm" data-width="449px" data-container="body" onFocus="hideSuccess(this);" onChange="updateInteger(this, 'patches', 'standalone', <?php echo $patch_id; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
									<option value="1" <?php echo ($patch['standalone'] == "1" ? " selected" : "") ?> >Yes</option>
									<option value="0" <?php echo ($patch['standalone'] == "0" ? " selected" : "") ?> >No</option>
								</select>
							</div>
							<h5 id="min_os_label"><strong>Minimum Operating System</strong> <small>Lowest macOS version capable of installing this patch.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
								<input type="text" id="min_os" class="form-control input-sm" onFocus="validString(this, 'min_os_label');" onKeyUp="validString(this, 'min_os_label');" onChange="updateString(this, 'patches', 'min_os', <?php echo $patch_id; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo htmlentities($patch['min_os']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
							</div>
							<h5><strong>Reboot</strong> <small><span style="font-family:monospace;">Yes</span> specifies that the computer must be restarted after the patch policy has completed successfully. <span style="font-family:monospace;">No</span> specifies that the computer will not be restarted.</small></h5>
							<div class="form-group has-feedback" style="max-width: 449px;">
									<select class="selectpicker" data-style="btn-default btn-sm" data-width="449px" data-container="body" onFocus="hideSuccess(this);" onChange="updateInteger(this, 'patches', 'reboot', <?php echo $patch_id; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
										<option value="0" <?php echo ($patch['reboot'] == "0" ? " selected" : "") ?> >No</option>
										<option value="1" <?php echo ($patch['reboot'] == "1" ? " selected" : "") ?> >Yes</option>
									</select>
							</div>
						</div>

					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="components-tab">

						<div style="padding: 16px 20px 8px;">
							<div id="components-alert-msg" class="panel panel-danger hidden" style="margin-bottom: 16px; border-color: #d43f3a;">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>At least one component is required for the patch to be valid.</div>
								</div>
							</div>

							<div class="text-muted" style="font-size: 12px;">Defines the elements that comprise this patch version.<br><strong>Note:</strong> Only one element is supported by Jamf Pro at this time.</div>
						</div>

						<table id="components" class="table table-hover">
							<thead>
								<tr>
									<th>Name</th>
									<th>Version</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
<?php foreach ($components as $component) { ?>
								<tr>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'components', 'name', <?php echo $component['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo htmlentities($component['name']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'components', 'version', <?php echo $component['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo htmlentities($component['version']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_comp-modal" onClick="$('#delete_comp').val('<?php echo $component['id']; ?>');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>Delete</button></td>
								</tr>
							</tbody>
						</table>

						<div style="padding: 16px 20px 4px; background-color: #f9f9f9; border-top: 1px solid #ddd;">
<?php if (sizeof($component['criteria']) == 0) { ?>
							<div class="panel panel-danger" style="margin-bottom: 16px; border-color: #d43f3a;">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>At least one criteria is required for the component to be valid.</div>
								</div>
							</div>
<?php } ?>
							<h5><strong>Criteria</strong> <small>Criteria used to determine which computers in your environment have this patch version installed.<br>The following values are the same as a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria must be ordered in the same way that smart group criteria is ordered.</small></h5>
						</div>

						<table id="criteria" class="table table-hover">
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
<?php foreach ($component['criteria'] as $criteria) { ?>
								<tr>
									<td>
<?php if ($criteria['sort_order'] == 0) { ?>
										<input type="hidden" value="<?php echo $criteria['is_and']; ?>" />
<?php } else { ?>
										<div class="has-feedback">
											<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateInteger(this, 'criteria', 'is_and', <?php echo $criteria['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
												<option value="1"<?php echo ($criteria['is_and'] == "1" ? " selected" : "") ?> >and</option>
												<option value="0"<?php echo ($criteria['is_and'] == "0" ? " selected" : "") ?> >or</option>
											</select>
										</div>
<?php } ?>
									</td>
									<td>
										<div class="has-feedback">
											<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateCriteria(this, 'criteria_operator[<?php echo $criteria['id']; ?>]', 'criteria_type[<?php echo $criteria['id']; ?>]', 'criteria', <?php echo $criteria['id']; ?>, true); $('.selectpicker').selectpicker('refresh'); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
<?php foreach ($ext_attrs as $ext_attr) { ?>
												<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($criteria['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
<?php } ?>
												<option value="Application Bundle ID"<?php echo ($criteria['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
												<option value="Application Title"<?php echo ($criteria['name'] == "Application Title" ? " selected" : "") ?> >Application Title</option>
												<option value="Application Version"<?php echo ($criteria['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
												<option value="Architecture Type"<?php echo ($criteria['name'] == "Architecture Type" ? " selected" : "") ?> >Architecture Type</option>
												<option value="Boot Drive Available MB"<?php echo ($criteria['name'] == "Boot Drive Available MB" ? " selected" : "") ?> >Boot Drive Available MB</option>
												<option value="Drive Capacity MB"<?php echo ($criteria['name'] == "Drive Capacity MB" ? " selected" : "") ?> >Drive Capacity MB</option>
												<option value="Make"<?php echo ($criteria['name'] == "Make" ? " selected" : "") ?> >Make</option>
												<option value="Model"<?php echo ($criteria['name'] == "Model" ? " selected" : "") ?> >Model</option>
												<option value="Model Identifier"<?php echo ($criteria['name'] == "Model Identifier" ? " selected" : "") ?> >Model Identifier</option>
												<option value="Number of Processors"<?php echo ($criteria['name'] == "Number of Processors" ? " selected" : "") ?> >Number of Processors</option>
												<option value="Operating System"<?php echo ($criteria['name'] == "Operating System" ? " selected" : "") ?> >Operating System</option>
												<option value="Operating System Build"<?php echo ($criteria['name'] == "Operating System Build" ? " selected" : "") ?> >Operating System Build</option>
												<option value="Operating System Name"<?php echo ($criteria['name'] == "Operating System Name" ? " selected" : "") ?> >Operating System Name</option>
												<option value="Operating System Version"<?php echo ($criteria['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
												<option value="Optical Drive"<?php echo ($criteria['name'] == "Optical Drive" ? " selected" : "") ?> >Optical Drive</option>
												<option value="Platform"<?php echo ($criteria['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
												<option value="Processor Speed MHz"<?php echo ($criteria['name'] == "Processor Speed MHz" ? " selected" : "") ?> >Processor Speed MHz</option>
												<option value="Processor Type"<?php echo ($criteria['name'] == "Processor Type" ? " selected" : "") ?> >Processor Type</option>
												<option value="SMC Version"<?php echo ($criteria['name'] == "SMC Version" ? " selected" : "") ?> >SMC Version</option>
												<option value="Total Number of Cores"<?php echo ($criteria['name'] == "Total Number of Cores" ? " selected" : "") ?> >Total Number of Cores</option>
												<option value="Total RAM MB"<?php echo ($criteria['name'] == "Total RAM MB" ? " selected" : "") ?> >Total RAM MB</option>
											</select>
										</div>
										<input type="hidden" id="criteria_type[<?php echo $criteria['id']; ?>]" value="<?php echo $criteria['type']; ?>"/>
									</td>
									<td>
										<div class="has-feedback">
											<select id="criteria_operator[<?php echo $criteria['id']; ?>]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onFocus="hideWarning(this);" onChange="updateString(this, 'criteria', 'operator', <?php echo $criteria['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
												<option value="is"<?php echo ($criteria['operator'] == "is" ? " selected" : "") ?> >is</option>
												<option value="is not"<?php echo ($criteria['operator'] == "is not" ? " selected" : "") ?> >is not</option>
<?php switch($criteria['name']) {
	case "Application Title": ?>
												<option value="has"<?php echo ($criteria['operator'] == "has" ? " selected" : "") ?> >has</option>
												<option value="does not have"<?php echo ($criteria['operator'] == "does not have" ? " selected" : "") ?> >does not have</option>
<?php break;
	case "Boot Drive Available MB":
	case "Drive Capacity MB":
	case "Number of Processors":
	case "Processor Speed MHz":
	case "Total Number of Cores":
	case "Total RAM MB": ?>
												<option value="more than"<?php echo ($criteria['operator'] == "more than" ? " selected" : "") ?> >more than</option>
												<option value="less than"<?php echo ($criteria['operator'] == "less than" ? " selected" : "") ?> >less than</option>
<?php break;
	case "Operating System Version": ?>
												<option value="like"<?php echo ($criteria['operator'] == "like" ? " selected" : "") ?> >like</option>
												<option value="not like"<?php echo ($criteria['operator'] == "not like" ? " selected" : "") ?> >not like</option>
												<option value="greater than"<?php echo ($criteria['operator'] == "greater than" ? " selected" : "") ?> >greater than</option>
												<option value="less than"<?php echo ($criteria['operator'] == "less than" ? " selected" : "") ?> >less than</option>
												<option value="greater than or equal"<?php echo ($criteria['operator'] == "greater than or equal" ? " selected" : "") ?> >greater than or equal</option>
												<option value="less than or equal"<?php echo ($criteria['operator'] == "less than or equal" ? " selected" : "") ?> >less than or equal</option>
<?php default: ?>
												<option value="like"<?php echo ($criteria['operator'] == "like" ? " selected" : "") ?> >like</option>
												<option value="not like"<?php echo ($criteria['operator'] == "not like" ? " selected" : "") ?> >not like</option>
												<option value="matches regex"<?php echo ($criteria['operator'] == "matches regex" ? " selected" : "") ?> >matches regex</option>
												<option value="does not match regex"<?php echo ($criteria['operator'] == "does not match regex" ? " selected" : "") ?> >does not match regex</option>
<?php } ?>
											</select>
										</div>
									</td>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" style="min-width: 84px;" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'criteria', 'value', <?php echo $criteria['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="" value="<?php echo htmlentities($criteria['value']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td align="right">
										<input type="hidden" name="criteria_comp_id[<?php echo $criteria['id']; ?>]" value="<?php echo $component['id']; ?>"/>
										<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_criteria-modal" onClick="$('#delete_criteria_comp_id').val('<?php echo $component['id']; ?>'); $('#delete_criteria_order').val('<?php echo $criteria['sort_order']; ?>'); $('#delete_criteria').val('<?php echo $criteria['id']; ?>');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>Delete</button>
									</td>
								</tr>
<?php } ?>
								<tr>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#create_criteria-<?php echo $component['id']; ?>" onClick="newCriteriaModal('<?php echo $component['id']; ?>'); $('.selectpicker').selectpicker('refresh');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tbody>
<?php } ?>
						</table>

						<!-- New Component Modal -->
						<div class="modal fade" id="create_comp-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Component</h3>
									</div>
									<div class="modal-body">

										<h5 id="comp_name_label[0]"><strong>Name</strong> <small>Name of the patch management software title.</small></h5>
										<div class="form-group">
											<input type="text" name="comp_name[0]" id="comp_name[0]" class="form-control input-sm" onKeyUp="validString(this, 'comp_name_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" onBlur="validString(this, 'comp_name_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" placeholder="[Required]" value="" />
										</div>

										<h5 id="comp_version_label[0]"><strong>Version</strong> <small>Version associated with this patch.</small></h5>
										<div class="form-group">
											<input type="text" name="comp_version[0]" id="comp_version[0]" class="form-control input-sm" onKeyUp="validString(this, 'comp_version_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" onBlur="validString(this, 'comp_version_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" placeholder="[Required]" value="" />
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_comp" id="create_comp" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete Component Modal -->
						<div class="modal fade" id="delete_comp-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete Component?</h3>
									</div>
									<div class="modal-body">
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_comp" id="delete_comp" class="btn btn-danger btn-sm" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

<?php foreach ($components as $component) { ?>
						<!-- New Criteria Modal -->
						<div class="modal fade" id="create_criteria-<?php echo $component['id']; ?>" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Criteria</h3>
									</div>
									<div class="modal-body">

										<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.</small></h5>
										<div class="form-group">
											<input type="hidden" name="new_criteria_order[<?php echo $component['id']; ?>]" id="new_criteria_order[<?php echo $component['id']; ?>]" value="<?php echo sizeof($component['criteria']); ?>" />
											<select id="new_criteria_name[<?php echo $component['id']; ?>]" name="new_criteria_name[<?php echo $component['id']; ?>]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" title="Select..." onChange="selectCriteria(this, 'new_criteria_type[<?php echo $component['id']; ?>]', 'new_criteria_operator[<?php echo $component['id']; ?>]'); validCriteria('create_criteria[<?php echo $component['id']; ?>]', 'new_criteria_order[<?php echo $component['id']; ?>]', 'new_criteria_name[<?php echo $component['id']; ?>]', 'new_criteria_operator[<?php echo $component['id']; ?>]', 'new_criteria_type[<?php echo $component['id']; ?>]');" >
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
											<input type="hidden" name="new_criteria_type[<?php echo $component['id']; ?>]" id="new_criteria_type[<?php echo $component['id']; ?>]" value="recon" />
											<input type="hidden" name="new_criteria_operator[<?php echo $component['id']; ?>]" id="new_criteria_operator[<?php echo $component['id']; ?>]" value="is" />
											<input type="hidden" name="new_criteria_value[<?php echo $component['id']; ?>]" id="new_criteria_value[<?php echo $component['id']; ?>]" value="" />
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_criteria[<?php echo $component['id']; ?>]" id="create_criteria[<?php echo $component['id']; ?>]" class="btn btn-primary btn-sm" value="<?php echo $component['id']; ?>" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->
<?php } ?>

						<!-- Delete Criteria Modal -->
						<div class="modal fade" id="delete_criteria-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete Criteria?</h3>
									</div>
									<div class="modal-body">
										<input type="hidden" id="delete_criteria_comp_id" name="delete_criteria_comp_id" value=""/>
										<input type="hidden" id="delete_criteria_order" name="delete_criteria_order" value=""/>
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_criteria" id="delete_criteria" class="btn btn-danger btn-sm" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->
					</div><!-- /.tab-pane -->

<!-- 
					<div class="tab-pane fade in" id="dependencies-tab">

						<div style="padding: 16px 20px 4px;">
							<div class="text-muted" style="font-size: 12px;">Not currently used by Jamf Pro.</div>
						</div>

						<table id="dependencies" class="table table-hover">
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
<?php foreach ($dependencies as $dependency) { ?>
								<tr>
									<td>
<?php if ($dependency['sort_order'] == 0) { ?>
										<input type="hidden" value="<?php echo $dependency['is_and']; ?>" />
<?php } else { ?>
										<div class="has-feedback">
											<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateInteger(this, 'dependencies', 'is_and', <?php echo $dependency['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
												<option value="1"<?php echo ($dependency['is_and'] == "1" ? " selected" : "") ?>>and</option>
												<option value="0"<?php echo ($dependency['is_and'] == "0" ? " selected" : "") ?>>or</option>
											</select>
										</div>
<?php } ?>
									</td>
									<td>
										<div class="has-feedback">
											<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateCriteria(this, 'dep_operator[<?php echo $dependency['id']; ?>]', 'dep_type[<?php echo $dependency['id']; ?>]', 'dependencies', <?php echo $dependency['id']; ?>, true); $('.selectpicker').selectpicker('refresh'); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
<?php foreach ($ext_attrs as $ext_attr) { ?>
												<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($dependency['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
<?php } ?>
												<option value="Application Bundle ID"<?php echo ($dependency['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
												<option value="Application Title"<?php echo ($dependency['name'] == "Application Title" ? " selected" : "") ?> >Application Title</option>
												<option value="Application Version"<?php echo ($dependency['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
												<option value="Architecture Type"<?php echo ($dependency['name'] == "Architecture Type" ? " selected" : "") ?> >Architecture Type</option>
												<option value="Boot Drive Available MB"<?php echo ($dependency['name'] == "Boot Drive Available MB" ? " selected" : "") ?> >Boot Drive Available MB</option>
												<option value="Drive Capacity MB"<?php echo ($dependency['name'] == "Drive Capacity MB" ? " selected" : "") ?> >Drive Capacity MB</option>
												<option value="Make"<?php echo ($dependency['name'] == "Make" ? " selected" : "") ?> >Make</option>
												<option value="Model"<?php echo ($dependency['name'] == "Model" ? " selected" : "") ?> >Model</option>
												<option value="Model Identifier"<?php echo ($dependency['name'] == "Model Identifier" ? " selected" : "") ?> >Model Identifier</option>
												<option value="Number of Processors"<?php echo ($dependency['name'] == "Number of Processors" ? " selected" : "") ?> >Number of Processors</option>
												<option value="Operating System"<?php echo ($dependency['name'] == "Operating System" ? " selected" : "") ?> >Operating System</option>
												<option value="Operating System Build"<?php echo ($dependency['name'] == "Operating System Build" ? " selected" : "") ?> >Operating System Build</option>
												<option value="Operating System Name"<?php echo ($dependency['name'] == "Operating System Name" ? " selected" : "") ?> >Operating System Name</option>
												<option value="Operating System Version"<?php echo ($dependency['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
												<option value="Optical Drive"<?php echo ($dependency['name'] == "Optical Drive" ? " selected" : "") ?> >Optical Drive</option>
												<option value="Platform"<?php echo ($dependency['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
												<option value="Processor Speed MHz"<?php echo ($dependency['name'] == "Processor Speed MHz" ? " selected" : "") ?> >Processor Speed MHz</option>
												<option value="Processor Type"<?php echo ($dependency['name'] == "Processor Type" ? " selected" : "") ?> >Processor Type</option>
												<option value="SMC Version"<?php echo ($dependency['name'] == "SMC Version" ? " selected" : "") ?> >SMC Version</option>
												<option value="Total Number of Cores"<?php echo ($dependency['name'] == "Total Number of Cores" ? " selected" : "") ?> >Total Number of Cores</option>
												<option value="Total RAM MB"<?php echo ($dependency['name'] == "Total RAM MB" ? " selected" : "") ?> >Total RAM MB</option>
											</select>
										</div>
										<input type="hidden" id="dep_type[<?php echo $dependency['id']; ?>]" value="<?php echo $dependency['type']; ?>"/>
									</td>
									<td>
										<div class="has-feedback">
											<select id="dep_operator[<?php echo $dependency['id']; ?>]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onFocus="hideWarning(this);" onChange="updateString(this, 'dependencies', 'operator', <?php echo $dependency['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
												<option value="is"<?php echo ($dependency['operator'] == "is" ? " selected" : "") ?> >is</option>
												<option value="is not"<?php echo ($dependency['operator'] == "is not" ? " selected" : "") ?> >is not</option>
<?php switch($dependency['name']) {
	case "Application Title": ?>
												<option value="has"<?php echo ($dependency['operator'] == "has" ? " selected" : "") ?> >has</option>
												<option value="does not have"<?php echo ($dependency['operator'] == "does not have" ? " selected" : "") ?> >does not have</option>
<?php break;
	case "Boot Drive Available MB":
	case "Drive Capacity MB":
	case "Number of Processors":
	case "Processor Speed MHz":
	case "Total Number of Cores":
	case "Total RAM MB": ?>
												<option value="more than"<?php echo ($dependency['operator'] == "more than" ? " selected" : "") ?> >more than</option>
												<option value="less than"<?php echo ($dependency['operator'] == "less than" ? " selected" : "") ?> >less than</option>
<?php break;
	case "Operating System Version": ?>
												<option value="like"<?php echo ($dependency['operator'] == "like" ? " selected" : "") ?> >like</option>
												<option value="not like"<?php echo ($dependency['operator'] == "not like" ? " selected" : "") ?> >not like</option>
												<option value="greater than"<?php echo ($dependency['operator'] == "greater than" ? " selected" : "") ?> >greater than</option>
												<option value="less than"<?php echo ($dependency['operator'] == "less than" ? " selected" : "") ?> >less than</option>
												<option value="greater than or equal"<?php echo ($dependency['operator'] == "greater than or equal" ? " selected" : "") ?> >greater than or equal</option>
												<option value="less than or equal"<?php echo ($dependency['operator'] == "less than or equal" ? " selected" : "") ?> >less than or equal</option>
<?php default: ?>
												<option value="like"<?php echo ($dependency['operator'] == "like" ? " selected" : "") ?> >like</option>
												<option value="not like"<?php echo ($dependency['operator'] == "not like" ? " selected" : "") ?> >not like</option>
												<option value="matches regex"<?php echo ($dependency['operator'] == "matches regex" ? " selected" : "") ?> >matches regex</option>
												<option value="does not match regex"<?php echo ($dependency['operator'] == "does not match regex" ? " selected" : "") ?> >does not match regex</option>
<?php } ?>
											</select>
										</div>
									</td>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" style="min-width: 84px;" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'dependencies', 'value', <?php echo $dependency['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="" value="<?php echo htmlentities($dependency['value']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_dep-modal" onClick="$('#delete_dep_order').val('<?php echo $dependency['sort_order']; ?>'); $('#delete_dep').val('<?php echo $dependency['id']; ?>');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>Delete</button></td>
								</tr>
<?php } ?>
								<tr>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#create_dep-modal" <?php echo ($source_id > 0 ? "disabled" : ""); ?>><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tbody>
						</table>

						<!~~ New Dependency Modal ~~>
						<div class="modal fade" id="create_dep-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Dependency</h3>
									</div>
									<div class="modal-body">

										<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.</small></h5>
										<div class="form-group">
											<input type="hidden" name="dep_order[0]" id="dep_order[0]" value="<?php echo sizeof($dependencies); ?>" />
											<select id="dep_name[0]" name="dep_name[0]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" title="Select..." onChange="selectCriteria(this, 'dep_type[0]', 'dep_operator[0]'); validCriteria('create_dep', 'dep_order[0]', 'dep_name[0]', 'dep_operator[0]', 'dep_type[0]');" >
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
											<input type="hidden" name="dep_type[0]" id="dep_type[0]" value="recon" />
											<input type="hidden" name="dep_operator[0]" id="dep_operator[0]" value="is" />
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_dep" id="create_dep" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!~~ /.modal ~~>

						<!~~ Delete Dependency Modal ~~>
						<div class="modal fade" id="delete_dep-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete Dependency?</h3>
									</div>
									<div class="modal-body">
										<input type="hidden" id="delete_dep_order" name="delete_dep_order" value=""/>
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_dep" id="delete_dep" class="btn btn-danger btn-sm" value="<?php echo $dependency['id']; ?>">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!~~ /.modal ~~>

					</div> <!~~ /.tab-pane ~~>
 -->

					<div class="tab-pane fade in" id="capabilities-tab">

						<div style="padding: 16px 20px 4px;">
							<div id="capabilities-alert-msg" class="panel panel-danger hidden" style="margin-bottom: 16px; border-color: #d43f3a;">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>At least one capability is required for the definition to be valid.</div>
								</div>
							</div>

							<div class="text-muted" style="font-size: 12px;">Criteria used to determine which computers in your environment have the ability to install and run this patch.<br>The following values are the same as a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria must be ordered in the same way that smart group criteria is ordered.</div>
						</div>

						<table id="capabilities" class="table table-hover">
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
<?php foreach ($capabilities as $capability) { ?>
								<tr>
									<td>
<?php if ($capability['sort_order'] == 0) { ?>
										<input type="hidden" value="<?php echo $capability['is_and']; ?>" />
<?php } else { ?>
										<div class="has-feedback">
											<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateInteger(this, 'capabilities', 'is_and', <?php echo $capability['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
												<option value="1"<?php echo ($capability['is_and'] == "1" ? " selected" : "") ?>>and</option>
												<option value="0"<?php echo ($capability['is_and'] == "0" ? " selected" : "") ?>>or</option>
											</select>
										</div>
<?php } ?>
									</td>
									<td>
										<div class="has-feedback">
											<select class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onChange="updateCriteria(this, 'cap_operator[<?php echo $capability['id']; ?>]', 'cap_type[<?php echo $capability['id']; ?>]', 'capabilities', <?php echo $capability['id']; ?>, true); $('.selectpicker').selectpicker('refresh'); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
<?php foreach ($ext_attrs as $ext_attr) { ?>
												<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($capability['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
<?php } ?>
												<option value="Application Bundle ID"<?php echo ($capability['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
												<option value="Application Title"<?php echo ($capability['name'] == "Application Title" ? " selected" : "") ?> >Application Title</option>
												<option value="Application Version"<?php echo ($capability['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
												<option value="Architecture Type"<?php echo ($capability['name'] == "Architecture Type" ? " selected" : "") ?> >Architecture Type</option>
												<option value="Boot Drive Available MB"<?php echo ($capability['name'] == "Boot Drive Available MB" ? " selected" : "") ?> >Boot Drive Available MB</option>
												<option value="Drive Capacity MB"<?php echo ($capability['name'] == "Drive Capacity MB" ? " selected" : "") ?> >Drive Capacity MB</option>
												<option value="Make"<?php echo ($capability['name'] == "Make" ? " selected" : "") ?> >Make</option>
												<option value="Model"<?php echo ($capability['name'] == "Model" ? " selected" : "") ?> >Model</option>
												<option value="Model Identifier"<?php echo ($capability['name'] == "Model Identifier" ? " selected" : "") ?> >Model Identifier</option>
												<option value="Number of Processors"<?php echo ($capability['name'] == "Number of Processors" ? " selected" : "") ?> >Number of Processors</option>
												<option value="Operating System"<?php echo ($capability['name'] == "Operating System" ? " selected" : "") ?> >Operating System</option>
												<option value="Operating System Build"<?php echo ($capability['name'] == "Operating System Build" ? " selected" : "") ?> >Operating System Build</option>
												<option value="Operating System Name"<?php echo ($capability['name'] == "Operating System Name" ? " selected" : "") ?> >Operating System Name</option>
												<option value="Operating System Version"<?php echo ($capability['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
												<option value="Optical Drive"<?php echo ($capability['name'] == "Optical Drive" ? " selected" : "") ?> >Optical Drive</option>
												<option value="Platform"<?php echo ($capability['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
												<option value="Processor Speed MHz"<?php echo ($capability['name'] == "Processor Speed MHz" ? " selected" : "") ?> >Processor Speed MHz</option>
												<option value="Processor Type"<?php echo ($capability['name'] == "Processor Type" ? " selected" : "") ?> >Processor Type</option>
												<option value="SMC Version"<?php echo ($capability['name'] == "SMC Version" ? " selected" : "") ?> >SMC Version</option>
												<option value="Total Number of Cores"<?php echo ($capability['name'] == "Total Number of Cores" ? " selected" : "") ?> >Total Number of Cores</option>
												<option value="Total RAM MB"<?php echo ($capability['name'] == "Total RAM MB" ? " selected" : "") ?> >Total RAM MB</option>
											</select>
										</div>
										<input type="hidden" id="cap_type[<?php echo $capability['id']; ?>]" value="<?php echo $capability['type']; ?>"/>
									</td>
									<td>
										<div class="has-feedback">
											<select id="cap_operator[<?php echo $capability['id']; ?>]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" onFocus="hideWarning(this);" onChange="updateString(this, 'capabilities', 'operator', <?php echo $capability['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>
												<option value="is"<?php echo ($capability['operator'] == "is" ? " selected" : "") ?> >is</option>
												<option value="is not"<?php echo ($capability['operator'] == "is not" ? " selected" : "") ?> >is not</option>
<?php switch($capability['name']) {
	case "Application Title": ?>
												<option value="has"<?php echo ($capability['operator'] == "has" ? " selected" : "") ?> >has</option>
												<option value="does not have"<?php echo ($capability['operator'] == "does not have" ? " selected" : "") ?> >does not have</option>
<?php break;
	case "Boot Drive Available MB":
	case "Drive Capacity MB":
	case "Number of Processors":
	case "Processor Speed MHz":
	case "Total Number of Cores":
	case "Total RAM MB": ?>
												<option value="more than"<?php echo ($capability['operator'] == "more than" ? " selected" : "") ?> >more than</option>
												<option value="less than"<?php echo ($capability['operator'] == "less than" ? " selected" : "") ?> >less than</option>
<?php break;
	case "Operating System Version": ?>
												<option value="like"<?php echo ($capability['operator'] == "like" ? " selected" : "") ?> >like</option>
												<option value="not like"<?php echo ($capability['operator'] == "not like" ? " selected" : "") ?> >not like</option>
												<option value="greater than"<?php echo ($capability['operator'] == "greater than" ? " selected" : "") ?> >greater than</option>
												<option value="less than"<?php echo ($capability['operator'] == "less than" ? " selected" : "") ?> >less than</option>
												<option value="greater than or equal"<?php echo ($capability['operator'] == "greater than or equal" ? " selected" : "") ?> >greater than or equal</option>
												<option value="less than or equal"<?php echo ($capability['operator'] == "less than or equal" ? " selected" : "") ?> >less than or equal</option>
<?php default: ?>
												<option value="like"<?php echo ($capability['operator'] == "like" ? " selected" : "") ?> >like</option>
												<option value="not like"<?php echo ($capability['operator'] == "not like" ? " selected" : "") ?> >not like</option>
												<option value="matches regex"<?php echo ($capability['operator'] == "matches regex" ? " selected" : "") ?> >matches regex</option>
												<option value="does not match regex"<?php echo ($capability['operator'] == "does not match regex" ? " selected" : "") ?> >does not match regex</option>
<?php } ?>
											</select>
										</div>
									</td>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" style="min-width: 84px;" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'capabilities', 'value', <?php echo $capability['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="" value="<?php echo htmlentities($capability['value']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_cap-modal" onClick="$('#delete_cap_order').val('<?php echo $capability['sort_order']; ?>'); $('#delete_cap').val('<?php echo $capability['id']; ?>');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>Delete</button></td>
								</tr>
<?php } ?>
								<tr>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#create_cap-modal" onClick="newCapModal(); $('.selectpicker').selectpicker('refresh');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>><span class="glyphicon glyphicon-plus"></span> Add</button></td>
								</tr>
							</tbody>
						</table>

						<!-- New Capability Modal -->
						<div class="modal fade" id="create_cap-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">New Capability</h3>
									</div>
									<div class="modal-body">

										<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.</small></h5>
										<div class="form-group">
											<input type="hidden" name="cap_order[0]" id="cap_order[0]" value="<?php echo sizeof($capabilities); ?>" />
											<select id="cap_name[0]" name="cap_name[0]" class="selectpicker" data-style="btn-default btn-sm" data-width="100%" data-container="body" title="Select..." onChange="selectCriteria(this, 'cap_type[0]', 'cap_operator[0]'); validCriteria('create_cap', 'cap_order[0]', 'cap_name[0]', 'cap_operator[0]', 'cap_type[0]');" >
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
											<input type="hidden" name="cap_type[0]" id="cap_type[0]" value="recon" />
											<input type="hidden" name="cap_operator[0]" id="cap_operator[0]" value="is" />
											<input type="hidden" name="cap_value[0]" id="cap_value[0]" value="" />
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_cap" id="create_cap" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete Capability Modal -->
						<div class="modal fade" id="delete_cap-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete Capability?</h3>
									</div>
									<div class="modal-body">
										<input type="hidden" id="delete_cap_order" name="delete_cap_order" value=""/>
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_cap" id="delete_cap" class="btn btn-danger btn-sm" value="<?php echo $capability['id']; ?>">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div><!-- /.tab-pane -->

					<div class="tab-pane fade in" id="killapps-tab">

						<div style="padding: 16px 20px 8px;">
							<div class="text-muted" style="font-size: 12px;">Specifies processes that will be stopped before a patch policy runs.</div>
						</div>

						<table id="kill_apps" class="table table-hover" style="border-bottom: 1px solid #ddd;">
							<thead>
								<tr>
									<th>Application Name</th>
									<th>Bundle Identifier</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
<?php foreach ($kill_apps as $kill_app) { ?>
								<tr>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'kill_apps', 'app_name', <?php echo $kill_app['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo htmlentities($kill_app['app_name']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td>
										<div class="has-feedback">
											<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'kill_apps', 'bundle_id', <?php echo $kill_app['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo htmlentities($kill_app['bundle_id']); ?>" <?php echo ($source_id > 0 ? "disabled" : ""); ?>/>
										</div>
									</td>
									<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#delete_kill_app-modal" onClick="$('#delete_kill_app-title').text('<?php echo htmlentities($kill_app['app_name']); ?>'); $('#delete_kill_app').val('<?php echo $kill_app['id']; ?>');" <?php echo ($source_id > 0 ? "disabled" : ""); ?>>Delete</button></td>
								</tr>
<?php } ?>
							</tbody>
						</table>

						<!-- New KillApp Modal -->
						<div class="modal fade" id="create_kill_app-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Kill Application</h3>
									</div>
									<div class="modal-body">

										<h5 id="kill_app_name_label[0]"><strong>Application Name</strong> <small>Name of the application that will be stopped before a patch policy runs.</small></h5>
										<div class="form-group">
											<input type="text" name="kill_app_name[0]" id="kill_app_name[0]" class="form-control input-sm" onKeyUp="validString(this, 'kill_app_name_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" onBlur="validString(this, 'kill_app_name_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" placeholder="[Required]" value="" />
										</div>

										<h5 id="kill_bundle_id_label[0]"><strong>Bundle Identifier</strong> <small>Bundle identifier of the applications that will be stopped before a patch policy runs.</small></h5>
										<div class="form-group">
											<input type="text" name="kill_bundle_id[0]" id="kill_bundle_id[0]" class="form-control input-sm" onKeyUp="validString(this, 'kill_bundle_id_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" onBlur="validString(this, 'kill_bundle_id_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" placeholder="[Required]" value="" />
										</div>

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="create_kill_app" id="create_kill_app" class="btn btn-primary btn-sm" disabled >Save</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

						<!-- Delete KillApp Modal -->
						<div class="modal fade" id="delete_kill_app-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h3 class="modal-title">Delete <span id="delete_kill_app-title">KillApp</span>?</h3>
									</div>
									<div class="modal-body">
										<div class="text-muted">This action is permanent and cannot be undone.</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="delete_kill_app" id="delete_kill_app" class="btn btn-danger btn-sm" value="">Delete</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

				</div><!-- /.tab-pane -->

			</div> <!-- end .tab-content -->

			</form><!-- end form patchDefinition -->
<?php } else { ?>
			<div style="padding: 64px 20px 0px;">
				<div id="error-msg" style="margin-top: 16px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
					<div class="panel-body">
						<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $error_msg; ?></div>
					</div>
				</div>
			</div>
<?php }
include "inc/footer.php"; ?>