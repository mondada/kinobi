<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Patch Definitions";

include "inc/header.php";

$backup_error = "";
$backup_success = "";
$upload_error = "";
$upload_success = "";
$restore_error = "";
$restore_success = "";

function patchExec($cmd) {
	return shell_exec("sudo /bin/sh scripts/patchHelper.sh ".escapeshellcmd($cmd)." 2>&1");
}

// Get Retention
$retention = $conf->getSetting("retention");
if ($retention == "") {
	$retention = 7;
	$conf->setSetting("retention", $retention);
}

// Backup
if (isset($_POST["backup"])) {
	if (trim(patchExec("backupDB")) == "true") {
		$backup_success = "Backup completed successfully.";
	} else {
		$backup_error = "Backup failed.";
	}
}

// Upload
if (isset($_POST["upload"]) && isset($_FILES["upload_file"]["name"])) {
	if ($_FILES["upload_file"]["error"] > 0) {
		$upload_error = $_FILES["upload_file"]["error"].".";
	} elseif ($_FILES["upload_file"]["type"] != "application/x-gzip") {
		$upload_error = "Invalid file type '".$_FILES["upload_file"]["type"]."'.";
	} else {
		// To Do: Add string replace to remove spaces in filename
		$filename = basename($_FILES["upload_file"]["name"]);
		move_uploaded_file($_FILES["upload_file"]["tmp_name"], '/tmp/'.$filename);
		patchExec("uploadDB ".$filename);
		$upload_success = "File uploaded successfully.";
	}
}

// Restore
if (isset($_POST["restore"])) {
	if (trim(patchExec("restoreDB ".$_POST["restore"])) == "true") {
		$restore_success = "Restored '".basename($_POST["restore"])."'.";
	} else {
		$restore_error = "Restore failed.";
	}
}

// Subscription
if (isset($_POST["subscribe"])) {
	if ($_POST["kinobi_url"] == "") {
		$conf->deleteSetting("kinobi_url");
	} else {
		$conf->setSetting("kinobi_url", $_POST['kinobi_url']);
	}
	if ($_POST["kinobi_token"] == "") {
		$conf->deleteSetting("kinobi_token");
	} else {
		$conf->setSetting("kinobi_token", $_POST['kinobi_token']);
	}
}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

// List Backups
$backup_list = trim(patchExec("listBackups"));
if ($backup_list != "") {
	$backups = explode("\n", $backup_list);
} else {
	$backups = array();
}

// Get Schedule
$schedule_str = trim(patchExec("getSchedule"));
if ($schedule_str != "") {
	$scheduled = explode(",", $schedule_str);
} else {
	$scheduled = array();
}

