<?php

include "inc/config.php";
include "inc/auth.php";
include "inc/functions.php";

$title = "Database";

include "inc/header.php";

function dbExec($cmd) {
	return shell_exec("sudo /bin/sh scripts/dbHelper.sh ".escapeshellcmd($cmd)." 2>&1");
}

$status_msg = "<p class=\"text-muted\"><small>Nothing to report.</small></p>";

// Get Retention
$retention = $conf->getSetting("retention");
if ($retention == "") {
	$retention = 7;
	$conf->setSetting("retention", $retention);
}

// Backup
if (isset($_POST["backup"])) {
	if (trim(dbExec("backupDB")) == "true") {
		$status_msg = "<p><span class=\"glyphicon glyphicon-ok-sign text-success\"></span><small> Backup completed successfully.</small></p>";
	} else {
		$status_msg = "<p class=\"text-danger\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span><small> Backup failed.</small></p>";
	}
}

// Upload
if (isset($_POST["upload"]) && isset($_FILES["upload_file"]["name"])) {
	if ($_FILES["upload_file"]["error"] > 0) {
		$status_msg = "<p class=\"text-danger\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span><small> ".$_FILES["upload_file"]["error"].".</small></p>";
	} elseif ($_FILES["upload_file"]["type"] != "application/x-gzip") {
		$status_msg = "<p class=\"text-danger\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span><small> Invalid file type".$_FILES["upload_file"]["type"].".</small></p>";
	} else {
		// To Do: Add string replace to remove spaces in filename
		$filename = basename($_FILES["upload_file"]["name"]);
		move_uploaded_file($_FILES["upload_file"]["tmp_name"], '/tmp/'.$filename);
		dbExec("uploadDB ".$filename);
		$status_msg = "<p><span class=\"glyphicon glyphicon-ok-sign text-success\"></span><small> File uploaded successfully.</small></p>";
	}
}

// Restore
if (isset($_POST["restore"])) {
	if (trim(dbExec("restoreDB ".$_POST["restore"])) == "true") {
		$status_msg = "<p><span class=\"glyphicon glyphicon-ok-sign text-success\"></span><small> Restored '".basename($_POST["restore"])."'.</small></p>";
	} else {
		$status_msg = "<p class=\"text-danger\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span><small> Restore failed.</small></p>";
	}
}

// List Backups
$backup_list = trim(dbExec("listBackups"));
if ($backup_list != "") {
	$backups = explode("\n", $backup_list);
} else {
	$backups = array();
}

// Get Schedule
$schedule_str = trim(dbExec("getSchedule"));
if ($schedule_str != "") {
	$scheduled = explode(",", $schedule_str);
} else {
	$scheduled = array();
}

// ####################################################################
// End of GET/POST parsing
// ####################################################################

?>

<link rel="stylesheet" href="theme/checkbox.bootstrap.css"/>

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
	ajaxPost('dbCtl.php', 'schedule='+scheduled.join());
	if (scheduled.length == 0) {
		showScheduleError();
	} else {
		hideScheduleError();
	}
}

function validRetention(element, icon = false) {
	hideSuccess(element, icon);
	if (element.value == parseInt(element.value) && element.value > 0  && element.value < 31) {
		hideError(element, icon);
	} else {
		showError(element, icon);
	}
}

