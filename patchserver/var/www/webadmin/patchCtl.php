<?php

session_start();

if (!($_SESSION['isAuthUser'])) {

	echo "Not authorized - please log in";

} else {

	include "inc/config.php";
	include "inc/dbConnect.php";

	if (isset($pdo)) {

		// Enable / Disable Software Title
		if (isset($_GET['title_id']) && isset($_POST['title_enabled'])) {
			$title_enabled = ($_POST['title_enabled'] === "true") ? "1" : "0";
			$stmt = $pdo->prepare('UPDATE titles SET enabled = ? WHERE id = ?');
			$stmt->execute([$title_enabled, $_GET['title_id']]);
		}

		// Update Title Modified
		if (isset($_GET['title_id']) && isset($_POST['title_modified'])) {
			$title_modified = time();
			$stmt = $pdo->prepare('UPDATE titles SET modified = ? WHERE id = ?');
			$stmt->execute([$title_modified, $_GET['title_id']]);
		}

		// Enable/Disable Patch
		if (isset($_GET['patch_id']) && isset($_POST['patch_enabled'])) {
			$patch_enabled = ($_POST['patch_enabled'] === "true") ? "1" : "0";
			$stmt = $pdo->prepare('UPDATE patches SET enabled = ? WHERE id = ?');
			$stmt->execute([$patch_enabled, $_GET['patch_id']]);
		}
		
		// Update Field w/Table
		if (isset($_GET['table']) && isset($_GET['field']) && isset($_POST['value']) && isset($_GET['id'])) {
			$stmt = $pdo->prepare('UPDATE '.$_GET['table'].' SET '.$_GET['field'].' = ? WHERE id = ?');
			$stmt->execute([$_POST['value'], $_GET['id']]);
		}

		// Update Patch Released
		if (isset($_GET['patch_id']) && isset($_POST['patch_released'])) {
			$patch_released = date("U",strtotime($_POST['patch_released']));
			$stmt = $pdo->prepare('UPDATE patches SET released = ? WHERE id = ?');
			$stmt->execute([$patch_released, $_GET['patch_id']]);
		}

	}

}

?>