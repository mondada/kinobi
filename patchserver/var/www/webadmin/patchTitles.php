<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Patch Definitions";

// Check for subscription
if ($conf->getSetting("kinobi_url") != "" && $conf->getSetting("kinobi_token") != "") {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $conf->getSetting("kinobi_url"));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "token=".$conf->getSetting("kinobi_token"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close ($ch);
	$token = json_decode($result, true);
}

include "inc/header.php";
include "inc/dbConnect.php";

$sw_titles = array();
$error_msg = "";
$warning_msg = "";
$success_msg = "";

if (isset($token['import'])) {
	include $token['refresh'];
	include $token['import'];
}
if (isset($token['upload'])) {
	include $token['upload'];
}

if (isset($pdo)) {
	// Create Software Title
	if (isset($_POST['create_title'])) {
		$name = $_POST['name'];
		$publisher = $_POST['publisher'];
		$app_name = $_POST['app_name'];
		$bundle_id = $_POST['bundle_id'];
		$modified = time();
		$current = $_POST['current'];
		$name_id = $_POST['name_id'];
		$stmt = $pdo->prepare('INSERT INTO titles (name, publisher, app_name, bundle_id, modified, current, name_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$name, $publisher, $app_name, $bundle_id, $modified, $current, $name_id]);
		if ($stmt->errorCode() == '00000') {
			$success_msg = "Created Software Title '<a href=\"manageTitle.php?id=".$pdo->lastInsertId()."\">".$name."</a>'.";
		} else {
			$error_msg = $stmt->errorInfo()[2];
		}
	}

	// Delete Software Title
	if (isset($_POST['delete_title'])) {
		$title_id = $_POST['delete_title'];
		$title_name = $_POST['delete_title_name'];
		$stmt = $pdo->prepare('DELETE FROM titles WHERE id = ?');
		$stmt->execute([$title_id]);
		if ($stmt->errorCode() == '00000') {
			$success_msg = "Deleted Software Title '".$title_name."'.";
		} else {
			$error_msg = $stmt->errorInfo()[2];
		}
	}

	// ####################################################################
	// End of GET/POST parsing
	// ####################################################################

	// Remove Expired Subscription
	if (isset($token) && $token['timestamp'] - (14*24*60*60) >= $token['expires'] || $conf->getSetting("kinobi_url") == "" && $conf->getSetting("kinobi_token") == "") {
		$removed = $pdo->exec('DELETE FROM titles WHERE source_id = "1"');
		if (!empty($removed)) {
			$warning_msg = "Titles imported from Kinobi have been removed.";
		}
	}

	// Software Title Summary
	$stmt = $pdo->query('SELECT id, name_id, name, publisher, current, modified, enabled, source_id FROM titles ORDER BY publisher, name');
	while ($sw_title = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$sw_title['enabled'] = ($sw_title['enabled'] == "1") ? "1" : "0";
		$sw_title['error'] = array();
		$sw_title['requirements'] = $pdo->query('SELECT id FROM requirements WHERE title_id = "'.$sw_title['id'].'"')->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($sw_title['requirements']) == 0) {
			array_push($sw_title['error'], "requirements");
		}
		$sw_title['patches'] = $pdo->query('SELECT id FROM patches WHERE title_id = "'.$sw_title['id'].'" AND enabled = 1')->fetchAll(PDO::FETCH_COLUMN);
		if (sizeof($sw_title['patches']) == 0) {
			array_push($sw_title['error'], "patches");
		}
		if (sizeof($sw_title['error']) > 0 && $sw_title['enabled'] == "1") {
			$sw_title['enabled'] == "0";
			$disable = $pdo->prepare('UPDATE titles SET enabled = 0 WHERE id = ?');
			$disable->execute([$sw_title['id']]);
			if ($disable->errorCode() == '00000') {
				$warning_msg = "Software Title '".$sw_title['name']."' has been disabled.";
			} else {
				$error_msg = $disable->errorInfo()[2];
			}
		}
		array_push($sw_titles, $sw_title);
	}
}
?>
			<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css"/>
			<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />

			<style>
				.btn-table {
					width: 75px;
				}
				.checkbox-error {
					font-size: 17px;
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
					#nav-title {
						left: 220px;
					}
				}
			</style>

			<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
			<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

			<script type="text/javascript">
				var existingIds = [<?php echo "\"".implode('", "', array_map(function($el){ return $el['name_id']; }, $sw_titles))."\""; ?>];
			</script>

			<script type="text/javascript" src="scripts/patchValidation.js"></script>

			<script type="text/javascript">
				$(document).ready(function() {
					$('#sw_titles').DataTable( {
						buttons: [
<?php if (isset($token['import']) || isset($token['upload'])) { ?>
							{
								extend: 'collection',
								text: '<span class="glyphicon glyphicon-share-alt"></span> Import</span>',
								className: 'btn-primary btn-sm btn-table',
								buttons: [
<?php if (isset($token['import'])) { ?>
									{
										text: 'From Kinobi',
										action: function ( e, dt, node, config ) {
											$("#import_title-modal").modal();
										}
									},
<?php }
if (isset($token['upload'])) { ?>
									{
										text: 'Upload JSON',
										action: function ( e, dt, node, config ) {
											$("#upload_json-modal").modal();
										}
									},
<?php } ?>
								]
							},
<?php } ?>
							{
								text: '<span class="glyphicon glyphicon-plus"></span> New',
								className: 'btn-primary btn-sm btn-table',
								action: function ( e, dt, node, config ) {
									$("#create_title-modal").modal();
								}
							}
						],
						"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'<'dataTables_paginate'B>>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
						"order": [ 1, 'asc' ],
						"lengthMenu": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
						"columns": [
							{ "orderable": false },
							null,
							null,
							null,
							null,
							{ "orderable": false }
						]
					});
				} );
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					$('#settings').attr('onclick', 'document.location.href="patchSettings.php"');
				});
			</script>

			<nav id="nav-title" class="navbar navbar-default navbar-fixed-top">
				<div style="padding: 19px 20px 1px;">
					<div class="description">&nbsp;</div>
					<div class="row">
						<div class="col-xs-10">
							<h2>Patch Definitions</h2>
						</div>
						<div class="col-xs-2 text-right">
							<!-- <button type="button" class="btn btn-default btn-sm" >Settings</button> -->
						</div>
					</div>
				</div>
			</nav>

