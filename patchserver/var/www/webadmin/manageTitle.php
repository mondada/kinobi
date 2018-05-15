<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Software Title";

//if (!isset($_POST['create_patch'])) {
	include "inc/header.php";
//}

include "inc/dbConnect.php";

$sw_title_name_ids = array();
$ext_attr_keys = array();

if (isset($pdo)) {

	$stmt = $pdo->prepare('SELECT id FROM titles WHERE id = ?');
	$stmt->execute([$_GET['id']]);
	$title_id = $stmt->fetchColumn();

}

if (!empty($title_id)) {

	// Create Extension Attribute
	if (isset($_POST['create_ea'])) {
		$ea_key_id = $_POST['ea_key_id'][0];
		$ea_script = $_POST['ea_script'][0];
		$ea_name = $_POST['ea_name'][0];
		$stmt = $pdo->prepare('INSERT INTO ext_attrs (title_id, key_id, script, name) VALUES (?, ?, ?, ?)');
		$stmt->execute([$title_id, $ea_key_id, $ea_script, $ea_name]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Save Extension Attribute
	if (isset($_POST['save_ea'])) {
		$ea_id = $_POST['save_ea'][$ea_id];
		$ea_key_id = $_POST['ea_key_id'][$ea_id];
		$ea_script = $_POST['ea_script'][$ea_id];
		$ea_name = $_POST['ea_name'][$ea_id];
		$stmt = $pdo->prepare('UPDATE ext_attrs SET key_id = ?, script = ?, name = ? WHERE id = ?');
		$stmt->execute([$ea_key_id, $ea_script, $ea_name, $ea_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Extension Attribute
	if (isset($_POST['delete_ea'])) {
		$ea_id = $_POST['delete_ea'];
		$ea_key_id = $_POST['ea_key_id'][$ea_id];
		$pdo->beginTransaction();
		$stmt = $pdo->prepare('DELETE FROM requirements WHERE name = ?');
		$stmt->execute([$ea_key_id]);
		$stmt = $pdo->prepare('DELETE FROM capabilities WHERE name = ?');
		$stmt->execute([$ea_key_id]);
		$stmt = $pdo->prepare('DELETE FROM dependencies WHERE name = ?');
		$stmt->execute([$ea_key_id]);
		$stmt = $pdo->prepare('DELETE FROM criteria WHERE name = ?');
		$stmt->execute([$ea_key_id]);
		$stmt = $pdo->prepare('DELETE FROM ext_attrs WHERE id = ?');
		$stmt->execute([$ea_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
		$pdo->commit();
	}

	// Create Requirement
	if (isset($_POST['create_rqmt'])) {
		$rqmt_name = $_POST['rqmt_name'][0];
		$rqmt_operator = $_POST['rqmt_operator'][0];
		$rqmt_value = "";
		$rqmt_type = $_POST['rqmt_type'][0];
		$rqmt_order = $_POST['rqmt_order'][0];
		$rqmt_and = "1";
		$stmt = $pdo->prepare('UPDATE requirements SET sort_order = sort_order + 1 WHERE title_id = ? AND sort_order >= ?');
		$stmt->execute([$title_id, $rqmt_order]);
		$stmt = $pdo->prepare('INSERT INTO requirements (title_id, name, operator, value, type, is_and, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$title_id, $rqmt_name, $rqmt_operator, $rqmt_value, $rqmt_type, $rqmt_and, $rqmt_order]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Requirement
	if (isset($_POST['delete_rqmt'])) {
		$rqmt_id = $_POST['delete_rqmt'];
		$rqmt_order = $_POST['rqmt_order'][$rqmt_id];
		$stmt = $pdo->prepare('UPDATE requirements SET sort_order = sort_order - 1 WHERE title_id = ? AND sort_order > ?');
		$stmt->execute([$title_id, $rqmt_order]);
		$stmt = $pdo->prepare('DELETE FROM requirements WHERE id = ?');
		$stmt->execute([$rqmt_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
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
		$stmt->execute([$title_id, $patch_order]);
		$stmt = $pdo->prepare('INSERT INTO patches (title_id, version, released, standalone, min_os, reboot, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$title_id, $patch_version, $patch_released, $patch_standalone, $patch_min_os, $patch_reboot, $patch_order]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Delete Patch
	if (isset($_POST['delete_patch'])) {
		$patch_id = $_POST['delete_patch'];
		$patch_order = $_POST['patch_order'][$patch_id];
		$stmt = $pdo->prepare('UPDATE patches SET sort_order = sort_order - 1 WHERE title_id = ? AND sort_order > ?');
		$stmt->execute([$title_id, $patch_order]);
		$stmt = $pdo->prepare('DELETE FROM patches WHERE id = ?');
		$stmt->execute([$patch_id]);
		if ($stmt->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$stmt->errorInfo()[2]."</div>";
		}
	}

	// Update Title Modified
	if (isset($_POST['create_ea'])
	 || isset($_POST['save_ea'])
	 || isset($_POST['delete_ea'])
	 || isset($_POST['create_rqmt'])
	 || isset($_POST['delete_rqmt'])
	 || isset($_POST['create_patch'])
	 || isset($_POST['delete_patch'])) {
		$title_modified = time();
		$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
		$stmt->execute([$title_modified, $title_id]);
	}

	// Software Title
	$sw_title = $pdo->query('SELECT name, publisher, app_name, bundle_id, modified, current, name_id, enabled FROM titles WHERE id = "'.$title_id.'"')->fetch(PDO::FETCH_ASSOC);
	$sw_title['error'] = array();

	// Software Title Select
	$sw_titles_select = $pdo->query('SELECT id, name FROM titles ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

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
			$disable->execute([$patch['id']]);
			if ($disable->errorCode() != '00000') {
				echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$disable->errorInfo()[2]."</div>";
			}
		}
		array_push($patches, $patch);
	}

	// Enabled Pacthes
	if (!in_array(1, array_map(function($el){ return $el['enabled']; }, $patches))) {
		array_push($sw_title['error'], "patches");
	}

	// Disable Incomplete Title
	if (sizeof($sw_title['error']) > 0 && $sw_title['enabled'] == "1") {
		$sw_title['enabled'] = "0";
		$disable = $pdo->prepare('UPDATE titles SET enabled = 0 WHERE id = ?');
		$disable->execute([$title_id]);
		if ($disable->errorCode() != '00000') {
			echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$disable->errorInfo()[2]."</div>";
		}
	}

} else {

	if (isset($pdo)) {
		echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> Invalid Software Title ID '".$_GET['id']."'</div>";
	}

}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

?>

<?php if (isset($pdo)) { ?>

<style>
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
</style>

<link rel="stylesheet" href="theme/checkbox.bootstrap.css"/>

<script type="text/javascript">
	var existingIds = [<?php echo "\"".implode('", "', $sw_title_name_ids)."\""; ?>];
	var existingKeys = [<?php echo "\"".implode('", "', $ext_attr_keys)."\""; ?>];
	var extAttrKeys = [<?php echo "\"".implode('", "', array_map(function($el){ return $el['key_id']; }, $ext_attrs))."\""; ?>];
</script>

<script type="text/javascript" src="scripts/patchValidation.js"></script>

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
var titleEnabled = <?php echo $sw_title['enabled']; ?>;
var sizeOfRqmts = <?php echo sizeof($requirements); ?>;
var enabledPatches = <?php echo array_sum(array_map(function($el){ return $el['enabled']; }, $patches)); ?>;

function showTitleDisabled() {
	$('#title-tab-icon').removeClass('hidden');
	$('#title-disabled-msg').removeClass('hidden');
	$('#title-enabled-msg').addClass('hidden');
}
function hideTitleDisabled() {
	$('#title-tab-icon').addClass('hidden');
	$('#title-disabled-msg').addClass('hidden');
	$('#title-enabled-msg').removeClass('hidden');
}
function toggleTitleBtn() {
	$('#enable_title').prop('disabled', (sizeOfRqmts == 0 || enabledPatches == 0));
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

$(document).ready(function() {
	if (titleEnabled == 0) { showTitleDisabled(); };
	if (sizeOfRqmts == 0) { showRqmtsError(); };
	if (enabledPatches == 0) { showPatchesError(); };
	toggleTitleBtn();
});

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
	toggleTitleBtn();
	ajaxPost('patchCtl.php?patch_id='+element.value, 'patch_enabled='+element.checked); 
}
</script>

<link rel="stylesheet" href="theme/datepicker.bootstrap.css" />

<script type="text/javascript" src="scripts/moment.js"></script>
<script type="text/javascript" src="scripts/transition.js"></script>
<script type="text/javascript" src="scripts/collapse.js"></script>
<script type="text/javascript" src="scripts/datepicker.bootstrap.min.js"></script>

<script type="text/javascript">
$(function () {
	$('#patch_datepicker').datetimepicker({
		//format: 'MMM D, YYYY \\at h:mm A'
		format: 'YYYY-MM-DDTHH:mm:ss\\Z'
	});
});
</script>

<script type="text/javascript" src="scripts/ace/ace.js"></script>

<link rel="stylesheet" href="theme/datatables.bootstrap.css" />

<script type="text/javascript" src="scripts/datatables.jquery.min.js"></script>
<script type="text/javascript" src="scripts/datatables.bootstrap.min.js"></script>
<script type="text/javascript" src="scripts/datatables.buttons.min.js"></script>
<script type="text/javascript" src="scripts/datatables.buttons.bootstrap.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
	$('#ext_attrs').DataTable( {
		buttons: [ {
			text: '<span class="glyphicon glyphicon-plus"></span> New',
				action: function ( e, dt, node, config ) {
                    $("#createEA").modal();
				}
			}
		],
		"dom": "<'row'<'col-sm-12'B>>" + "<'row'<'col-sm-12'tr>>",
		"order": [ 0, 'asc' ],
		"columns": [
			null,
			{ "orderable": false }
		]
	});
	$('#patches').DataTable( {
		buttons: [ {
			text: '<span class="glyphicon glyphicon-plus"></span> New',
				action: function ( e, dt, node, config ) {
                    $("#createPatch").modal();
				}
			}
		],
		"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'B>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
		"order": [ 1, 'asc' ],
		"lengthMenu": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
		"columns": [
			{ "orderable": false },
			null,
			null,
			null,
			null,
			{ "orderable": false },
			{ "orderable": false },
			{ "orderable": false }
		]
	});
} );
</script>

<?php if (!empty($title_id)) { ?>
<span class="description"><a href="patchTitles.php">Software Titles</a> <span class="glyphicon glyphicon-chevron-right"> </span></span>
<h2 id="heading"><?php echo $sw_title['name']; ?></h2>
<?php } ?>

<div class="row">
	<div class="col-xs-12 col-sm-12 col-lg-12">

		<?php if (!empty($title_id)) { ?>

		<form action="manageTitle.php?id=<?php echo $title_id; ?>" method="post" name="patchDefinition" id="patchDefinition">

			<ul class="nav nav-tabs nav-justified" id="top-tabs">
				<li class="active"><a id="title-tab-link" class="tab-font" href="#title-tab" role="tab" data-toggle="tab"><span id="title-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Software Title</a></li>
				<li><a id="ea-tab-link" class="tab-font" href="#ea-tab" role="tab" data-toggle="tab"> Extension Attributes</a></li>
				<li><a id="rqmts-tab-link" class="tab-font" href="#rqmts-tab" role="tab" data-toggle="tab"><span id="rqmts-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Requirements</a></li>
				<li><a id="patches-tab-link" class="tab-font" href="#patches-tab" role="tab" data-toggle="tab"><span id="patches-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Patches</a></li>
			</ul>

			<div class="tab-content">

				<div class="tab-pane active fade in" id="title-tab">

					<div style="padding: 8px 0px;" class="description">The information in the Software Title object must match the information in the Software Title Summary object that shares <span style="font-family:monospace;">id</span>.<br>None of the following values can be null. In addition, the <span style="font-family:monospace;">id</span> cannot include any special characters or spaces.</div>

					<div id="title-disabled-msg" style="padding-bottom: 8px;" class="hidden">
						<span><small>This patch defnition is disabled.</small></span>
						<br>
						<button id="enable_title" type="button" class="btn btn-sm btn-default" onClick="enableTitle();" disabled>Enable</button>
					</div>
					<div id="title-enabled-msg" style="padding-bottom: 8px;">
						<span><small>This patch defnition is enabled.</small></span>
					</div>

					<div class="row">
						<div class="col-xs-12 col-sm-12 col-lg-12">
							<label class="control-label">Name</label>
							<span class="description">Name of the patch management software title.</span>
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-8 col-md-5 col-lg-4">
							<input type="text" class="form-control input-sm" onFocus="validString(this, true);" onKeyUp="validString(this, true);" onChange="updateString(this, 'titles', 'name', <?php echo $title_id; ?>, true); updateTimestamp(<?php echo $title_id; ?>); document.getElementById('heading').innerHTML = this.value;" placeholder="[Required]" value="<?php echo $sw_title['name']; ?>" />
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-12 col-lg-12">
							<label class="control-label">Publisher</label>
							<span class="description">Publisher of the patch management software title.</span>
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-8 col-md-5 col-lg-4">
							<input type="text" class="form-control input-sm" onFocus="validString(this, true);" onKeyUp="validString(this, true);" onChange="updateString(this, 'titles', 'publisher', <?php echo $title_id; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo $sw_title['publisher']; ?>" />
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-12 col-lg-12">
							<label class="control-label">Application Name</label>
							<span class="description">Deprecated</span>
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-8 col-md-5 col-lg-4">
							<input type="text" class="form-control input-sm" onFocus="validOrEmptyString(this, true);" onKeyUp="validOrEmptyString(this, true);" onChange="updateOrEmptyString(this, 'titles', 'app_name', <?php echo $title_id; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Optional]" value="<?php echo $sw_title['app_name']; ?>" />
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-12 col-lg-12">
							<label class="control-label">Bundle Identifier</label>
							<span class="description">Deprecated</span>
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-8 col-md-5 col-lg-4">
							<input type="text" class="form-control input-sm" onFocus="validOrEmptyString(this, true);" onKeyUp="validOrEmptyString(this, true);" onChange="updateOrEmptyString(this, 'titles', 'bundle_id', <?php echo $title_id; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Optional]" value="<?php echo $sw_title['bundle_id']; ?>" />
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-12 col-lg-12">
							<label class="control-label">Current Version</label>
							<span class="description">Used for reporting the latest version of the patch management software title to Jamf Pro.</span>
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-8 col-md-5 col-lg-4">
							<input type="text" class="form-control input-sm" onFocus="validString(this, true);" onKeyUp="validString(this, true);" onChange="updateString(this, 'titles', 'current', <?php echo $title_id; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo $sw_title['current']; ?>" />
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-12 col-lg-12">
							<label class="control-label">ID</label>
							<span class="description">Uniquely identifies this software title on the external source.<br><strong>Note:</strong> An <span style="font-family:monospace;">id</span> cannot be duplicated on an individual external source.</span>
						</div><!-- /.col -->
						<div class="col-xs-12 col-sm-8 col-md-5 col-lg-4">
							<input type="text" class="form-control input-sm" onFocus="validNameId(this, true);" onKeyUp="validNameId(this, true);" onChange="updateNameId(this, 'titles', 'name_id', <?php echo $title_id; ?>, true); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo $sw_title['name_id']; ?>" />
						</div><!-- /.col -->
					</div><!-- /.row -->

					<br>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="ea-tab">

					<div style="padding-top: 8px;" class="description">Extension attributes that are required by Jamf Pro to use this software title. Terms must be accepted in Jamf Pro.</div>

					<table id="ext_attrs" class="table table-striped">
						<thead>
							<tr>
								<th><small>Name</small></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($ext_attrs as $ext_attr) { ?>
							<tr>
								<td>
									<input type="hidden" name="ea_key_id[<?php echo $ext_attr['id']; ?>]" value="<?php echo $ext_attr['key_id']; ?>" />
									<button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#editEa<?php echo $ext_attr['id']; ?>" data-backdrop="static" onClick="existingKeys.splice(existingKeys.indexOf('<?php echo $ext_attr['key_id']; ?>'), 1); eaNameValue = document.getElementById('ea_name[<?php echo $ext_attr['id']; ?>]').value; eaKeyValue = document.getElementById('ea_key_id[<?php echo $ext_attr['id']; ?>]').value; eaScriptValue = document.getElementById('ea_script[<?php echo $ext_attr['id']; ?>]').value;"><?php echo $ext_attr['name']; ?></button>
								</td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteEa<?php echo $ext_attr['id']; ?>">Delete</button></td>
							</tr>
							<?php } ?>
						</tobdy>
					</table>

				</div><!-- /.tab-pane -->

				<div class="modal fade" id="createEA" tabindex="-1" role="dialog">
					<div class="modal-dialog modal-lg" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h4 class="modal-title" id="modalLabel">New Extension Attribute</h4>
							</div>
							<div class="modal-body">

								<div class="row">
									<div class="col-xs-12 col-sm-12 col-lg-12">
										<label class="control-label">Display Name</label>
										<span class="description">Used on the Jamf Pro Patch Management &gt; Extension Attributes tab.</span>
									</div><!-- /.col -->
									<div class="col-xs-12 col-sm-8 col-md-6 col-lg-6">
										<span><input type="text" name="ea_name[0]" id="ea_name[0]" class="form-control input-sm" onKeyUp="validString(this);" onBlur="validString(this); validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');" placeholder="[Required]" /></span>
									</div><!-- /.col -->

									<div class="col-xs-12 col-sm-12 col-lg-12">
										<label class="control-label">Key</label>
										<span class="description">Identifier unique within Jamf Pro. It is used by criteria objects and displayed in the Jamf Pro computer inventory information.<br><strong>Note:</strong> Duplicate keys are not allowed.</span>
									</div><!-- /.col -->
									<div class="col-xs-12 col-sm-8 col-md-6 col-lg-6">
										<span><input type="text" name="ea_key_id[0]" id="ea_key_id[0]" class="form-control input-sm" onKeyUp="validEaKeyid(this);" onBlur="validEaKeyid(this); validEa('create_ea', 'ea_name[0]', 'ea_key_id[0]');" placeholder="[Required]" /></span>
									</div><!-- /.col -->
								</div><!-- /.row -->

								<label class="control-label">Script</label>
								<span class="description">Standard extension attribute script which must return a <span style="font-family:monospace;">&lt;result&gt;</span>.</span>
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

				<?php foreach ($ext_attrs as $ext_attr) { ?>
				<div class="modal fade" id="editEa<?php echo $ext_attr['id']; ?>" tabindex="-1" role="dialog">
					<div class="modal-dialog modal-lg" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h4 class="modal-title" id="modalLabel">Edit Extension Attribute</h4>
							</div>
							<div class="modal-body">

								<div class="row">
									<div class="col-xs-12 col-sm-12 col-lg-12">
										<label class="control-label">Display Name</label>
										<span class="description">Used on the Jamf Pro Patch Management &gt; Extension Attributes tab.</span>
									</div><!-- /.col -->
									<div class="col-xs-12 col-sm-8 col-md-6 col-lg-6">
										<span><input type="text" name="ea_name[<?php echo $ext_attr['id']; ?>]" id="ea_name[<?php echo $ext_attr['id']; ?>]" class="form-control input-sm" onOnFocus="validString(this);" onKeyUp="validString(this);" onBlur="validString(this); validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');" placeholder="[Required]" value="<?php echo $ext_attr['name']; ?>" /></span>
									</div><!-- /.col -->

									<div class="col-xs-12 col-sm-12 col-lg-12">
										<label class="control-label">Key</label>
										<span class="description">Identifier unique within Jamf Pro. It is used by criteria objects and displayed in the Jamf Pro computer inventory information.<br><strong>Note:</strong> Duplicate keys are not allowed.</span>
									</div><!-- /.col -->
									<div class="col-xs-12 col-sm-8 col-md-6 col-lg-6">
										<span><input type="text" name="ea_key_id[<?php echo $ext_attr['id']; ?>]" id="ea_key_id[<?php echo $ext_attr['id']; ?>]" class="form-control input-sm" onOnFocus="validEaKeyid(this);" onKeyUp="validEaKeyid(this);" onBlur="validEaKeyid(this); validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');" placeholder="[Required]" value="<?php echo $ext_attr['key_id']; ?>" /></span>
									</div><!-- /.col -->
								</div><!-- /.row -->

								<label class="control-label">Script</label>
								<span class="description">Standard extension attribute script which must return a <span style="font-family:monospace;">&lt;result&gt;</span>.</span>
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
								editor<?php echo $ext_attr['id']; ?>.getSession().on('change', function(e) {
									document.getElementById('ea_script[<?php echo $ext_attr['id']; ?>]').value = editor<?php echo $ext_attr['id']; ?>.getValue();
									setModeEditor<?php echo $ext_attr['id']; ?>();
								});
								$('#ea_script<?php echo $ext_attr['id']; ?>').focusin(function() {
									validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');
								});
								$('#ea_script<?php echo $ext_attr['id']; ?>').focusout(function() {
									validEa('save_ea[<?php echo $ext_attr['id']; ?>]', 'ea_name[<?php echo $ext_attr['id']; ?>]', 'ea_key_id[<?php echo $ext_attr['id']; ?>]');
								});
								</script>

							</div>
							<div class="modal-footer">
								<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" onClick="existingKeys.push('<?php echo $ext_attr['key_id']; ?>'); document.getElementById('ea_name[<?php echo $ext_attr['id']; ?>]').value = eaNameValue; document.getElementById('ea_key_id[<?php echo $ext_attr['id']; ?>]').value = eaKeyValue; hideError(document.getElementById('ea_key_id[<?php echo $ext_attr['id']; ?>]')); hideError(document.getElementById('ea_name[<?php echo $ext_attr['id']; ?>]'));">Cancel</button>
								<button type="submit" name="save_ea[<?php echo $ext_attr['id']; ?>]" id="save_ea[<?php echo $ext_attr['id']; ?>]" class="btn btn-primary btn-sm" value="<?php echo $ext_attr['id']; ?>" disabled >Save</button>
							</div>
						</div>
					</div>
				</div>

				<div class="modal fade" id="deleteEa<?php echo $ext_attr['id']; ?>" tabindex="-1" role="dialog">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h4 class="modal-title" id="modalLabel">Delete '<?php echo $ext_attr['name']; ?>'?</h4>
							</div>
							<div class="modal-body">
								<span class="description">This action is permanent and cannot be undone.</span>
							</div>
							<div class="modal-footer">
								<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left">Cancel</button>
								<button type="submit" name="delete_ea" id="delete_ea" class="btn btn-danger btn-sm pull-right" value="<?php echo $ext_attr['id']; ?>">Delete</button>
							</div>
						</div>
					</div>
				</div>
				<?php } ?>

				<div class="tab-pane fade in" id="rqmts-tab">

					<div style="padding: 8px 0px;" class="description">Criteria used to determine which computers in your environment have this software title installed.<br>The following values correspond with a row in a smart computer group or advanced search.<br><strong>Note:</strong> Criteria objects in an array must be ordered in the same way that smart group criteria is ordered.</div>

					<div id="rqmts-alert-msg" style="padding-bottom: 8px;" class="hidden">
						<span class="text-danger">
							<span class="glyphicon glyphicon-exclamation-sign"></span>
							<small>At least one requirement is required for the definition to be valid.</small>
						</span>
					</div>

					<table class="table table-striped">
						<thead>
							<tr>
								<th><small>Order</small></th>
								<th><small>Criteria</small></th>
								<th><small>Operator</small></th>
								<th><small>Value</small></th>
								<th><small>and/or</small></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($requirements as $requirement) { ?>
							<tr>
								<td><input type="text" size="3" name="rqmt_order[<?php echo $requirement['id']; ?>]" class="form-control input-sm" onKeyUp="validInteger(this);" onChange="updateInteger(this, 'requirements', 'sort_order', <?php echo $requirement['id']; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="[Required]" value="<?php echo $requirement['sort_order']; ?>" /></td>
								<td>
									<select class="form-control input-sm" onChange="updateCriteria(this, 'rqmt_operator[<?php echo $requirement['id']; ?>]', 'rqmt_type[<?php echo $requirement['id']; ?>]', 'requirements', <?php echo $requirement['id']; ?>); updateTimestamp(<?php echo $title_id; ?>);">
										<?php foreach ($ext_attrs as $ext_attr) { ?>
										<option value="<?php echo $ext_attr['key_id']; ?>"<?php echo ($requirement['name'] == $ext_attr['key_id'] ? " selected" : "") ?> ><?php echo $ext_attr['name']; ?></option>
										<?php } ?>
										<option value="Application Bundle ID"<?php echo ($requirement['name'] == "Application Bundle ID" ? " selected" : "") ?> >Application Bundle ID</option>
										<option value="Application Version"<?php echo ($requirement['name'] == "Application Version" ? " selected" : "") ?> >Application Version</option>
										<option value="Platform"<?php echo ($requirement['name'] == "Platform" ? " selected" : "") ?> >Platform</option>
										<option value="Operating System Version"<?php echo ($requirement['name'] == "Operating System Version" ? " selected" : "") ?> >Operating System Version</option>
									</select>
									<input type="hidden" id="rqmt_type[<?php echo $requirement['id']; ?>]" value="<?php echo $requirement['type']; ?>"/>
								</td>
								<td>
									<select id="rqmt_operator[<?php echo $requirement['id']; ?>]" class="form-control input-sm" onFocus="hideWarning(this);" onChange="updateString(this, 'requirements', 'operator', <?php echo $requirement['id']; ?>); updateTimestamp(<?php echo $title_id; ?>);" >
										<option value="is"<?php echo ($requirement['operator'] == "is" ? " selected" : "") ?> >is</option>
										<option value="is not"<?php echo ($requirement['operator'] == "is not" ? " selected" : "") ?> >is not</option>
										<option value="like"<?php echo ($requirement['operator'] == "like" ? " selected" : "") ?> >like</option>
										<option value="not like"<?php echo ($requirement['operator'] == "not like" ? " selected" : "") ?> >not like</option>
										<option value="greater than"<?php echo ($requirement['operator'] == "greater than" ? " selected" : "") ?><?php echo ($requirement['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than</option>
										<option value="less than"<?php echo ($requirement['operator'] == "less than" ? " selected" : "") ?><?php echo ($requirement['name'] != "Operating System Version" ? " disabled" : "") ?> >less than</option>
										<option value="greater than or equal"<?php echo ($requirement['operator'] == "greater than or equal" ? " selected" : "") ?><?php echo ($requirement['name'] != "Operating System Version" ? " disabled" : "") ?> >greater than or equal</option>
										<option value="less than or equal"<?php echo ($requirement['operator'] == "less than or equal" ? " selected" : "") ?><?php echo ($requirement['name'] != "Operating System Version" ? " disabled" : "") ?> >less than or equal</option>
									</select>
								</td>
								<td><input type="text" class="form-control input-sm" onKeyUp="validOrEmptyString(this);" onChange="updateOrEmptyString(this, 'requirements', 'value', <?php echo $requirement['id']; ?>); updateTimestamp(<?php echo $title_id; ?>);" placeholder="" value="<?php echo $requirement['value']; ?>" /></td>
								<td>
									<select class="form-control input-sm" onChange="updateInteger(this, 'requirements', 'is_and', <?php echo $requirement['id']; ?>); updateTimestamp(<?php echo $title_id; ?>);">
										<option value="1"<?php echo ($requirement['is_and'] == "1" ? " selected" : "") ?>>and</option>
										<option value="0"<?php echo ($requirement['is_and'] == "0" ? " selected" : "") ?>>or</option>
									</select>
								</td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deleteRqmt<?php echo $requirement['id']; ?>">Delete</button></td>
							</tr>
							<?php } ?>
							<tr>
								<td colspan="6" align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#createRequirement"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
							</tr>
						</tbody>
					</table>

					<div class="modal fade" id="createRequirement" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title" id="modalLabel">New Requirement</h4>
								</div>
								<div class="modal-body">

									<label class="control-label">Criteria</label>
									<span class="description">Any valid Jamf Pro smart group criteria.<br>When type is <span style="font-family:monospace;">extensionAttribute</span>, the name value is the key defined in the extensionAttribute object.</span>
									<span>
										<input type="hidden" name="rqmt_order[0]" id="rqmt_order[0]" value="<?php echo sizeof($requirements); ?>" />
										<select id="rqmt_name[0]" name="rqmt_name[0]" class="form-control input-sm" onChange="selectCriteria(this, 'rqmt_type[0]', 'rqmt_operator[0]'); validCriteria('create_rqmt', 'rqmt_order[0]', 'rqmt_name[0]', 'rqmt_operator[0]', 'rqmt_type[0]');" >
											<option value="" disabled selected>Select...</option>
											<?php foreach ($ext_attrs as $ext_attr) { ?>
											<option value="<?php echo $ext_attr['key_id']; ?>"><?php echo $ext_attr['name']; ?></option>
											<?php } ?>
											<option value="Application Bundle ID">Application Bundle ID</option>
											<option value="Application Version">Application Version</option>
											<option value="Platform">Platform</option>
											<option value="Operating System Version">Operating System Version</option>
										</select>
										<input type="hidden" name="rqmt_type[0]" id="rqmt_type[0]" value="recon" />
										<input type="hidden" name="rqmt_operator[0]" id="rqmt_operator[0]" value="is" />
									</span>

								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="create_rqmt" id="create_rqmt" class="btn btn-primary btn-sm" disabled >Save</button>
								</div>
							</div>
						</div>
					</div>

					<?php foreach ($requirements as $requirement) { ?>
					<div class="modal fade" id="deleteRqmt<?php echo $requirement['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title" id="modalLabel">Delete Requirement?</h4>
								</div>
								<div class="modal-body">
									<span class="description">This action is permanent and cannot be undone.</span>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_rqmt" id="delete_rqmt" class="btn btn-danger btn-sm" value="<?php echo $requirement['id']; ?>">Delete</button>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="patches-tab">

					<div style="padding: 8px 0px;" class="description">Software title version information; one patch is one software title version.<br><strong>Note:</strong> Must be listed in descending order with the newest version at the top of the list.</div>

					<div id="patches-alert-msg" style="padding-bottom: 8px;" class="hidden">
						<span class="text-danger">
							<span class="glyphicon glyphicon-exclamation-sign"></span>
							<small>At least one patch must be enabled for the definition to be valid.</small>
						</span>
					</div>

					<table id="patches" class="table table-striped">
						<thead>
							<tr>
								<th><small>Enable</small></th>
								<th><small>Order</small></th>
								<th><small>Version</small></th>
								<th><small><nobr>Release Date</nobr></small></th>
								<th><small><nobr>Minimum OS</nobr></small></th>
								<th><small><nobr>Stand Alone</nobr></small></th>
								<th><small>Reboot</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($patches as $patch) { ?>
							<tr>
								<td>
									<div class="checkbox checkbox-primary">
										<input type="checkbox" class="styled" name="enable_patch" id="enable_patch" value="<?php echo $patch['id']; ?>" onChange="togglePatch(this); updateTimestamp(<?php echo $title_id; ?>);" <?php echo (sizeof($patch['error']) > 0) ? "disabled " : ""; ?><?php echo ($patch['enabled'] == "1" && sizeof($patch['error']) == 0) ? "checked " : ""; ?>/>
										<label/>
									</div>
								</td>
								<td>
									<input type="hidden" name="patch_order[<?php echo $patch['id']; ?>]" value="<?php echo $patch['sort_order']; ?>"/>
									<small><?php echo $patch['sort_order']; ?></small>
								</td>
								<td><small><a href="managePatch.php?id=<?php echo $patch['id']; ?>"><?php echo $patch['version']; ?></a></small></td>
								<td><small><nobr><?php echo gmdate("Y-m-d\TH:i:s\Z", $patch['released']); ?></nobr></small></td>
								<td><small><?php echo $patch['min_os']; ?></small></td>
								<td><small><?php echo ($patch['standalone'] == "1" ? "Yes" : "No") ?></small></td>
								<td><small><?php echo ($patch['reboot'] == "1" ? "Yes" : "No") ?></small></td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#deletePatch<?php echo $patch['id']; ?>">Delete</button></td>
							</tr>
							<?php } ?>
						</tbody>
					</table>

					<div class="modal fade" id="createPatch" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title" id="modalLabel">New Patch</h4>
								</div>
								<div class="modal-body">

									<label class="control-label">Sort Order</label>
									<span><input type="text" name="patch_order[0]" id="patch_order[0]" class="form-control input-sm" onKeyUp="validInteger(this);" onBlur="validInteger(this); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" value="0" /></span>

									<label class="control-label">Version</label>
									<span class="description">Version associated with this patch.</span>
									<span><input type="text" name="patch_version[0]" id="patch_version[0]" class="form-control input-sm" onKeyUp="validString(this);" onBlur="validString(this); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" value="" /></span>

									<label class="control-label">Release Date</label>
									<span class="description">Date that this patch version was released.</span>
									<div class="input-group date" id="patch_datepicker">
										<input type="text" name="patch_released[0]" id="patch_released[0]" class="form-control input-sm" onBlur="validDate(this); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" />
										<span class="input-group-addon input-sm">
											<span class="glyphicon glyphicon-calendar"></span>
										</span>
									</div>

									<label class="control-label">Standalone</label>
									<span class="description"><span style="font-family:monospace;">true</span> specifies a patch that can be installed by itself. <span style="font-family:monospace;">false</span> specifies a patch that must be installed incrementally.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes.</span>
									<select id="patch_standalone[0]" name="patch_standalone[0]" class="form-control input-sm">
										<option value="1">Yes</option>
										<option value="0">No</option>
									</select>

									<label class="control-label">Minimum Operating System</label>
									<span class="description">Lowest macOS version capable of installing this patch.<br><strong>Note:</strong> Used for reporting purposes. It is not used by patch policy processes. See the capabilities array for patch policy implementation.</span>
									<span><input type="text" name="patch_min_os[0]" id="patch_min_os[0]" class="form-control input-sm" onKeyUp="validString(this);" onBlur="validString(this); validPatch('create_patch', 'patch_order[0]', 'patch_version[0]', 'patch_released[0]', 'patch_min_os[0]');" placeholder="[Required]" /></span>

									<label class="control-label">Reboot</label>
									<span class="description"><span style="font-family:monospace;">true</span> specifies that the computer must be restarted after the patch policy has completed successfully. <span style="font-family:monospace;">false</span> specifies that the computer will not be restarted.</span>
									<select id="patch_reboot[0]" name="patch_reboot[0]" class="form-control input-sm">
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

					<?php foreach ($patches as $patch) { ?>
					<div class="modal fade" id="deletePatch<?php echo $patch['id']; ?>" tabindex="-1" role="dialog">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title" id="modalLabel">Delete '<?php echo $patch['version']; ?>'?</h4>
								</div>
								<div class="modal-body">
									<span class="description">This action is permanent and cannot be undone.</span>
								</div>
								<div class="modal-footer">
									<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
									<button type="submit" name="delete_patch" id="delete_patch" class="btn btn-danger btn-sm pull-right" value="<?php echo $patch['id']; ?>">Delete</button>
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

		<input type="button" id="settings-button" name="action" class="btn btn-sm btn-default" value="Settings" onclick="document.location.href='dbSettings.php'">

	</div><!-- /.col -->
</div><!-- /.row -->

<?php } ?>

<?php include "inc/footer.php"; ?>