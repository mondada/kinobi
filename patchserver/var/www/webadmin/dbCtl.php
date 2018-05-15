<?php

session_start();

if (!($_SESSION['isAuthUser'])) {

	echo "Not authorized - please log in";

} else {

	include "inc/config.php";
	include "inc/functions.php";

	$sURL="dbSettings.php";

	function dbExec($cmd) {
		return shell_exec("sudo /bin/sh scripts/dbHelper.sh ".escapeshellcmd($cmd)." 2>&1");
	}

	// Download Backup
	if (isset($_GET['download'])) {
		$filename = $_GET['download'];
		if (file_exists('/var/appliance/backup/'.$filename)) {
			if (ob_get_level()) ob_end_clean();
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: '.filesize('/var/appliance/backup/'.$filename));
			ob_clean();
			flush();
			readfile('/var/appliance/backup/'.$filename);
			exit;
		}
	}

	// Backup Schedule
	if (isset($_POST['schedule'])) {
		if ($_POST['schedule'] == "") {
			dbExec("delSchedule");
		} else {
			dbExec("setSchedule ".$_POST['schedule']);
		}
	}

	// Backup Retention
	if (isset($_POST['retention'])) {
		$conf->setSetting("retention", $_POST['retention']);
	}

	header('Location: '. $sURL);

}

?>