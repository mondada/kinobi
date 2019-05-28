<?php
// Re-direct to HTTPS if connecting via HTTP
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") {
	header("Location: https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
}

// to find current page
$currentFile = $_SERVER['PHP_SELF'];
$parts = explode("/", $currentFile);
$pageURI = $parts[count($parts) - 1];
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
		<meta http-equiv="refresh" content="<?php print ini_get("session.gc_maxlifetime"); ?>; url=/webadmin/logout.php">

		<title><?php echo (isset($title) ? $title : "Kinobi"); ?></title>

		<!-- Bootstrap -->
		<link href="theme/bootstrap.css" rel="stylesheet" media="all">

		<!-- Roboto Font -->
		<link href="theme/roboto.font.css" rel="stylesheet" type="text/css">

		<!-- Custom styles for this project -->
		<link href="theme/custom.css" rel="stylesheet" type="text/css">

		<!-- JQuery -->
		<script type="text/javascript" src="scripts/jquery/jquery-2.2.4.js"></script>

		<!-- Bootstrap JavaScript -->
		<script type="text/javascript" src="scripts/bootstrap.min.js"></script>
	</head>

	<body>

	<!-- Fixed Top Navbar -->
	<nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="container-fluid">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" style="padding-top: 10px;" href="patchTitles.php"><img src="images/kinobi-logo-rev.svg" height="30"></a>
				<div class="navbar-text">v1.2b12</div>
			</div>
			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<li class="<?php echo ($pageURI == "patchTitles.php" ? "active" : ""); ?>"><a href="patchTitles.php">Patch Definitions</a></li>
					<li class="<?php echo ($pageURI == "patchSettings.php" ? "active" : ""); ?>"><a href="patchSettings.php">Settings</a></li>
				</ul>
				<ul class="nav navbar-nav navbar-right">
					<li><a href="logout.php">Logout <?php echo (isset($_SESSION['username']) ? $_SESSION['username'] : ""); ?></a></li>
				</ul>
			</div><!--/.nav-collapse -->
		</div>
	</nav>

	<div id="wrapper">

		<!-- Page Content -->
		<div class="container-fluid" id="page-content-wrapper">