// Subscription Status
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
?>

			<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css"/>
			<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />
			<link rel="stylesheet" href="theme/bootstrap-toggle.css">

			<style>
				#tab-content {
					margin-top: 255px;
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
					#nav-title {
						left: 220px;
					}
					#tab-content {
						margin-top: 165px;
					}
				}
			</style>

			<script type="text/javascript" src="scripts/toggle/bootstrap-toggle.min.js"></script>

			<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
			<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
			<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

			<script type="text/javascript">
				var scheduled = [<?php echo (sizeof($scheduled) > 0 ? "\"".implode('", "', $scheduled)."\"" : ""); ?>];
			</script>

			<script type="text/javascript" src="scripts/patchValidation.js"></script>

			<script type="text/javascript">
				function updateSchedule(element) {
					if (scheduled.indexOf(element.value) >= 0) {
						scheduled.splice(scheduled.indexOf(element.value), 1);
					}
					if (element.checked) {
						scheduled.push(element.value);
					}
					scheduled.sort();
					ajaxPost('patchCtl.php', 'schedule='+scheduled.join());
					if (scheduled.length == 0) {
						showScheduleError();
					} else {
						hideScheduleError();
					}
				}

				function validRetention(element, labelId = false) {
					hideSuccess(element);
					if (element.value == parseInt(element.value) && element.value > 0  && element.value < 31) {
						hideError(element, labelId);
					} else {
						showError(element, labelId);
					}
				}

				function updateRetention(element, offset = false) {
					if (element.value == parseInt(element.value) && element.value > 0  && element.value < 31) {
						ajaxPost("patchCtl.php", "retention="+element.value);
						showSuccess(element, offset);
					}
				}

				function showScheduleError() {
					$('#schedule-tab-icon').removeClass('hidden');
					$('#schedule-alert-msg').removeClass('hidden');
				}

				function hideScheduleError() {
					$('#schedule-tab-icon').addClass('hidden');
					$('#schedule-alert-msg').addClass('hidden');
				}

				function toggleService() {
					if ($('#patchenabled').prop('checked')) {
						$('#patch').removeClass('hidden');
						$('#backup').prop('disabled', false);
						$('[name="schedule"]').prop('disabled', false);
						if (scheduled.length == 0) {
							showScheduleError();
						}
						$('#retention').prop('disabled', false);
						$('[name="restorepromt"]').prop('disabled', false);
						ajaxPost('patchCtl.php', 'service=enable');
					} else {
						$('#patch').addClass('hidden');
						$('#backup').prop('disabled', true);
						hideScheduleError();
						$('[name="schedule"]').prop('disabled', true);
						$('[name="schedule"]').prop('checked', false);
						scheduled = [];
						ajaxPost('patchCtl.php', 'schedule=');
						$('#retention').prop('disabled', true);
						$('[name="restorepromt"]').prop('disabled', true);
						ajaxPost('patchCtl.php', 'service=disable');
					}
				}

				function toggleDashboard() {
					if ($('#dashboard').prop('checked')) {
						ajaxPost('patchCtl.php', 'dashboard=true');
					} else {
						ajaxPost('patchCtl.php', 'dashboard=false');
					}
				}

				function validSubscribe() {
					var kinobi_url = document.getElementById('kinobi_url');
					var kinobi_token = document.getElementById('kinobi_token');
					if (/^.{1,255}$/.test(kinobi_url.value)) {
						hideError(kinobi_url, 'kinobi_url_label');
					} else {
						showError(kinobi_url, 'kinobi_url_label');
					}
					if (/^.{1,255}$/.test(kinobi_token.value)) {
						hideError(kinobi_token, 'kinobi_token_label');
					} else {
						showError(kinobi_token, 'kinobi_token_label');
					}
					if (/^.{1,255}$/.test(kinobi_url.value) && /^.{1,255}$/.test(kinobi_token.value) || kinobi_url.value == "" && kinobi_token.value == "") {
						hideError(kinobi_url, 'kinobi_url_label');
						hideError(kinobi_token, 'kinobi_token_label');
						$('#subscribe').prop('disabled', false);
					} else {
						$('#subscribe').prop('disabled', true);
					}
				}
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					if ($('#patchenabled').prop('checked') && scheduled.length == 0) {
						showScheduleError();
					}
				});
			</script>

			<script type="text/javascript">
				$(document).ready(function() {
					$('#backups').DataTable( {
						buttons: [
							{
								text: '<span class="glyphicon glyphicon-plus"></span> Upload',
								className: 'btn-primary btn-sm',
								action: function ( e, dt, node, config ) {
									$("#uploadBackup").modal();
								}
							}
						],
						"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'<'dataTables_paginate'B>>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
						"order": [ 1, 'desc' ],
						"lengthMenu": [ [5, 10, 25, -1], [5, 10, 25, "All"] ],
						"pageLength": 5,
						"columns": [
							null,
							null,
							{ "orderable": false }
						]
					});
				} );
			</script>

			<script type="text/javascript">
				//function to save the current tab on refresh
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
				$(document).ready(function(){
					toggleService();
				});
			</script>

			<nav id="nav-title" class="navbar navbar-default navbar-fixed-top">
				<div style="padding: 19px 20px 1px;">
					<div class="description"><a href="settings.php">Settings</a> <span class="glyphicon glyphicon-chevron-right"></span> <span class="text-muted">Services</span> <span class="glyphicon glyphicon-chevron-right"></span></div>
					<div class="row">
						<div class="col-xs-10">
							<h2>Patch Definitions</h2>
						</div>
						<div class="col-xs-2 text-right">
							<input type="checkbox" id="patchenabled" data-toggle="toggle" data-size="small" onChange="toggleService();" <?php echo ($conf->getSetting("patch") == "enabled" ? "checked" : ""); ?>>
						</div>
					</div>
				</div>
				<div style="padding: 6px 20px 0px; background-color: #f9f9f9; border-bottom: 1px solid #ddd;">
					<div class="checkbox checkbox-primary">
						<input name="dashboard" id="dashboard" class="styled" type="checkbox" value="true" onChange="toggleDashboard();" <?php echo ($conf->getSetting("showpatch") == "false" ? "" : "checked"); ?>>
						<label><strong>Show in Dashboard</strong><br><span style="font-size: 75%; color: #777;">Display service status in the NetSUS dashboard.</span></label>
					</div>
					<ul class="nav nav-tabs nav-justified" id="top-tabs" style="margin-bottom: -1px;">
						<li class="active"><a class="tab-font" href="#backup-tab" role="tab" data-toggle="tab"><span id="schedule-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Backup</a></li>
						<li><a class="tab-font" href="#restore-tab" role="tab" data-toggle="tab">Restore</a></li>
						<li><a class="tab-font" href="#subscription-tab" role="tab" data-toggle="tab"><span id="subscription-tab-icon" class="glyphicon glyphicon-exclamation-sign <?php echo (!isset($token) || $token['expires'] > $token['timestamp'] + (14*24*60*60) ? "hidden" : ""); ?>"></span> Subscription</a></li>
					</ul>
				</div>
			</nav>

			<form action="patchSettings.php" method="post" name="Database" id="Database" enctype="multipart/form-data">

				<div id="tab-content" class="tab-content">

					<div class="tab-pane active fade in" id="backup-tab">

						<div style="padding: 9px 20px 16px;">
							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($backup_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $backup_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($backup_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $backup_success; ?></div>
								</div>
							</div>

							<h5><strong>Manual Backup</strong> <small>Perform a manual backup of the database.</small></h5>
							<button type="submit" name="backup" id="backup" class="btn btn-primary btn-sm" style="width: 90px;" value="backup">Backup</button>
						</div>

						<hr>

						<div style="padding: 9px 20px 16px; background-color: #f9f9f9;">
							<div id="schedule-alert-msg" style="margin-top: 7px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning hidden">
								<div class="panel-body">
									<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>No backups scheduled.</div>
								</div>
							</div>

							<h5><strong>Backup Schedule</strong> <small>Days of the week for an automatic backup to run.<br><strong>Note:</strong> Backups will occur at 12:00 AM (this server's local time) on the specified days.</small></h5>

							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[0]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="0" <?php echo (in_array(0, $scheduled) ? "checked" : ""); ?>>
								<label for="sun"> Sun </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[1]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="1" <?php echo (in_array(1, $scheduled) ? "checked" : ""); ?>>
								<label for="mon"> Mon </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[2]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="2" <?php echo (in_array(2, $scheduled) ? "checked" : ""); ?>>
								<label for="tue"> Tue </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[3]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="3" <?php echo (in_array(3, $scheduled) ? "checked" : ""); ?>>
								<label for="wed"> Wed </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[4]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="4" <?php echo (in_array(4, $scheduled) ? "checked" : ""); ?>>
								<label for="thu"> Thu </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[5]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="5" <?php echo (in_array(5, $scheduled) ? "checked" : ""); ?>>
								<label for="fri"> Fri </label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input name="schedule" id="schedule[6]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="6" <?php echo (in_array(6, $scheduled) ? "checked" : ""); ?>>
								<label for="sat"> Sat </label>
							</div>
						</div>

						<hr>

						<div style="padding: 9px 20px 16px;">
							<h5 id="retention_label"><strong>Backup Retention</strong> <small>Number of backup archives to be retained on the server.</small></h5>
							<div class="form-group has-feedback" style="width: 90px;">
								<input type="text" id="retention" class="form-control input-sm" onFocus="validRetention(this, 'retention_label');" onKeyUp="validRetention(this, 'retention_label');" onChange="updateRetention(this);" placeholder="[1 - 30]" value="<?php echo $retention; ?>" />
							</div>
						</div>

					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="restore-tab">

						<div style="padding: 9px 20px 1px;">
							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($upload_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $upload_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($upload_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $upload_success; ?></div>
								</div>
							</div>

							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger <?php echo (empty($restore_error) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $restore_error; ?></div>
								</div>
							</div>

							<div style="margin-top: 7px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success <?php echo (empty($restore_success) ? "hidden" : ""); ?>">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $restore_success; ?></div>
								</div>
							</div>

							<h5><strong>Available Backups</strong> <small>Click the backup filename to download a backup archive.<br>Backup archives are saved in <span style="font-family:monospace;">/var/appliance/backup</span> on this server.</small></h5>
						</div>

						<div style="padding: 9px 20px 1px; overflow-x: auto;">
							<table id="backups" class="table table-hover" style="border-bottom: 1px solid #eee;">
								<thead>
									<tr>
										<th>Filename</th>
										<th>Date</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
<?php $i = 0;
foreach ($backups as $backup) { ?>
									<tr>
										<td><a href="patchCtl.php?download=<?php echo basename($backup); ?>"><?php echo basename($backup); ?></a></td>
										<td><?php echo gmdate("Y-m-d\TH:i:s\Z", stat($backup)["mtime"]); ?></td>
										<td align="right"><button type="button" name="restorepromt" class="btn btn-default btn-sm" data-toggle="modal" data-target="#restore_<?php echo $i; ?>">Restore</button></td>
									</tr>
<?php $i++;
} ?>
								</tobdy>
							</table>
						</div>

<?php $i = 0;
foreach ($backups as $backup) { ?>
						<!-- Restore Modal -->
						<div class="modal fade" id="restore_<?php echo $i; ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h4 class="modal-title" id="modalLabel">Restore Database</h4>
									</div>
									<div class="modal-body">
										<div class="text-muted">Are you sure you want to restore the database:<br> '<?php echo basename($backup); ?>' ?</div>
									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="restore" id="restore" class="btn btn-primary btn-sm pull-right" value="<?php echo $backup; ?>">Restore</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->
<?php $i++;
} ?>
						<!-- Upload Modal -->
						<div class="modal fade" id="uploadBackup" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h4 class="modal-title" id="modalLabel">Upload Backup</h4>
									</div>
									<div class="modal-body">

										<h5>Archive <small>Upload a backup archive file (gzipped SQLite database) to add to the list of available backups.</small></h5>
										<input type="file" name="upload_file" id="upload_file" class="form-control input-sm" onChange="document.getElementById('upload').disabled = this.value == '';" >

									</div>
									<div class="modal-footer">
										<button type="button" data-dismiss="modal" class="btn btn-default btn-sm pull-left" >Cancel</button>
										<button type="submit" name="upload" id="upload" class="btn btn-primary btn-sm" disabled >Upload</button>
									</div>
								</div>
							</div>
						</div>
						<!-- /.modal -->

					</div> <!-- /.tab-pane -->

					<div class="tab-pane fade in" id="subscription-tab">

						<div style="padding: 9px 20px 16px;">
<?php if ($conf->getSetting("kinobi_url") == "" && $conf->getSetting("kinobi_token") == "") { ?>
							<div style="margin-top: 11px; margin-bottom: 16px;" class="panel panel-primary">
								<div class="panel-body">
									<div class="text-muted"><span class="text-info glyphicon glyphicon-info-sign" style="padding-right: 12px;"></span>Register for a <a target="_blank" href="https://kinobi.io/kinobi/">Kinobi subscription</a> to provide patch definitions.</div>
								</div>
							</div>
<?php } elseif (empty($token['expires'])) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span>Invalid token. Please ensure the Server URL and Token values are entered exactly as they were provided.</div>
								</div>
							</div>
<?php } elseif ($token['expires'] > $token['timestamp'] + (14*24*60*60)) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #4cae4c;" class="panel panel-success">
								<div class="panel-body">
									<div class="text-muted"><span class="text-success glyphicon glyphicon-ok-sign" style="padding-right: 12px;"></span><?php echo $token['type']; ?> subscription expires: <?php echo date('M j, Y', $token['expires']); ?>.</div>
								</div>
							</div>
<?php } elseif ($token['expires'] > $token['timestamp']) { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #eea236;" class="panel panel-warning">
								<div class="panel-body">
									<div class="text-muted"><span class="text-warning glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $token['type']; ?> subscription expires: <?php echo date('M j, Y', $token['expires']); ?>. <a target="_blank" href="<?php echo $token['renew']; ?>">Click here to renew</a>.</div>
								</div>
							</div>
<?php } else { ?>
							<div style="margin-top: 11px; margin-bottom: 16px; border-color: #d43f3a;" class="panel panel-danger">
								<div class="panel-body">
									<div class="text-muted"><span class="text-danger glyphicon glyphicon-exclamation-sign" style="padding-right: 12px;"></span><?php echo $token['type']; ?> subscription expired: <?php echo date('M j, Y', $token['expires']); ?>. <a target="_blank" href="<?php echo $token['renew']; ?>">Click here to renew</a>.</div>
								</div>
							</div>
<?php } ?>

							<!-- <div style="margin-top: 0px; margin-bottom: 16px;" class="panel panel-info">
								<div class="panel-body">
									<div class="text-muted"><span class="text-info glyphicon glyphicon-info-sign" style="padding-right: 12px;"></span><?php print_r($token); ?></div>
								</div>
							</div> -->

							<h5 id="kinobi_url_label"><strong>Server URL</strong> <small>URL for the subscription server.</small></h5>
							<div class="form-group has-feedback">
								<input type="text" name="kinobi_url" id="kinobi_url" class="form-control input-sm" onFocus="validSubscribe();" onKeyUp="validSubscribe();" onBlur="validSubscribe();" placeholder="[Required]" value="<?php echo $conf->getSetting("kinobi_url"); ?>"/>
							</div>

							<h5 id="kinobi_token_label"><strong>Token</strong> <small>Auth token for the subscription server.</small></h5>
							<div class="form-group has-feedback">
								<input type="text" name="kinobi_token" id="kinobi_token" class="form-control input-sm" onFocus="validSubscribe();" onKeyUp="validSubscribe();" onBlur="validSubscribe();" placeholder="[Required]" value="<?php echo $conf->getSetting("kinobi_token"); ?>"/>
							</div>

							<div class="text-right">
								<button type="submit" name="subscribe" id="subscribe" class="btn btn-primary btn-sm" disabled>Apply</button>
							</div>
						</div>

					</div> <!-- /.tab-pane -->

				</div> <!-- end .tab-content -->

			</form> <!-- end Database form -->
<?php include "inc/footer.php"; ?>