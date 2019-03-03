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

$pdo_error = "";
$pdo_message = "";

$db = $kinobi->getSetting("pdo");
$uuid = $kinobi->getSetting("uuid");

if ($db['dsn']['prefix'] == "sqlite") {
	$dsn = $db['dsn']['prefix'].":".$db['dsn']['dbpath'];
	$username = null;
	$passwd = null;
	$auto_inc = "AUTOINCREMENT";
	$engine = "";
	$charset = "";
} else {
	$dsn = $db['dsn']['prefix'].":host=".$db['dsn']['host'].";port=".$db['dsn']['port'].";dbname=".$db['dsn']['dbname'];
	$username = $db['username'];
	$passwd = openssl_decrypt($db['passwd'], "AES-128-CTR", $uuid, 0, substr(md5($username), 0, 16));
	$auto_inc = "AUTO_INCREMENT";
	$engine = " ENGINE=InnoDB";
	$charset = " DEFAULT CHARSET=utf8";
}

try {
	$pdo = new PDO($dsn, $username, $passwd);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$sql = "CREATE TABLE IF NOT EXISTS titles (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  name varchar(255) NOT NULL,
  publisher varchar(255) NOT NULL,
  app_name varchar(255) DEFAULT NULL,
  bundle_id varchar(255) DEFAULT NULL,
  modified bigint(32) NOT NULL DEFAULT -1,
  current varchar(255) NOT NULL,
  name_id varchar(255) NOT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  source_id int(11) NOT NULL DEFAULT 0
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS requirements (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS patches (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  version varchar(255) NOT NULL,
  released bigint(32) NOT NULL DEFAULT -1,
  standalone tinyint(1) NOT NULL DEFAULT 1,
  min_os varchar(255) NOT NULL,
  reboot tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT -1,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS ext_attrs (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  key_id varchar(255) NOT NULL,
  script longtext,
  name varchar(255) NOT NULL,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS kill_apps (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  bundle_id varchar(255) NOT NULL,
  app_name varchar(255) NOT NULL,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS components (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  version varchar(255) NOT NULL,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS capabilities (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS dependencies (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS criteria (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  component_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (component_id) REFERENCES components (id) ON DELETE CASCADE
)" . $engine . $charset;
	$pdo->exec($sql);

/*
	$sql = "CREATE TABLE IF NOT EXISTS users (
  id integer PRIMARY KEY " . $auto_inc . " NOT NULL,
  username varchar(255) NOT NULL,
  password varchar(255) NOT NULL,
  token varchar(255),
  expires bigint(32),
  api tinyint(1)
)" . $engine . $charset;
	$pdo->exec($sql);

	$sql = "CREATE TABLE IF NOT EXISTS api (
  authtype varchar(255) NOT NULL DEFAULT('basic'),
  auto tinyint(1) NOT NULL DEFAULT(1),
  reqauth tinyint(1) NOT NULL DEFAULT(0)
)" . $engine . $charset;
	$pdo->exec($sql);
*/

	$sql = "CREATE TABLE IF NOT EXISTS subscription (
  url varchar(255),
  token varchar(255),
  refresh int(11) NOT NULL DEFAULT(3600),
  lastcheckin bigint(32) NOT NULL DEFAULT(0)
)" . $engine . $charset;
	$pdo->exec($sql);

	if ($db['dsn']['prefix'] == "sqlite") {
		$source_id_type = "";

		$pragma = $pdo->query("PRAGMA table_info('titles');");
		while ($table_info = $pragma->fetch(PDO::FETCH_ASSOC)) {
			if ($table_info['name'] == "source_id") {
				$source_id_type = $table_info['type'];
			}
		}

		if ($source_id_type != "int(11)") {
			$sql = "PRAGMA writable_schema=ON; 
UPDATE sqlite_master SET sql='CREATE TABLE titles (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  name varchar(255) NOT NULL,
  publisher varchar(255) NOT NULL,
  app_name varchar(255) DEFAULT NULL,
  bundle_id varchar(255) DEFAULT NULL,
  modified bigint(32) NOT NULL DEFAULT -1,
  current varchar(255) NOT NULL,
  name_id varchar(255) NOT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  source_id int(11) NOT NULL DEFAULT 0
)' WHERE type='table' AND name='titles';
UPDATE sqlite_master SET sql='CREATE TABLE requirements (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
)' WHERE type='table' AND name='requirements';
UPDATE sqlite_master SET sql='CREATE TABLE patches (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  version varchar(255) NOT NULL,
  released bigint(32) NOT NULL DEFAULT -1,
  standalone tinyint(1) NOT NULL DEFAULT 1,
  min_os varchar(255) NOT NULL,
  reboot tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT -1,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
)' WHERE type='table' AND name='patches';
UPDATE sqlite_master SET sql='CREATE TABLE ext_attrs (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  key_id varchar(255) NOT NULL,
  script longtext,
  name varchar(255) NOT NULL,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
)' WHERE type='table' AND name='ext_attrs';
UPDATE sqlite_master SET sql='CREATE TABLE kill_apps (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  bundle_id varchar(255) NOT NULL,
  app_name varchar(255) NOT NULL,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)' WHERE type='table' AND name='kill_apps';
UPDATE sqlite_master SET sql='CREATE TABLE components (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  version varchar(255) NOT NULL,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)' WHERE type='table' AND name='components';
UPDATE sqlite_master SET sql='CREATE TABLE capabilities (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)' WHERE type='table' AND name='capabilities';
UPDATE sqlite_master SET sql='CREATE TABLE dependencies (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
)' WHERE type='table' AND name='dependencies';
UPDATE sqlite_master SET sql='CREATE TABLE criteria (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  component_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (component_id) REFERENCES components (id) ON DELETE CASCADE
)' WHERE type='table' AND name='criteria';
PRAGMA writable_schema=OFF;";
			$pdo->exec($sql);
			$pdo_message = "Schema updated successfully.";
		}

		$sql = 'PRAGMA foreign_keys = ON';
		$pdo->exec($sql);
	}

} catch(PDOException $e) {
	$pdo_error = $e->getMessage();
    $pdo = false;
}

?>