<?php if (isset($pdo)) { ?>
			<form action="patchTitles.php" method="post" name="patchTitle" id="patchTitle">

				<div style="padding: 79px 20px 1px; background-color: #f9f9f9; overflow-x: auto;">
<?php if ($conf->getSetting("kinobi_url") != "" || $conf->getSetting("kinobi_token") != "") {
	if (empty($token['expires'])) { ?>
					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
						<div class="panel-body">
							<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>Invalid token. Please ensure the Server URL and Token values are entered exactly as they were provided.</div>
						</div>
					</div>
<?php } elseif ($token['expires'] < $token['timestamp']) { ?>
					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
						<div class="panel-body">
							<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $token['type']; ?> subscription expired: <?php echo date('M j, Y', $token['expires']); ?>. <a target="_blank" href="<?php echo $token['renew']; ?>">Click here to renew</a>.</div>
						</div>
					</div>
<?php if ($token['expires'] > $token['timestamp'] - (14*24*60*60)) { ?>
					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning">
						<div class="panel-body">
							<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>Definitions imported from Kinobi will be removed in <?php echo (14 + ($token['expires'] - $token['timestamp']) / (24*60*60)); ?> days.</div>
						</div>
					</div>
<?php }
} elseif ($token['expires'] < $token['timestamp'] + (14*24*60*60)) { ?>
					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning">
						<div class="panel-body">
							<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $token['type']; ?> subscription expires: <?php echo date('M j, Y', $token['expires']); ?>. <a target="_blank" href="<?php echo $token['renew']; ?>">Click here to renew</a>.</div>
						</div>
					</div>
<?php }
} ?>
					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (!empty($error_msg) ? "" : "hidden"); ?>">
						<div class="panel-body">
							<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $error_msg; ?></div>
						</div>
					</div>

					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning <?php echo (!empty($warning_msg) ? "" : "hidden"); ?>">
						<div class="panel-body">
							<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $warning_msg; ?></div>
						</div>
					</div>

					<div style="margin-top: 0px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (!empty($success_msg) ? "" : "hidden"); ?>">
						<div class="panel-body">
							<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $success_msg; ?></div>
						</div>
					</div>

					<!-- <div style="margin-top: 0px; margin-bottom: 16px;" class="panel panel-info">
						<div class="panel-body">
							<div class="text-muted"><span class="text-info glyphicon glyphicon-info-sign" style="padding-right: 12px;"></span><?php  ?></div>
						</div>
					</div> -->

					<table id="sw_titles" class="table table-hover" style="border-bottom: 1px solid #eee;">
						<thead>
							<tr>
								<th>Enable</th>
								<th>Name</th>
								<th>Publisher</th>
								<th>Last Modified</th>
								<th>Current Version</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
<?php foreach ($sw_titles as $sw_title) { ?>
							<tr>
								<td>
<?php if (sizeof($sw_title['error']) == 0) { ?>
									<div class="checkbox checkbox-primary checkbox-inline">
										<input type="checkbox" class="styled" name="enable_title" id="enable_title" value="<?php echo $sw_title['id']; ?>" onChange="javascript:ajaxPost('patchCtl.php?title_id='+this.value, 'title_enabled='+this.checked);" <?php echo ($sw_title['enabled'] == "1") ? "checked " : ""; ?>/>
										<label/>
									</div>
<?php } else { ?>
									<div style="checkbox checkbox-danger checkbox-inline">
										<a href="manageTitle.php?id=<?php echo $sw_title['id']; ?>"><span class="text-danger glyphicon glyphicon-exclamation-sign checkbox-error"></span></a>
									</div>
<?php } ?>
								</td>
<?php if ($sw_title['source_id'] == 0) { ?>
								<td><a href="manageTitle.php?id=<?php echo $sw_title['id']; ?>"><?php echo $sw_title['name']; ?></a></td>
<?php } else { ?>
								<td><?php echo $sw_title['name']; ?></td>
<?php } ?>
								<td><?php echo $sw_title['publisher']; ?></td>
								<td><?php echo gmdate("Y-m-d\TH:i:s\Z", $sw_title['modified']); ?></td>
								<td><?php echo $sw_title['current']; ?></td>
<?php if ($sw_title['source_id'] == 0 || isset($token) && $token['expires'] < $token['timestamp']) { ?>
								<td align="right"><button type="button" class="btn btn-default btn-sm" style="width: 65px;" data-toggle="modal" data-target="#delete_title-modal" onClick="$('#delete_title-title').text('<?php echo $sw_title['name']; ?>'); $('#delete_title_name').val('<?php echo $sw_title['name']; ?>'); $('#delete_title').val('<?php echo $sw_title['id']; ?>');">Delete</button></td>
<?php } else { ?>
								<td align="right"><button type="button" class="btn btn-default btn-sm" style="width: 65px;" data-toggle="modal" data-target="#remove_title-modal" onClick="$('#remove_title-title').text('<?php echo $sw_title['name']; ?>'); $('#remove_title_name').val('<?php echo $sw_title['name']; ?>'); $('#remove_title').val('<?php echo $sw_title['id']; ?>');">Remove</button></td>
<?php } ?>

							</tr>
<?php } ?>
						</tobdy>
					</table>
				</div>

				<hr>

				<!-- New Title Modal -->
				<div class="modal fade" id="create_title-modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title">New Software Title</h3>
							</div>
							<div class="modal-body">

								<h5 id="name_label"><strong>Name</strong> <small>Name of the patch management software title.</small></h5>
								<div class="form-group">
									<input type="text" name="name" id="name" class="form-control input-sm" onKeyUp="validString(this, 'name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validString(this, 'name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
								</div>

								<h5 id="publisher_label"><strong>Publisher</strong> <small>Publisher of the patch management software title.</small></h5>
								<div class="form-group">
									<input type="text" name="publisher" id="publisher" class="form-control input-sm" onKeyUp="validString(this, 'publisher_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validString(this, 'publisher_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
								</div>

								<h5 id="app_name_label"><strong>Application Name</strong> <small>Deprecated.</small></h5>
								<div class="form-group">
									<input type="text" name="app_name" id="app_name" class="form-control input-sm" onKeyUp="validOrEmptyString(this, 'app_name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validOrEmptyString(this, 'app_name_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Optional]" />
								</div>

								<h5 id="bundle_id_label"><strong>Bundle Identifier</strong> <small>Deprecated.</small></h5>
								<div class="form-group">
									<input type="text" name="bundle_id" id="bundle_id" class="form-control input-sm" onKeyUp="validOrEmptyString(this, 'bundle_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validOrEmptyString(this, 'bundle_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Optional]" />
								</div>

								<h5 id="current_label"><strong>Current Version</strong> <small>Used for reporting the latest version of the patch management software title to Jamf Pro.</small></h5>
								<div class="form-group">
									<input type="text" name="current" id="current" class="form-control input-sm" onKeyUp="validString(this, 'current_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validString(this, 'current_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
								</div>

								<h5 id="name_id_label"><strong>ID</strong> <small>Uniquely identifies this software title on this external source.<br><strong>Note:</strong> The <span style="font-family:monospace;">id</span> cannot include any special characters or spaces.</small></h5>
								<div class="form-group">
									<input type="text" name="name_id" id="name_id" class="form-control input-sm" onKeyUp="validNameId(this, 'name_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" onBlur="validNameId(this, 'name_id_label'); validTitle('create_title', 'name', 'publisher', 'app_name', 'bundle_id', 'current', 'name_id');" placeholder="[Required]" />
								</div>

							</div>
							<div class="modal-footer">
								<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
								<button type="submit" name="create_title" id="create_title" class="btn btn-primary btn-sm" disabled >Save</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->

				<!-- Delete Title Modal -->
				<div class="modal fade" id="delete_title-modal" tabindex="-1" role="dialog">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h3 class="modal-title">Delete <span id="delete_title-title">Title</span>?</h3>
							</div>
							<div class="modal-body">
								<div class="text-muted">This action is permanent and cannot be undone.</div>
								<input type="hidden" id="delete_title_name" name="delete_title_name" value=""/>
							</div>
							<div class="modal-footer">
								<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
								<button type="submit" name="delete_title" id="delete_title" class="btn btn-danger btn-sm pull-right" value="">Delete</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /.modal -->

			</form> <!-- end form patchTitle -->
<?php } else { ?>
			<div style="padding: 79px 20px 1px; background-color: #f9f9f9;">
				<div style="margin-top: 0px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
					<div class="panel-body">
						<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $pdo_error; ?></div>
					</div>
				</div>
			</div>

			<hr>
<?php }
include "inc/footer.php"; ?>