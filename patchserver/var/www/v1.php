<?php

$req_auth = false;
$tokens = array();

$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));

if (sizeof($request) > 0) {

	include "webadmin/inc/config.php";
	include "webadmin/inc/dbConnect.php";

	if (!$req_auth || isset($_POST['token']) && in_array($_POST['token'], $tokens)) {

		if (isset($pdo)) {
			if ($request[0] == "software") {

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

				// Remove Expired Subscription
				if (isset($token) && $token['timestamp'] - (14*24*60*60) >= $token['expires'] || $conf->getSetting("kinobi_url") == "" && $conf->getSetting("kinobi_token") == "") {
					$pdo->exec('DELETE FROM titles WHERE source_id = "1"');
				}

				if (isset($token['refresh'])) {
					include $token['refresh'];
				}

				$response = array();
				if (empty($request[1])) {
					// This endpoint returns an array of Software Title Summary objects
					$stmt = $pdo->query('SELECT name, publisher, modified AS "lastModified", current AS "currentVersion", name_id AS "id" FROM titles WHERE enabled = 1');
					while ($sw_title = $stmt->fetch(PDO::FETCH_ASSOC)) {
						// $sw_title['lastModified'] = gmdate("Y-m-d\TH:i:s", $sw_title['lastModified']/1000).(gettype($sw_title['lastModified']/1000) == "double" ? ".".substr($sw_title['lastModified'], -3) : "")."Z";
						$sw_title['lastModified'] = gmdate("Y-m-d\TH:i:s\Z", $sw_title['lastModified']);
						array_push($response, $sw_title);
					}
				} else {
					// This endpoint returns a subset array of Software Title Summary objects that match any of the given {ids}
					foreach (explode(',', trim($request[1], ',')) as $name_id) {
						$stmt = $pdo->prepare('SELECT name, publisher, modified AS "lastModified", current AS "currentVersion", name_id AS "id" FROM titles WHERE enabled = 1 AND name_id = ?');
						$stmt->execute([$name_id]);
						while ($sw_title = $stmt->fetch(PDO::FETCH_ASSOC)) {
							// $sw_title['lastModified'] = gmdate("Y-m-d\TH:i:s", $sw_title['lastModified']/1000).(gettype($sw_title['lastModified']/1000) == "double" ? ".".substr($sw_title['lastModified'], -3) : "")."Z";
							$sw_title['lastModified'] = gmdate("Y-m-d\TH:i:s\Z", $sw_title['lastModified']);
							array_push($response, $sw_title);
						}
					}
				}
			}
			if ($request[0] === "patch") {
				if (isset($request[1])) {
					// This endpoint returns a Software Title object
					$stmt = $pdo->prepare('SELECT id AS "title_id", name, publisher, app_name AS "appName", bundle_id AS "bundleId", modified AS "lastModified", current AS "currentVersion", name_id AS "id" FROM titles WHERE enabled = 1 AND name_id = ?');
					$stmt->execute([$request[1]]);
					$response = $stmt->fetch(PDO::FETCH_ASSOC);
					if ($response) {
						// $response['lastModified'] = gmdate("Y-m-d\TH:i:s", $response['lastModified']/1000).(gettype($response['lastModified']/1000) == "double" ? ".".substr($response['lastModified'], -3) : "")."Z";
						$response['lastModified'] = gmdate("Y-m-d\TH:i:s\Z", $response['lastModified']);
						// requirements
						$response['requirements'] = array();
						$stmt = $pdo->query('SELECT name, operator, value, type, is_and AS "and" FROM requirements WHERE title_id = "'.$response['title_id'].'" ORDER BY sort_order');
						while ($requirement = $stmt->fetch(PDO::FETCH_ASSOC)) {
							if ($requirement['and'] === null) {
								unset($requirement['and']);
							} else {
								$requirement['and'] = ($requirement['and'] === "0") ? false: true;
							}
							array_push($response['requirements'], $requirement);
						}
						// patches
						$response['patches'] = array();
						$stmt = $pdo->query('SELECT id, version, released AS "releaseDate", standalone, min_os AS "minimumOperatingSystem", reboot FROM patches WHERE title_id = "'.$response['title_id'].'" AND enabled = 1 ORDER BY sort_order');
						while ($patch = $stmt->fetch(PDO::FETCH_ASSOC)) {
							// $patch['releaseDate'] = gmdate("Y-m-d\TH:i:s", $patch['releaseDate']/1000).(gettype($patch['releaseDate']/1000) == "double" ? ".".substr($patch['releaseDate'], -3) : "")."Z";
							$patch['releaseDate'] = gmdate("Y-m-d\TH:i:s\Z", $patch['releaseDate']);
							$patch['standalone'] = ($patch['standalone'] === "0") ? false: true;
							$patch['reboot'] = ($patch['reboot'] === "1") ? true: false;
							// killApps
							$patch['killApps'] = $pdo->query('SELECT bundle_id AS "bundleId", app_name AS "appName" FROM kill_apps WHERE patch_id = "'.$patch['id'].'"')->fetchAll(PDO::FETCH_ASSOC);
							// components
							$patch['components'] = array();
							$comp_stmt = $pdo->query('SELECT id, name, version FROM components WHERE patch_id = "'.$patch['id'].'"');
							while ($component = $comp_stmt->fetch(PDO::FETCH_ASSOC)) {
								// criteria
								$component['criteria'] = array();
								$criteria_stmt = $pdo->query('SELECT name, operator, value, type, is_and AS "and" FROM criteria WHERE component_id = "'.$component['id'].'" ORDER BY sort_order');
								while ($criteria = $criteria_stmt->fetch(PDO::FETCH_ASSOC)) {
									if ($criteria['and'] === null) {
										unset($criteria['and']);
									} else {
										$criteria['and'] = ($criteria['and'] === "0") ? false: true;
									}
									array_push($component['criteria'], $criteria);
								}
								unset($component['id']);
								array_push($patch['components'], $component);
							}
							// capabilities
							$patch['capabilities'] = array();
							$cap_stmt = $pdo->query('SELECT name, operator, value, type, is_and AS "and" FROM capabilities WHERE patch_id = "'.$patch['id'].'" ORDER BY sort_order');
							while ($capability = $cap_stmt->fetch(PDO::FETCH_ASSOC)) {
								if ($capability['and'] === null) {
									unset($capability['and']);
								} else {
									$capability['and'] = ($capability['and'] === "0") ? false: true;
								}
								array_push($patch['capabilities'], $capability);
							}
							// dependencies
							$patch['dependencies'] = array();
							$dep_stmt = $pdo->query('SELECT name, operator, value, type, is_and AS "and" FROM dependencies WHERE patch_id = "'.$patch['id'].'" ORDER BY sort_order');
							while ($dependency = $dep_stmt->fetch(PDO::FETCH_ASSOC)) {
								if ($dependency['and'] === null) {
									unset($dependency['and']);
								} else {
									$dependency['and'] = ($dependency['and'] === "0") ? false: true;
								}
								array_push($patch['dependencies'], $dependency);
							}
							unset($patch['id']);
							array_push($response['patches'], $patch);
						}
						// extensionAttributes
						$response['extensionAttributes'] = array();
						$stmt =  $pdo->query('SELECT key_id AS "key", script AS "value", name AS "displayName" FROM ext_attrs WHERE title_id = "'.$response['title_id'].'"');
						while ($ext_attr = $stmt->fetch(PDO::FETCH_ASSOC)) {
							$ext_attr['value'] = base64_encode($ext_attr['value']);
							array_push($response['extensionAttributes'], $ext_attr);
						}
						unset($response['title_id']);
					}
				}
			}
		}
	}
}

if (is_array($response)) {
	header("Content-Type: application/json");
	print_r(json_encode($response));
} else {
	header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
	echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL /v1.php".$_SERVER['PATH_INFO']." was not found on this server.</p>
<hr>
<address>".$_SERVER['SERVER_SIGNATURE']."</address>
</body></html>";
}

?>