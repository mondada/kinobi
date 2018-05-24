<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Patch";

include "inc/header.php";

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
		$status_msg = "<div class=\"text-success\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-ok-sign\"></span> Backup completed successfully.</div>";
	} else {
		$status_msg = "<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> Backup failed.</div>";
	}
}

// Upload
if (isset($_POST["upload"]) && isset($_FILES["upload_file"]["name"])) {
	if ($_FILES["upload_file"]["error"] > 0) {
		$status_msg = "<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> ".$_FILES["upload_file"]["error"].".</div>";
	} elseif ($_FILES["upload_file"]["type"] != "application/x-gzip") {
		$status_msg = "<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> Invalid file type '".$_FILES["upload_file"]["type"]."'.</div>";
	} else {
		// To Do: Add string replace to remove spaces in filename
		$filename = basename($_FILES["upload_file"]["name"]);
		move_uploaded_file($_FILES["upload_file"]["tmp_name"], '/tmp/'.$filename);
		patchExec("uploadDB ".$filename);
		$status_msg = "<div class=\"text-success\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-ok-sign\"></span> File uploaded successfully.</div>";
	}
}

// Restore
if (isset($_POST["restore"])) {
	if (trim(patchExec("restoreDB ".$_POST["restore"])) == "true") {
		$status_msg = "<div class=\"text-success\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-ok-sign\"></span> Restored '".basename($_POST["restore"])."'.</div>";
	} else {
		$status_msg = "<div class=\"text-danger\" style=\"padding: 12px 0px;\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span> Restore failed.</div>";
	}
}

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

// ####################################################################
// End of GET/POST parsing
// ####################################################################

?>

<link rel="stylesheet" href="theme/awesome-bootstrap-checkbox.css"/>

<script type="text/javascript" src="scripts/patchValidation.js"></script>

<script type="text/javascript">
var scheduled = [<?php echo (sizeof($scheduled) > 0 ? "\"".implode('", "', $scheduled)."\"" : ""); ?>];

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

$(document).ready(function() {
	if (scheduled.length == 0) {
		showScheduleError();
	}
});
</script>

<script type="text/javascript">
$(document).ready(function(){
	$('a[data-toggle="tab"]').on('show.bs.tab', function(e) {
		localStorage.setItem('activeDbTab', $(e.target).attr('href'));
	});
	var activeDbTab = localStorage.getItem('activeDbTab');
	if(activeDbTab){
		$('#top-tabs a[href="' + activeDbTab + '"]').tab('show');
	}
});
</script>

<link rel="stylesheet" href="theme/dataTables.bootstrap.css" />

