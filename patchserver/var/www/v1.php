<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.2
 *
 */

// Settings
if (file_exists("webadmin/inc/config.php")) {
	include "webadmin/inc/config.php";
}
include "webadmin/inc/patch/functions.php";

// Database
include "webadmin/inc/patch/database.php";

// Slim Framework
require "../kinobi/bin/Slim/Slim.php";
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

// Service
$app->service = (isset($conf) ? $conf->getSetting("patch") !== "disabled" : true);

// Authorization
$api = getSettingApi($pdo);

$app->authorzied = ($api['reqauth'] ? false : "0");

if (isset($_SERVER['Authorization'])) {
	$auth_header = trim($_SERVER['Authorization']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
	$auth_header = trim($_SERVER['HTTP_AUTHORIZATION']);
} elseif (function_exists("apache_request_headers")) {
	$request_headers = apache_request_headers();
	$request_headers = array_combine(array_map("ucwords", array_keys($request_headers)), array_values($request_headers));

	if (isset($request_headers['Authorization'])) {
		$auth_header = trim($request_headers['Authorization']);
	}
}

if (isset($auth_header)) {
	if (preg_match("/Bearer\s(\S+)/", $auth_header, $matches)) {
		$api_token = $matches[1];
	}
}

if (isset($api_token)) {
	$users = getSettingUsers($pdo);

	$api_tokens = array();
	foreach ($users as $key => $value) {
		if (isset($value['token']) && isset($value['api']) && (!isset($value['expires']) || $value['expires'] > time())) {
			$api_tokens[$value['token']] = $value['api'];
		}
	}

	$app->authorzied = (array_key_exists($api_token, $api_tokens) ? $api_tokens[$api_token] : $app->authorzied);
} else {
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header("WWW-Authenticate: Basic realm=\"v1\"");
	} else {
		$username = $_SERVER['PHP_AUTH_USER'];
		$password = hash("sha256", $_SERVER['PHP_AUTH_PW']);
	
		$users = getSettingUsers($pdo);

		$app->authorzied = (array_key_exists($username, $users) && $users[$username]['password'] == $password && (!isset($users[$username]['expires']) || $users[$username]['expires'] > time()) && isset($users[$username]['api']) ? $users[$username]['api'] : $app->authorzied);
	}
}

$app->unauthorized = "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">" . PHP_EOL . "<html><head>" . PHP_EOL . "<title>401 Unauthorized</title>" . PHP_EOL . "</head><body>" . PHP_EOL . "<h1>Unauthorized</h1>" . PHP_EOL . "<p>This server could not verify that you" . PHP_EOL . "are authorized to access the document" . PHP_EOL . "requested.  Either you supplied the wrong" . PHP_EOL . "credentials (e.g., bad password), or your" . PHP_EOL . "browser doesn't understand how to supply" . PHP_EOL . "the credentials required.</p>" . (empty($_SERVER['SERVER_SIGNATURE']) ? "" : PHP_EOL . "<hr>" . PHP_EOL . "<address>" . $_SERVER['SERVER_SIGNATURE'] . "</address>") . PHP_EOL . "</body></html>" . PHP_EOL;
$app->bad_request = "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">" . PHP_EOL . "<html><head>" . PHP_EOL . "<title>400 Bad Request</title>" . PHP_EOL . "</head><body>" . PHP_EOL . "<h1>Bad Request</h1>" . PHP_EOL . "<p>Your browser sent a request that this server could not understand.<br />" . PHP_EOL . "</p>" . (empty($_SERVER['SERVER_SIGNATURE']) ? "" : PHP_EOL . "<hr>" . PHP_EOL . "<address>" . $_SERVER['SERVER_SIGNATURE'] . "</address>") . PHP_EOL . "</body></html>" . PHP_EOL;

// Subscription
$subs_resp = false;
$subs = getSettingSubscription($pdo);
if (!empty($subs['url']) && !empty($subs['token'])) {
    $subs_resp = fetchJsonArray($subs['url'], $subs['token']);
}

// Refresh
include "webadmin/inc/patch/refresh.php";