function updateRetention(element, icon = false) {
	if (element.value == parseInt(element.value) && element.value > 0  && element.value < 31) {
		ajaxPost("dbCtl.php", "retention="+element.value);
		showSuccess(element, icon);
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

<link rel="stylesheet" href="theme/datatables.bootstrap.css" />

<script type="text/javascript" src="scripts/datatables.jquery.min.js"></script>
<script type="text/javascript" src="scripts/datatables.bootstrap.min.js"></script>
<script type="text/javascript" src="scripts/datatables.buttons.min.js"></script>
<script type="text/javascript" src="scripts/datatables.buttons.bootstrap.min.js"></script>

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

<span class="description"><a href="settings.php">Settings</a> <span class="glyphicon glyphicon-chevron-right"></span></span>
<h2>Database</h2>

<div class="row">
	<div class="col-sm-12 col-md-8"> 

		<form action="dbSettings.php" method="post" name="Database" id="Database" enctype="multipart/form-data">

			<ul class="nav nav-tabs nav-justified" id="top-tabs">
				<li class="active"><a class="tab-font" href="#backup-tab" role="tab" data-toggle="tab">Backup / Restore</a></li>
				<li><a class="tab-font" href="#schedule-tab" role="tab" data-toggle="tab"><span id="schedule-tab-icon" class="glyphicon glyphicon-exclamation-sign hidden"></span> Schedule</a></li>
			</ul>

			<div class="tab-content">

				<div class="tab-pane active fade in" id="backup-tab">


					<label class="control-label">Status</label>
					<?php echo $status_msg; ?>

					<hr>

					<label class="control-label">Backup</label>
					<span class="description">Click the backup button to create a gzipped backup file of the SQLite database.</span>
					<button type="submit" name="backup" id="backup" class="btn btn-primary btn-sm" value="backup">Backup</button>

					<br>
					<br>
					<hr>

					<label class="control-label">Available Backups</label>
					<span class="description">Backup archives are saved in <span style="font-family:monospace;">/var/appliance/backup</span> on this server.<br>Click the backup filename to download a backup archive.</span>

					<br>

					<table id="backups" class="table table-striped">
						<thead>
							<tr>
								<th><small>Filename</small></th>
								<th><small>Date</small></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php $i = 0; 
							foreach ($backups as $backup) { ?>
							<tr>
								<td nowrap><small><a href="dbCtl.php?download=<?php echo basename($backup); ?>"><?php echo basename($backup); ?></a></small></td>
								<td nowrap><small><?php echo gmdate("Y-m-d\TH:i:s\Z", stat($backup)["mtime"]); ?></small></td>
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
									<span class="description">Are you sure you want to restore the database:<br> '<?php echo basename($backup); ?>' ?</span>
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
									<button type="button" class="close" data-dismiss="modal" aria-label="Close">
										<span aria-hidden="true">&times;</span>
									</button>
									<h4 class="modal-title" id="modalLabel">Upload Backup</h4>
								</div>
								<div class="modal-body">

									<label class="control-label">Archive</label>
									<span class="description">Upload a backup archive(gz) file to add to your list of available backups.</span>
									<span><input type="file" name="upload_file" id="upload_file" class="form-control input-sm" onChange="document.getElementById('upload').disabled = this.value == '';" ></span>

								</div>
								<div class="modal-footer">
									<button type="submit" name="upload" id="upload" class="btn btn-primary btn-sm" disabled >Upload</button>
								</div>
							</div>
						</div>
					</div>

				</div><!-- /.tab-pane -->

				<div class="tab-pane fade in" id="schedule-tab">

					<label class="control-label">Schedule</label>
					<span class="description">Select the days of the week you would like your backup to run.<br><strong>Note:</strong> Backups will occur at 12:00 AM on the specified days.</span>
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

					<label class="control-label">Retention</label>
					<span class="description">Enter the number of backup archives that you would like to remain on the server.</span>
					<div class="row">
						<div class="col-xs-3">
							<input type="text" name="retention" id="retention" class="form-control input-sm" onFocus="validRetention(this, true);" onKeyUp="validRetention(this, true);" onChange="validRetention(this, true); updateRetention(this, true);" placeholder="[1 - 30]" value="<?php echo $retention; ?>" />
						</div><!-- /.col -->
					</div><!-- /.row -->

				</div><!-- /.tab-pane -->

			</div> <!-- end .tab-content -->

		</form> <!-- end Database form -->

	</div>
</div>

<?php include "inc/footer.php"; ?>

?>