<script type="text/javascript" src="scripts/dataTables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="scripts/dataTables/dataTables.bootstrap.min.js"></script>
<script type="text/javascript" src="scripts/Buttons/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="scripts/Buttons/buttons.bootstrap.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {
	$('#backups').DataTable( {
		buttons: [ {
			text: '<span class="glyphicon glyphicon-plus"></span> Upload',
				action: function ( e, dt, node, config ) {
                    $("#uploadBackup").modal();
				}
			}
		],
		"dom": "<'row'<'col-sm-4'f><'col-sm-4'i><'col-sm-4'B>>" + "<'row'<'col-sm-12'tr>>" + "<'row'<'col-sm-5'l><'col-sm-7'p>>",
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

<div class="description"><a href="settings.php">Settings</a> <span class="glyphicon glyphicon-chevron-right"></span></div>
<h2>Patch</h2>

<div class="row">
	<div class="col-sm-12 col-md-9"> 

		<form action="patchSettings.php" method="post" name="Database" id="Database" enctype="multipart/form-data">

			<ul class="nav nav-tabs nav-justified" id="top-tabs">
				<li class="active"><a class="tab-font" href="#backup-tab" role="tab" data-toggle="tab">Backup / Restore</a></li>
				<li><a class="tab-font" href="#schedule-tab" role="tab" data-toggle="tab"><span id="schedule-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Schedule</a></li>
			</ul>

			<div class="tab-content">

				<div class="tab-pane active fade in" id="backup-tab">

					<?php echo (isset($status_msg) ? $status_msg : ""); ?>

					<h5><strong>Backup</strong></h5>
					<div class="description" style="padding-bottom: 4px;">Click the backup button to create a gzipped backup file of the SQLite database.</div>
					<button type="submit" name="backup" id="backup" class="btn btn-primary btn-sm" value="backup">Backup</button>

					<br>
					<br>

					<h5><strong>Available Backups</strong></h5>
					<div class="description" style="padding-bottom: 8px;">Backup archives are saved in <span style="font-family:monospace;">/var/appliance/backup</span> on this server.<br>Click the backup filename to download a backup archive.</div>

					<table id="backups" class="table table-striped">
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
								<td nowrap><a href="patchCtl.php?download=<?php echo basename($backup); ?>"><?php echo basename($backup); ?></a></td>
								<td nowrap><?php echo gmdate("Y-m-d\TH:i:s\Z", stat($backup)["mtime"]); ?></td>
								<td align="right"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#restore_<?php echo $i; ?>">Restore</button></td>
							</tr>
							<?php $i++;
							} ?>
						</tobdy>
					</table>

					<?php $i = 0; 
					foreach ($backups as $backup) { ?>
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
					<?php $i++;
					} ?>

					<div class="modal fade" id="uploadBackup" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h4 class="modal-title" id="modalLabel">Upload Backup</h4>
								</div>
								<div class="modal-body">

									<h5>Archive <small>Upload a backup archive(gz) file to add to your list of available backups.</small></h5>
									<input type="file" name="upload_file" id="upload_file" class="form-control input-sm" onChange="document.getElementById('upload').disabled = this.value == '';" >

								</div>
								<div class="modal-footer">
									<button type="submit" name="upload" id="upload" class="btn btn-primary btn-sm" disabled >Upload</button>
								</div>
							</div>
						</div>
					</div>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="schedule-tab">

					<h5><strong>Schedule</strong></h5>
					<div class="description" style="padding-bottom: 8px;">Select the days of the week you would like your backup to run.<br><strong>Note:</strong> Backups will occur at 12:00 AM on the specified days.</div>

					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[0]" id="schedule[0]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="0" <?php echo (in_array(0, $scheduled) ? "checked" : ""); ?>>
						<label for="sun"> Sun </label>
					</div>
					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[1]" id="schedule[1]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="1" <?php echo (in_array(1, $scheduled) ? "checked" : ""); ?>>
						<label for="mon"> Mon </label>
					</div>
					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[2]" id="schedule[2]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="2" <?php echo (in_array(2, $scheduled) ? "checked" : ""); ?>>
						<label for="tue"> Tue </label>
					</div>
					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[3]" id="schedule[3]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="3" <?php echo (in_array(3, $scheduled) ? "checked" : ""); ?>>
						<label for="wed"> Wed </label>
					</div>
					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[4]" id="schedule[4]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="4" <?php echo (in_array(4, $scheduled) ? "checked" : ""); ?>>
						<label for="thu"> Thu </label>
					</div>
					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[5]" id="schedule[5]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="5" <?php echo (in_array(5, $scheduled) ? "checked" : ""); ?>>
						<label for="fri"> Fri </label>
					</div>
					<div class="checkbox checkbox-primary checkbox-inline">
						<input name="schedule[6]" id="schedule[6]" class="styled" type="checkbox" onChange="updateSchedule(this);" value="6" <?php echo (in_array(6, $scheduled) ? "checked" : ""); ?>>
						<label for="sat"> Sat </label>
					</div>

					<br>
					<br>

					<h5 id="retention_label"><strong>Retention</strong> <small>Enter the number of backup archives that you would like to remain on the server.</small></h5>
					<div class="form-group has-feedback" style="max-width: 100px;">
						<input type="text" class="form-control input-sm" onFocus="validRetention(this, 'retention_label');" onKeyUp="validRetention(this, 'retention_label');" onChange="updateRetention(this);" placeholder="[1 - 30]" value="<?php echo $retention; ?>" />
					</div>

				</div><!-- /.tab-pane -->

			</div> <!-- end .tab-content -->

		</form> <!-- end Database form -->

	</div>
</div>

<?php include "inc/footer.php"; ?>

?>