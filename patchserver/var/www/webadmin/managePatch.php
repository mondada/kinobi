<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Patch";

include "inc/header.php";

include "inc/dbConnect.php";

$patches_select = array();

if (isset($pdo)) {

	$stmt = $pdo->prepare('SELECT id FROM patches WHERE id = ?');
	$stmt->execute([$_GET['id']]);
	$patch_id = $stmt->fetchColumn();

}

if (!empty($patch_id)) {

	// Create Component
	if (isset($_POST['create_comp'])) {
		$comp_name = $_POST['comp_name'][0];
		$comp_version = $_POST['comp_version'][0];
		$stmt = $pdo->prepare('INSERT INTO components (patch_id, name, version) VALUES (?, ?, ?)');
		$stmt->execute([$patch_id, $comp_name, $comp_version]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Component
	if (isset($_POST['delete_comp'])) {
		$comp_id = $_POST['delete_comp'];
		$stmt = $pdo->prepare('DELETE FROM components WHERE id = ?');
		$stmt->execute([$comp_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Create Criteria
	if (isset($_POST['create_criteria'])) {
		$criteria_comp_id = implode($_POST['create_criteria']);
		$criteria_name = $_POST['new_criteria_name'][$criteria_comp_id];
		$criteria_operator = $_POST['new_criteria_operator'][$criteria_comp_id];
		$criteria_value = "";
		$criteria_type = $_POST['new_criteria_type'][$criteria_comp_id];
		$criteria_order = $_POST['new_criteria_order'][$criteria_comp_id];
		$criteria_and = "1";
		$stmt = $pdo->prepare('UPDATE criteria SET sort_order = sort_order + 1 WHERE component_id = ? AND sort_order >= ?');
		$stmt->execute([$criteria_comp_id, $criteria_order]);
		$stmt = $pdo->prepare('INSERT INTO criteria (component_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$criteria_comp_id, $criteria_name, $criteria_operator, $criteria_value, $criteria_type, $criteria_and, $criteria_order]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Criteria
	if (isset($_POST['delete_criteria'])) {
		$criteria_id = $_POST['delete_criteria'];
		$criteria_comp_id = $_POST['criteria_comp_id'][$criteria_id];
		$criteria_order = $_POST['criteria_order'][$criteria_id];
		$stmt = $pdo->prepare('UPDATE criteria SET sort_order = sort_order - 1 WHERE component_id = ? AND sort_order > ?');
		$stmt->execute([$criteria_comp_id, $criteria_order]);
		$stmt = $pdo->prepare('DELETE FROM criteria WHERE id = ?');
		$stmt->execute([$criteria_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
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
		$stmt->execute([$patch_id, $dep_order]);
		$stmt = $pdo->prepare('INSERT INTO dependencies (patch_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$patch_id, $dep_name, $dep_operator, $dep_value, $dep_type, $dep_and, $dep_order]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Dependency
	if (isset($_POST['delete_dep'])) {
		$dep_id = $_POST['delete_dep'];
		$dep_order = $_POST['dep_order'][$dep_id];
		$stmt = $pdo->prepare('UPDATE dependencies SET sort_order = sort_order - 1 WHERE patch_id = ? AND sort_order > ?');
		$stmt->execute([$patch_id, $dep_order]);
		$stmt = $pdo->prepare('DELETE FROM dependencies WHERE id = ?');
		$stmt->execute([$dep_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Create Capability
	if (isset($_POST['create_cap'])) {
		$cap_name = $_POST['cap_name'][0];
		$cap_operator = $_POST['cap_operator'][0];
		$cap_value = "";
		$cap_type = $_POST['cap_type'][0];
		$cap_order = $_POST['cap_order'][0];
		$cap_and = "1";
		$stmt = $pdo->prepare('UPDATE capabilities SET sort_order = sort_order + 1 WHERE patch_id = ? AND sort_order >= ?');
		$stmt->execute([$patch_id, $cap_order]);
		$stmt = $pdo->prepare('INSERT INTO capabilities (patch_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$patch_id, $cap_name, $cap_operator, $cap_value, $cap_type, $cap_and, $cap_order]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Capability
	if (isset($_POST['delete_cap'])) {
		$cap_id = $_POST['delete_cap'];
		$cap_order = $_POST['cap_order'][$cap_id];
		$stmt = $pdo->prepare('UPDATE capabilities SET sort_order = sort_order - 1 WHERE patch_id = ? AND sort_order > ?');
		$stmt->execute([$patch_id, $cap_order]);
		$stmt = $pdo->prepare('DELETE FROM capabilities WHERE id = ?');
		$stmt->execute([$cap_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Create Kill Application
	if (isset($_POST['create_kill_app'])) {
		$kill_app_name = $_POST['kill_app_name'][0];
		$kill_bundle_id = $_POST['kill_bundle_id'][0];
		$stmt = $pdo->prepare('INSERT INTO kill_apps (patch_id, bundle_id, app_name) VALUES (?, ?, ?)');
		$stmt->execute([$patch_id, $kill_bundle_id, $kill_app_name]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Kill Application
	if (isset($_POST['delete_kill_app'])) {
		$kill_app_id = $_POST['delete_kill_app'];
		$stmt = $pdo->prepare('DELETE FROM kill_apps WHERE id = ?');
		$stmt->execute([$kill_app_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
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
		$stmt->execute([$patch_id]);
		$title_id = $stmt->fetchColumn();
		$title_modified = time();
		$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
		$stmt->execute([$title_modified, $title_id]);
	}

	// Patch
	$patch = $pdo->query('SELECT name, app_name, bundle_id, title_id, version, released, standalone, min_os, reboot, sort_order, patches.enabled FROM patches JOIN titles ON titles.id = patches.title_id WHERE patches.id = "'.$patch_id.'"')->fetch(PDO::FETCH_ASSOC);
	$patch['standalone'] = ($patch['standalone'] == "0") ? "0": "1";
	$patch['reboot'] = ($patch['reboot'] == "1") ? "1": "0";
	$patch['enabled'] = ($patch['enabled'] == "1") ? "1" : "0";
	$patch['error'] = array();

	// Kill Applications
	$kill_apps = $pdo->query('SELECT id, bundle_id, app_name FROM kill_apps WHERE patch_id = "'.$patch_id.'"')->fetchAll(PDO::FETCH_ASSOC);

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
		$disable->execute([$patch_id]);
		if ($disable->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$disable->errorInfo()[2]."</div>";
		}
	}

	if (isset($_GET['new'])) {
		echo "<div class=\"alert alert-success\"><strong>SUCCESS:</strong> Created Patch '".$patch['name']." ".$patch['version']."'</div>";
	}

} else {

	if (isset($pdo)) {
		echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> Invalid Patch ID '".$_GET["id"]."'</div>";
	}

}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

?>

<?php if (isset($pdo)) { ?>

<script type="text/javascript">
	var extAttrKeys = [<?php echo "\"".implode('", "', array_map(function($el){ return $el['key_id']; }, $ext_attrs))."\""; ?>];
</script>

<script type="text/javascript" src="scripts/patchValidation.js"></script>

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
var patchEnabled = <?php echo $patch['enabled']; ?>;
var componentsError = <?php echo (in_array("components", $patch['error']) ? "1" : "0"); ?>;
var criteriaError = <?php echo (in_array("criteria", $patch['error']) ? "1" : "0"); ?>;
var capabilitiesError = <?php echo (in_array("capabilities", $patch['error']) ? "1" : "0"); ?>;

function showPatchDisabled() {
	$('#patch-tab-icon').removeClass('hidden');
	$('#patch-disabled-msg').removeClass('hidden');
	$('#patch-enabled-msg').addClass('hidden');
}
function hidePatchDisabled() {
	$('#patch-tab-icon').addClass('hidden');
	$('#patch-disabled-msg').addClass('hidden');
	$('#patch-enabled-msg').removeClass('hidden');
}
function togglePatchBtn() {
	$('#enable_patch').prop('disabled', (componentsError == 1 || criteriaError == 1 || capabilitiesError == 1));
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

$(document).ready(function() {
	if (patchEnabled == 0) { showPatchDisabled(); };
	if (componentsError == 1 || criteriaError == 1) { showComponentsError(); };
	if (capabilitiesError == 1) { showCapabilitiesError(); };
	togglePatchBtn();
});

function enablePatch() {
	if (patchEnabled == 0 && componentsError == 0 && criteriaError == 0 && capabilitiesError == 0) {
		ajaxPost('patchCtl.php?patch_id=<?php echo $patch_id; ?>', 'patch_enabled=true');
		hidePatchDisabled();
		patchEnabled = 1;
	}
}
</script>

<link rel="stylesheet" href="theme/bootstrap-datetimepicker.css" />

<script type="text/javascript" src="scripts/datetimepicker/moment.js"></script>
<script type="text/javascript" src="scripts/datetimepicker/transition.js"></script>
<script type="text/javascript" src="scripts/datetimepicker/collapse.js"></script>
<script type="text/javascript" src="scripts/datetimepicker/bootstrap-datetimepicker.min.js"></script>

<script type="text/javascript">
$(function () {
	$('#released').datetimepicker({
		//format: 'MMM D, YYYY \\at h:mm A'
		format: 'YYYY-MM-DDTHH:mm:ss\\Z'
	});
});
</script>

<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />

<?php if (!empty($patch_id)) { ?>
<div class="description"><a href="patchTitles.php">Software Titles</a> <span class="glyphicon glyphicon-chevron-right"></span> <a href="manageTitle.php?id=<?php echo $patch['title_id']; ?>"><?php echo $patch['name']; ?></a> <span class="glyphicon glyphicon-chevron-right"></span></div>
<h2 id="heading"><?php echo $patch['version']; ?></h2>
<?php } ?>

<div class="row">
	<div class="col-xs-12 col-sm-12 col-lg-12">

		<?php if (!empty($patch_id)) { ?>

		<form action="managePatch.php?id=<?php echo $patch_id; ?>" method="post" name="editPatch" id="editPatch">

			<ul class="nav nav-tabs nav-justified" id="top-tabs">
				<li class="active"><a id="patch-tab-link" class="tab-font" href="#patch-tab" role="tab" data-toggle="tab"><span id="patch-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Patch</a></li>
				<li><a id="components-tab-link" class="tab-font" href="#components-tab" role="tab" data-toggle="tab"><span id="components-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Components</a></li>
				<li><a id="dependencies-tab-link" class="tab-font" href="#dependencies-tab" role="tab" data-toggle="tab">Dependencies</a></li>
				<li><a id="capabilities-tab-link" class="tab-font" href="#capabilities-tab" role="tab" data-toggle="tab"><span id="capabilities-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Capabilities</a></li>
				<li><a id="killapps-tab-link" class="tab-font" href="#killapps-tab" role="tab" data-toggle="tab">Kill Applications</a></li>
			</ul>

			<div class="tab-content">

				<div class="tab-pane active fade in" id="patch-tab">

					<div class="description" style="padding: 8px 0px;">Software title version information; one patch is one software title version.<br><strong>Note:</strong> Must be listed in descending order with the newest version at the top of the list.</div>

					<div id="patch-disabled-msg" class="hidden" style="padding-bottom: 8px;">
						<div class="text-muted" style="padding-bottom: 4px;">This patch is disabled.</div>
						<button id="enable_patch" type="button" class="btn btn-sm btn-default" onClick="enablePatch();" disabled>Enable</button>
					</div>
					<div id="patch-enabled-msg" style="padding-bottom: 8px;">
						<div class="text-muted">This patch is enabled.</div>
					</div>

					<h5 id="sort_order_label"><strong>Sort Order</strong></h5>
					<div class="form-group has-feedback" style="max-width: 449px;">
						<input type="text" class="form-control input-sm" onFocus="validInteger(this, 'sort_order_label');" onKeyUp="validInteger(this, 'sort_order_label');" onChange="updateInteger(this, 'patches', 'sort_order', <?php echo $patch_id; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $patch['sort_order']; ?>" />
					</div>
					<h5 id="version_label"><strong>Version</strong> <small>Version associated with this patch.</small></h5>
					<div class="form-group has-feedback" style="max-width: 449px;">
						<input type="text" class="form-control input-sm" onFocus="validString(this, 'version_label');" onKeyUp="validString(this, 'version_label');" onChange="updateString(this, 'patches', 'version', <?php echo $patch_id; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>); document.getElementById('heading').innerHTML = this.value;" placeholder="[Required]" value="<?php echo $patch['version']; ?>" />
					</div>
					<h5 id="released_label"><strong>Release Date</strong> <small>Date that this patch version was released.</small></h5>
					<div class="form-group">
						<div class="input-group has-feedback date" id="released" style="max-width: 449px;">
							<span class="input-group-addon input-sm">
								<span class="glyphicon glyphicon-calendar"></span>
							</span>
							<input type="text" class="form-control input-sm" onFocus="validDate(this, 'released_label');" onBlur="validDate(this, 'released_label'); updateDate(this, 'patches', 'released', <?php echo $patch_id; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo gmdate("Y-m-d\TH:i:s\Z", $patch['released']); ?>" />
						</div>
					</div>
					<h5><strong>Standalone</strong> <small><span style="font-family:monospace;">true</span> specifies a patch that can be installed by itself. <span style="font-family:monospace;">false</span> specifies a patch that must be installed incrementally.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</small></h5>
					<div class="form-group" style="max-width: 449px;">
						<select class="form-control input-sm" onFocus="hideSuccess(this);" onChange="updateInteger(this, 'patches', 'standalone', <?php echo $patch_id; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" >
							<option value="1" <?php echo ($patch['standalone'] == "1" ? " selected" : "") ?> >Yes</option>
							<option value="0" <?php echo ($patch['standalone'] == "0" ? " selected" : "") ?> >No</option>
						</select>
					</div>
					<h5 id="min_os_label"><strong>Minimum Operating System</strong> <small>Lowest macOS version capable of installing this patch.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes. See the capabilities array for patch policy implementation.</small></h5>
					<div class="form-group has-feedback" style="max-width: 449px;">
						<input type="text" class="form-control input-sm" onFocus="validString(this, 'min_os_label');" onKeyUp="validString(this, 'min_os_label');" onChange="updateString(this, 'patches', 'min_os', <?php echo $patch_id; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $patch['min_os']; ?>" />
					</div>
					<h5><strong>Reboot</strong> <small><span style="font-family:monospace;">true</span> specifies that the computer must be restarted after the patch policy has completed successfully. <span style="font-family:monospace;">false</span> specifies that the computer will not be restarted.</small></h5>
					<div class="form-group" style="max-width: 449px;">
							<select class="form-control input-sm" onFocus="hideSuccess(this);" onChange="updateInteger(this, 'patches', 'reboot', <?php echo $patch_id; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" >
								<option value="0" <?php echo ($patch['reboot'] == "0" ? " selected" : "") ?> >No</option>
								<option value="1" <?php echo ($patch['reboot'] == "1" ? " selected" : "") ?> >Yes</option>
							</select>
					</div>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="components-tab">

					<div class="description" style="padding-top: 8px;">Defines the elements that comprise this patch version.<br><strong>Note:</strong> Only one element is supported by Jamf Pro at this time.</div>

					<div id="components-alert-msg" style="padding-top: 8px;" class="hidden">
						<div class="text-danger"><span class="glyphicon glyphicon-exclamation-sign"></span> At least one component is required for the patch to be valid.</div>
					</div>

					<div class="dataTables_wrapper form-inline dt-bootstrap no-footer">
						<div class="row">
							<div class="col-sm-12">
								<div class="dataTables_button">
									<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createComponent"<?php echo (sizeof($components) > 0 ? " disabled" : "") ?>><span class="glyphicon glyphicon-plus"></span> New</button>
								</div>
							</div>
						</div>
					</div>

					<table class="table table-striped">
						<?php foreach ($components as $component) { ?>
						<thead>
							<tr>
								<th colspan="3">Name</th>
								<th colspan="3">Version</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td colspan="3">
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'components', 'name', <?php echo $component['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $component['name']; ?>" />
									</div>
								</td>
								<td colspan="3">
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'components', 'version', <?php echo $component['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $component['version']; ?>" />
									</div>
								</td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteComp<?php echo $component['id']; ?>">Delete</button></td>
							</tr>
						</tbody>
						<thead>
							<tr>
								<td colspan="7">
									<h5><strong>Criteria</strong> <small>Criteria used to determine which computers in your environment have this patch version installed.<!-- <br>The following values correspond with a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria objects in an array must be ordered in the same way that smart group criteria is ordered. --></small></h5>
									<?php if (sizeof($component['criteria']) == 0) { ?>
									<div style="padding-top: 8px;">
										<div class="text-danger"><span class="glyphicon glyphicon-exclamation-sign"></span>At least one criteria is required for the component to be valid.</div>
									</div>
									<?php } ?>
								</td>
							</tr>
							<tr>
								<th>Order</th>
								<th>Criteria</th>
								<th colspan="2">Operator</th>
								<th>Value</th>
								<th>and/or</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($component['criteria'] as $criteria) { ?>
							<tr>
								<td>
									<div class="has-feedback">
										<input type="text" size="3" name="criteria_order[<?php echo $criteria['id']; ?>]" class="form-control input-sm" onKeyUp="validInteger(this);" onChange="updateInteger(this, 'criteria', 'sort_order', <?php echo $criteria['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $criteria['sort_order']; ?>" />
									</div>
								</td>
								<td>
									<select class="form-control input-sm" onChange="updateCriteria(this, 'criteria_operator[<?php echo $criteria['id']; ?>]', 'criteria_type[<?php echo $criteria['id']; ?>]', 'criteria', <?php echo $criteria['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);">
										<option value=""<?php echo ($criteria['name'] == "" ? " selected" : "") ?> disabled ></option>
										<?php foreach ($ext_attrs as $ext_attr) { ?>
										<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($criteria['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
										<?php } ?>
										<option value="Application Bundle ID"<?php echo ($criteria['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
										<option value="Application Version"<?php echo ($criteria['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
										<option value="Platform"<?php echo ($criteria['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
										<option value="Operating System Version"<?php echo ($criteria['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
									</select>
									<input type="hidden" id="criteria_type[<?php echo $criteria['id']; ?>]" value="<?php echo $criteria['type']; ?>"/>
								</td>
								<td colspan="2">
									<select id="criteria_operator[<?php echo $criteria['id']; ?>]" class="form-control input-sm" onFocus="hideWarning(this);" onChange="updateString(this, 'criteria', 'operator', <?php echo $criteria['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" >
										<option value="is"<?php echo ($criteria['operator'] == "is" ? " selected" : "") ?> >is</option>
										<option value="is not"<?php echo ($criteria['operator'] == "is not" ? " selected" : "") ?> >is not</option>
										<option value="like"<?php echo ($criteria['operator'] == "like" ? " selected" : "") ?> >like</option>
										<option value="not like"<?php echo ($criteria['operator'] == "not like" ? " selected" : "") ?> >not like</option>
										<option value="greater than"<?php echo ($criteria['operator'] == "greater than" ? " selected" : "") ?><?php echo ($criteria['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than</option>
										<option value="less than"<?php echo ($criteria['operator'] == "less than" ? " selected" : "") ?><?php echo ($criteria['name'] != "Operating System Version" ? " disabled" : "") ?> >less than</option>
										<option value="greater than or equal"<?php echo ($criteria['operator'] == "greater than or equal" ? " selected" : "") ?><?php echo ($criteria['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than or equal</option>
										<option value="less than or equal"<?php echo ($criteria['operator'] == "less than or equal" ? " selected" : "") ?><?php echo ($criteria['name'] != "Operating System Version" ? " disabled" : "") ?> >less than or equal</option>
									</select>
								</td>
								<td>
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'criteria', 'value', <?php echo $criteria['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="" value="<?php echo $criteria['value']; ?>" />
									</div>
								</td>
								<td>
									<select class="form-control input-sm" onChange="updateInteger(this, 'criteria', 'is_and', <?php echo $criteria['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);">
										<option value="1"<?php echo ($criteria['is_and'] == "1" ? " selected" : "") ?> >and</option>
										<option value="0"<?php echo ($criteria['is_and'] == "0" ? " selected" : "") ?> >or</option>
									</select>
								</td>
								<td align="right">
									<input type="hidden" name="criteria_comp_id[<?php echo $criteria['id']; ?>]" value="<?php echo $component['id']; ?>"/>
									<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteCriteria<?php echo $criteria['id']; ?>">Delete</button>
								</td>
							</tr>
							<?php } ?>
							<tr>
								<td colspan="7" align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#createCriteria<?php echo $component['id']; ?>"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
							</tr>
						</tbody>

						<?php } ?>
						<?php if (sizeof($components) == 0) { ?>
						<thead>
							<tr>
								<th colspan="3">Name</th>
								<th colspan="3">Version</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td align="center" valign="top" colspan="7" class="dataTables_empty">No data available in table</td>
							</tr>
						</tbody>
						<?php } ?>
					</table>

					<div class="modal fade" id="createComponent" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">New Component</h3>
								</div>
								<div class="modal-body">

									<h5 id="comp_name_label[0]"><strong>Name</strong> <small>Name of the patch management software title.</small></h5>
									<div class="form-group">
										<input type="text" name="comp_name[0]" id="comp_name[0]" class="form-control input-sm" onKeyUp="validString(this, 'comp_name_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" onBlur="validString(this, 'comp_name_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" placeholder="[Required]" value="<?php echo (sizeof($components) == 0 ? $patch['name'] : "") ?>"/>
									</div>

									<h5 id="comp_version_label[0]"><strong>Version</strong> <small>Version associated with this patch.</small></h5>
									<div class="form-group">
										<input type="text" name="comp_version[0]" id="comp_version[0]" class="form-control input-sm" onKeyUp="validString(this, 'comp_version_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" onBlur="validString(this, 'comp_version_label[0]'); validComponent('create_comp', 'comp_name[0]', 'comp_version[0]');" placeholder="[Required]" value="<?php echo (sizeof($components) == 0 ? $patch['version'] : "") ?>"/>
									</div>

								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="create_comp" id="create_comp" class="btn btn-primary btn-sm" <?php echo (sizeof($components) == 0 && $patch['name'] != "" && $patch['version'] != "" ? "" : "disabled") ?>>Save</button>
								</div>
							</div>
						</div>
					</div>

					<?php foreach ($components as $component) { ?>
					<div class="modal fade" id="deleteComp<?php echo $component['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">Delete Component?</h3>
								</div>
								<div class="modal-body">
									<div class="text-muted">This action is permanent and cannot be undone.</div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_comp" id="delete_comp" class="btn btn-danger btn-sm" value="<?php echo $component['id']; ?>">Delete</button>
								</div>
							</div>
						</div>
					</div>

					<div class="modal fade" id="createCriteria<?php echo $component['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">New Criteria</h3>
								</div>
								<div class="modal-body">

									<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.<br>When type is <span style="font-family:monospace;">extensionAttribute</span>, the name value is the key defined in the extensionAttribute object.</small></h5>
									<div class="form-group">
										<input type="hidden" name="new_criteria_order[<?php echo $component['id']; ?>]" id="new_criteria_order[<?php echo $component['id']; ?>]" value="<?php echo sizeof($component['criteria']); ?>" />
										<select id="new_criteria_name[<?php echo $component['id']; ?>]" name="new_criteria_name[<?php echo $component['id']; ?>]" class="form-control input-sm" onChange="selectCriteria(this, 'new_criteria_type[<?php echo $component['id']; ?>]', 'new_criteria_operator[<?php echo $component['id']; ?>]'); validCriteria('create_criteria[<?php echo $component['id']; ?>]', 'new_criteria_order[<?php echo $component['id']; ?>]', 'new_criteria_name[<?php echo $component['id']; ?>]', 'new_criteria_operator[<?php echo $component['id']; ?>]', 'new_criteria_type[<?php echo $component['id']; ?>]');" >
											<option value="" disabled selected>Select...</option>
											<?php foreach ($ext_attrs as $ext_attr) { ?>
											<option value="<?php echo $ext_attr['key_id']; ?>"><?php echo $ext_attr['name']; ?></option>
											<?php } ?>
											<option value="Application Bundle ID">Application Bundle ID</option>
											<option value="Application Version">Application Version</option>
											<option value="Platform">Platform</option>
											<option value="Operating System Version">Operating System Version</option>
										</select>
										<input type="hidden" name="new_criteria_type[<?php echo $component['id']; ?>]" id="new_criteria_type[<?php echo $component['id']; ?>]" value="recon" />
										<input type="hidden" name="new_criteria_operator[<?php echo $component['id']; ?>]" id="new_criteria_operator[<?php echo $component['id']; ?>]" value="is" />
									</div>

								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="create_criteria[<?php echo $component['id']; ?>]" id="create_criteria[<?php echo $component['id']; ?>]" class="btn btn-primary btn-sm" value="<?php echo $component['id']; ?>" disabled >Save</button>
								</div>
							</div>
						</div>
					</div>
					
					<?php foreach ($component['criteria'] as $criteria) { ?>
					<div class="modal fade" id="deleteCriteria<?php echo $criteria['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">Delete Criteria?</h3>
								</div>
								<div class="modal-body">
									<div class="text-muted">This action is permanent and cannot be undone.</div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_criteria" id="delete_criteria" class="btn btn-danger btn-sm" value="<?php echo $criteria['id']; ?>">Delete</button>
								</div>
							</div>
						</div>
					</div>
					<?php }
					} ?>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="dependencies-tab">

					<div class="description" style="padding: 8px 0px;">Not currently used by Jamf Pro.<br><strong>Note:</strong> Cannot be a null value.</div>

					<table class="table table-striped">
						<thead>
							<tr>
								<th>Order</th>
								<th>Criteria</th>
								<th>Operator</th>
								<th>Value</th>
								<th>and/or</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($dependencies as $dependency) { ?>
							<tr>
								<td>
									<div class="has-feedback">
										<input type="text" size="3" name="dep_order[<?php echo $dependency['id']; ?>]" class="form-control input-sm" onKeyUp="validInteger(this);" onChange="updateInteger(this, 'dependencies', 'sort_order', <?php echo $dependency['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $dependency['sort_order']; ?>" />
									</div>
								</td>
								<td>
									<select class="form-control input-sm" onChange="updateCriteria(this, 'dep_operator[<?php echo $dependency['id']; ?>]', 'dep_type[<?php echo $dependency['id']; ?>]', 'dependencies', <?php echo $dependency['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);">
										<?php foreach ($ext_attrs as $ext_attr) { ?>
										<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($dependency['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
										<?php } ?>
										<option value="Application Bundle ID"<?php echo ($dependency['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
										<option value="Application Version"<?php echo ($dependency['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
										<option value="Platform"<?php echo ($dependency['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
										<option value="Operating System Version"<?php echo ($dependency['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
									</select>
									<input type="hidden" id="dep_type[<?php echo $dependency['id']; ?>]" value="<?php echo $dependency['type']; ?>"/>
								</td>
								<td>
									<select id="dep_operator[<?php echo $dependency['id']; ?>]" class="form-control input-sm" onFocus="hideWarning(this);" onChange="updateString(this, 'dependencies', 'operator', <?php echo $dependency['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" >
										<option value="is"<?php echo ($dependency['operator'] == "is" ? " selected" : "") ?> >is</option>
										<option value="is not"<?php echo ($dependency['operator'] == "is not" ? " selected" : "") ?> >is not</option>
										<option value="like"<?php echo ($dependency['operator'] == "like" ? " selected" : "") ?> >like</option>
										<option value="not like"<?php echo ($dependency['operator'] == "not like" ? " selected" : "") ?> >not like</option>
										<option value="greater than"<?php echo ($dependency['operator'] == "greater than" ? " selected" : "") ?><?php echo ($dependency['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than</option>
										<option value="less than"<?php echo ($dependency['operator'] == "less than" ? " selected" : "") ?><?php echo ($dependency['name'] != "Operating System Version" ? " disabled" : "") ?> >less than</option>
										<option value="greater than or equal"<?php echo ($dependency['operator'] == "greater than or equal" ? " selected" : "") ?><?php echo ($dependency['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than or equal</option>
										<option value="less than or equal"<?php echo ($dependency['operator'] == "less than or equal" ? " selected" : "") ?><?php echo ($dependency['name'] != "Operating System Version" ? " disabled" : "") ?> >less than or equal</option>
									</select>
								</td>
								<td>
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'dependencies', 'value', <?php echo $dependency['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="" value="<?php echo $dependency['value']; ?>" />
									</div>
								</td>
								<td>
									<select class="form-control input-sm" onChange="updateInteger(this, 'dependencies', 'is_and', <?php echo $dependency['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);">
										<option value="1"<?php echo ($dependency['is_and'] == "1" ? " selected" : "") ?>>and</option>
										<option value="0"<?php echo ($dependency['is_and'] == "0" ? " selected" : "") ?>>or</option>
									</select>
								</td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteDep<?php echo $dependency['id']; ?>">Delete</button></td>
							</tr>
							<?php } ?>
							<tr>
								<td colspan="6" align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#createDependency"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
							</tr>
						</tbody>
					</table>

					<div class="modal fade" id="createDependency" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">New Dependency</h3>
								</div>
								<div class="modal-body">

									<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.<br>When type is <span style="font-family:monospace;">extensionAttribute</span>, the name value is the key defined in the extensionAttribute object.</small></h5>
									<div class="form-group">
										<input type="hidden" name="dep_order[0]" id="dep_order[0]" value="<?php echo sizeof($dependencies); ?>" />
										<select id="dep_name[0]" name="dep_name[0]" class="form-control input-sm" onChange="selectCriteria(this, 'dep_type[0]', 'dep_operator[0]'); validCriteria('create_dep', 'dep_order[0]', 'dep_name[0]', 'dep_operator[0]', 'dep_type[0]');" >
											<option value="" disabled selected>Select...</option>
											<?php foreach ($ext_attrs as $ext_attr) { ?>
											<option value="<?php echo $ext_attr['key_id']; ?>"><?php echo $ext_attr['name']; ?></option>
											<?php } ?>
											<option value="Application Bundle ID">Application Bundle ID</option>
											<option value="Application Version">Application Version</option>
											<option value="Platform">Platform</option>
											<option value="Operating System Version">Operating System Version</option>
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

					<?php foreach ($dependencies as $dependency) { ?>
					<div class="modal fade" id="deleteDep<?php echo $dependency['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">Delete Dependency?</h3>
								</div>
								<div class="modal-body">
									<div class="text-muted">This action is permanent and cannot be undone.</div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_dep" id="delete_dep" class="btn btn-danger btn-sm" value="<?php echo $dependency['id']; ?>">Delete</button>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>

				</div> <!-- /.tab-pane -->

				<div class="tab-pane fade in" id="capabilities-tab">

					<div class="description" style="padding: 8px 0px;">Criteria used to determine which computers in your environment have the ability to install and run this patch.<br>The following values correspond with a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria objects in an array must be ordered in the same way that smart group criteria is ordered.</div>

					<div id="capabilities-alert-msg" style="padding-bottom: 8px;" class="hidden">
						<div class="text-danger"><span class="glyphicon glyphicon-exclamation-sign"></span> At least one capability is required for the definition to be valid.</div>
					</div>

					<table class="table table-striped">
						<thead>
							<tr>
								<th>Order</th>
								<th>Criteria</th>
								<th>Operator</th>
								<th>Value</th>
								<th>and/or</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($capabilities as $capability) { ?>
							<tr>
								<td>
									<div class="has-feedback">
										<input type="text" size="3" name="cap_order[<?php echo $capability['id']; ?>]" class="form-control input-sm" onKeyUp="validInteger(this);" onChange="updateInteger(this, 'capabilities', 'sort_order', <?php echo $capability['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $capability['sort_order']; ?>" />
									</div>
								</td>
								<td>
									<select class="form-control input-sm" onChange="updateCriteria(this, 'cap_operator[<?php echo $capability['id']; ?>]', 'cap_type[<?php echo $capability['id']; ?>]', 'capabilities', <?php echo $capability['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);">
										<?php foreach ($ext_attrs as $ext_attr) { ?>
										<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($capability['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
										<?php } ?>
										<option value="Application Bundle ID"<?php echo ($capability['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
										<option value="Application Version"<?php echo ($capability['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
										<option value="Platform"<?php echo ($capability['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
										<option value="Operating System Version"<?php echo ($capability['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
									</select>
									<input type="hidden" id="cap_type[<?php echo $capability['id']; ?>]" value="<?php echo $capability['type']; ?>"/>
								</td>
								<td>
									<select id="cap_operator[<?php echo $capability['id']; ?>]" class="form-control input-sm" onFocus="hideWarning(this);" onChange="updateString(this, 'capabilities', 'operator', <?php echo $capability['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);" >
										<option value="is"<?php echo ($capability['operator'] == "is" ? " selected" : "") ?> >is</option>
										<option value="is not"<?php echo ($capability['operator'] == "is not" ? " selected" : "") ?> >is not</option>
										<option value="like"<?php echo ($capability['operator'] == "like" ? " selected" : "") ?> >like</option>
										<option value="not like"<?php echo ($capability['operator'] == "not like" ? " selected" : "") ?> >not like</option>
										<option value="greater than"<?php echo ($capability['operator'] == "greater than" ? " selected" : "") ?><?php echo ($capability['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than</option>
										<option value="less than"<?php echo ($capability['operator'] == "less than" ? " selected" : "") ?><?php echo ($capability['name'] != "Operating System Version" ? " disabled" : "") ?> >less than</option>
										<option value="greater than or equal"<?php echo ($capability['operator'] == "greater than or equal" ? " selected" : "") ?><?php echo ($capability['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than or equal</option>
										<option value="less than or equal"<?php echo ($capability['operator'] == "less than or equal" ? " selected" : "") ?><?php echo ($capability['name'] != "Operating System Version" ? " disabled" : "") ?> >less than or equal</option>
									</select>
								</td>
								<td>
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'capabilities', 'value', <?php echo $capability['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="" value="<?php echo $capability['value']; ?>" />
									</div>
								</td>
								<td>
									<select class="form-control input-sm" onChange="updateInteger(this, 'capabilities', 'is_and', <?php echo $capability['id']; ?>); updateTimestamp(<?php echo $patch['title_id']; ?>);">
										<option value="1"<?php echo ($capability['is_and'] == "1" ? " selected" : "") ?>>and</option>
										<option value="0"<?php echo ($capability['is_and'] == "0" ? " selected" : "") ?>>or</option>
									</select>
								</td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteCap<?php echo $capability['id']; ?>">Delete</button></td>
							</tr>
							<?php } ?>
							<tr>
								<td colspan="6" align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#createCapability"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
							</tr>
						</tbody>
					</table>

					<div class="modal fade" id="createCapability" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">New Capability</h3>
								</div>
								<div class="modal-body">

									<h5><strong>Criteria</strong> <small>Any valid Jamf Pro smart group criteria.<br>When type is <span style="font-family:monospace;">extensionAttribute</span>, the name value is the key defined in the extensionAttribute object.</small></h5>
									<div class="form-group">
										<input type="hidden" name="cap_order[0]" id="cap_order[0]" value="<?php echo sizeof($capabilities); ?>" />
										<select id="cap_name[0]" name="cap_name[0]" class="form-control input-sm" onChange="selectCriteria(this, 'cap_type[0]', 'cap_operator[0]'); validCriteria('create_cap', 'cap_order[0]', 'cap_name[0]', 'cap_operator[0]', 'cap_type[0]');" >
											<option value="" disabled selected>Select...</option>
											<?php foreach ($ext_attrs as $ext_attr) { ?>
											<option value="<?php echo $ext_attr['key_id']; ?>"><?php echo $ext_attr['name']; ?></option>
											<?php } ?>
											<option value="Application Bundle ID">Application Bundle ID</option>
											<option value="Application Version">Application Version</option>
											<option value="Platform">Platform</option>
											<option value="Operating System Version">Operating System Version</option>
										</select>
										<input type="hidden" name="cap_type[0]" id="cap_type[0]" value="recon" />
										<input type="hidden" name="cap_operator[0]" id="cap_operator[0]" value="is" />
									</div>

								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="create_cap" id="create_cap" class="btn btn-primary btn-sm" disabled >Save</button>
								</div>
							</div>
						</div>
					</div>

					<?php foreach ($capabilities as $capability) { ?>
					<div class="modal fade" id="deleteCap<?php echo $capability['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">Delete Capability?</h3>
								</div>
								<div class="modal-body">
									<div class="text-muted">This action is permanent and cannot be undone.</div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_cap" id="delete_cap" class="btn btn-danger btn-sm" value="<?php echo $capability['id']; ?>">Delete</button>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="killapps-tab">

					<div class="description" style="padding: 8px 0px;">Specifies processes that will be stopped before a patch policy runs.</div>

					<div class="dataTables_wrapper form-inline dt-bootstrap no-footer">
						<div class="row">
							<div class="col-sm-12">
								<div class="dataTables_button">
									<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createKillApp"><span class="glyphicon glyphicon-plus"></span> New</button>
								</div>
							</div>
						</div>
					</div>

					<table id="kill_apps" class="table table-striped">
						<thead>
							<tr>
								<th><nobr>Application Name</nobr></th>
								<th><nobr>Bundle Identifier</nobr></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($kill_apps as $kill_app) { ?><tr>
								<td>
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'kill_apps', 'app_name', <?php echo $kill_app['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $kill_app['app_name']; ?>" />
									</div>
								</td>
								<td>
									<div class="has-feedback">
										<input type="text" class="form-control input-sm" onKeyUp="validString(this);" onChange="updateString(this, 'kill_apps', 'bundle_id', <?php echo $kill_app['id']; ?>, true); updateTimestamp(<?php echo $patch['title_id']; ?>);" placeholder="[Required]" value="<?php echo $kill_app['bundle_id']; ?>" />
									</div>
								</td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteKillApp<?php echo $kill_app['id']; ?>">Delete</button></td>
							</tr><?php } ?>
							<?php if (sizeof($kill_apps) == 0) { ?><tr>
								<td align="center" valign="top" colspan="3" class="dataTables_empty">No data available in table</td>
							</tr><?php } ?>
						</tbody>
						</tbody>
					</table>

					<div class="modal fade" id="createKillApp" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">New Application</h3>
								</div>
								<div class="modal-body">

									<h5 id="kill_app_name_label[0]"><strong>Application Name</strong> <small>Name of the application that will be stopped before a patch policy runs.</small></h5>
									<div class="form-group">
										<input type="text" name="kill_app_name[0]" id="kill_app_name[0]" class="form-control input-sm" onKeyUp="validString(this, 'kill_app_name_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" onBlur="validString(this, 'kill_app_name_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" placeholder="[Required]" />
									</div>

									<h5 id="kill_bundle_id_label[0]"><strong>Bundle Identifier</strong> <small>Bundle identifier of the applications that will be stopped before a patch policy runs.</small></h5>
									<div class="form-group">
										<input type="text" name="kill_bundle_id[0]" id="kill_bundle_id[0]" class="form-control input-sm" onKeyUp="validString(this, 'kill_bundle_id_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" onBlur="validString(this, 'kill_bundle_id_label[0]'); validKillApp('create_kill_app', 'kill_app_name[0]', 'kill_bundle_id[0]');" placeholder="[Required]" />
									</div>

								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="create_kill_app" id="create_kill_app" class="btn btn-primary btn-sm" disabled >Save</button>
								</div>
							</div>
						</div>
					</div>

					<?php foreach ($kill_apps as $kill_app) { ?>
					<div class="modal fade" id="deleteKillApp<?php echo $kill_app['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h3 class="modal-title" id="modalLabel">Delete <?php echo $kill_app['app_name']; ?>?</h3>
								</div>
								<div class="modal-body">
									<div class="text-muted">This action is permanent and cannot be undone.</div>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_kill_app" id="delete_kill_app" class="btn btn-danger btn-sm" value="<?php echo $kill_app['id']; ?>">Delete</button>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>

				</div><!-- /.tab-pane -->

			</div> <!-- end .tab-content -->

		</form><!-- end form patchDefinition -->

		<?php } else { ?>

		<br>

		<?php } ?>

	</div><!-- /.col -->
</div><!-- /.row -->

<?php } else { ?>

<div class="row">
	<div class="col-xs-12 col-sm-12 col-lg-12">

		<hr>
		<br>

		<input type="button" id="settings-button" name="action" class="btn btn-sm btn-default" value="Settings" onclick="document.location.href='patchDB.php'">

	</div><!-- /.col -->
</div><!-- /.row -->

<?php } ?>

<?php include "inc/footer.php"; ?>