// Software
$app->get(
    "/software(/(:ids))",
    function ($ids = null) use ($app, $pdo) {
        if ($app->service) {
            if ($pdo) {
                if (false !== $app->authorzied) {
                    $result = getSoftwareTitleSummary($pdo, $ids);

                    $response = $app->response();
                    $response['Content-Type'] = "application/json";
                    $response->status(200);
                    $response->body(json_encode($result));
                } else {
                    $app->halt(401, $app->unauthorized);
                }
            } else {
                $app->error();
            }
        } else {
            $app->notFound();
        }
    }
);

// Patch
$app->get(
    "/patch/:id",
    function ($id) use ($app, $pdo) {
        if ($app->service) {
            if ($pdo) {
                if (false !== $app->authorzied) {
                    $result = getSoftwareTitle($pdo, $id);

                    if (null !== $result) {
                        $response = $app->response();
                        $response['Content-Type'] = "application/json";
                        $response->status(200);
                        $response->body(json_encode($result));
                    } else {
                        $app->notFound();
                    }
                } else {
                    $app->halt(401, $app->unauthorized);
                }
            } else {
                $app->error();
            }
        } else {
            $app->notFound();
        }
    }
);

// Test Software
$app->get(
    "/test/software(/(:ids))",
    function ($ids = null) use ($app, $pdo) {
        if ($app->service) {
            if ($pdo) {
                if (false !== $app->authorzied) {
                    $result = getSoftwareTitleSummary($pdo, $ids, 0);

                    $response = $app->response();
                    $response['Content-Type'] = "application/json";
                    $response->status(200);
                    $response->body(json_encode($result));
                } else {
                    $app->halt(401, $app->unauthorized);
                }
            } else {
                $app->error();
            }
        } else {
            $app->notFound();
        }
    }
);

// Test Patch
$app->get(
    "/test/patch/:id",
    function ($id) use ($app, $pdo) {
        if ($app->service) {
            if ($pdo) {
                if (false !== $app->authorzied) {
                    $result = getSoftwareTitle($pdo, $id, 0);

                    if (null !== $result) {
                        $response = $app->response();
                        $response['Content-Type'] = "application/json";
                        $response->status(200);
                        $response->body(json_encode($result));
                    } else {
                        $app->notFound();
                    }
                } else {
                    $app->halt(401, $app->unauthorized);
                }
            } else {
                $app->error();
            }
        } else {
            $app->notFound();
        }
    }
);

// Backup Database
$app->get(
    "/backup/:uuid",
    function ($uuid) use ($app, $kinobi) {
        if ($app->service) {
            if ($uuid == $kinobi->getSetting("uuid")) {
				$db = $kinobi->getSetting("pdo");
				$backup = $kinobi->getSetting("backup");
				$timestamp = time();

				include_once("webadmin/inc/patch/mysqldump.php");

				if ($db['dsn']['prefix'] == "mysql") {
					$dbname = $db['dsn']['dbname'];
					$dump = new Mysqldump(
						$db['dsn']['prefix'].":host=".$db['dsn']['host'].";port=".$db['dsn']['port'].";dbname=".$db['dsn']['dbname'],
						$db['username'],
						openssl_decrypt($db['passwd'], "AES-128-CTR", $uuid, 0, substr(md5($db['username']), 0, 16)),
						array('compress' => Mysqldump::GZIP, "add-drop-table" => true, 'no-autocommit' => false)
					);
				}

				if ($db['dsn']['prefix'] == "sqlite") {
					$dbname = basename($db['dsn']['dbpath']);
					if ($pos = strpos($dbname, ".")) {
						$dbname = substr($dbname, 0, $pos);
					}
					$dump = new Mysqldump(
						$db['dsn']['prefix'].":".$db['dsn']['dbpath'],
						null,
						null,
						array('compress' => Mysqldump::GZIP, 'no-autocommit' => false, 'sqlite-dump' => true)
					);
				}

				$dump->start($backup['path']."/".$dbname."-".$timestamp.".sql.gz");
            } else {
				$app->halt(401, $app->unauthorized);
            }
        } else {
            $app->notFound();
        }
    }
);

if (isset($subs_resp['endpoint'])) {
    include $subs_resp['endpoint'];
}

$app->run();
