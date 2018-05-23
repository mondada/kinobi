<?php

try {

	$pdo = new PDO("sqlite:/var/appliance/db/patch_v1.sqlite");
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$sql = 'CREATE TABLE IF NOT EXISTS "titles" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"name" text NOT NULL,
		"publisher" text NOT NULL,
		"app_name" text,
		"bundle_id" text,
		"modified" integer NOT NULL DEFAULT(-1),
		"current" text NOT NULL,
		"name_id" text NOT NULL,
		"enabled" integer NOT NULL DEFAULT(0),
		"source_id" integer NOT NULL DEFAULT(0)
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "requirements" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"title_id" integer NOT NULL DEFAULT(-1),
		"name" text NOT NULL,
		"operator" text NOT NULL,
		"value" text NOT NULL,
		"type" text NOT NULL,
		"is_and" integer,
		"sort_order" integer NOT NULL DEFAULT(-1),
		FOREIGN KEY (title_id) REFERENCES "titles" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "patches" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"title_id" integer NOT NULL DEFAULT(-1),
		"version" text NOT NULL,
		"released" integer NOT NULL DEFAULT(-1),
		"standalone" integer NOT NULL DEFAULT(1),
		"min_os" text NOT NULL,
		"reboot" integer NOT NULL DEFAULT(0),
		"sort_order" integer NOT NULL DEFAULT(-1),
		"enabled" integer NOT NULL DEFAULT(0),
		FOREIGN KEY (title_id) REFERENCES "titles" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "ext_attrs" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"title_id" integer NOT NULL DEFAULT(-1),
		"key_id" text NOT NULL,
		"script" text NOT NULL,
		"name" text NOT NULL,
		FOREIGN KEY (title_id) REFERENCES "titles" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "kill_apps" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"patch_id" integer NOT NULL DEFAULT(-1),
		"bundle_id" text NOT NULL,
		"app_name" text NOT NULL,
		FOREIGN KEY (patch_id) REFERENCES "patches" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "components" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"patch_id" integer NOT NULL DEFAULT(-1),
		"name" text NOT NULL,
		"version" text NOT NULL,
		FOREIGN KEY (patch_id) REFERENCES "patches" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "capabilities" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"patch_id" integer NOT NULL DEFAULT(-1),
		"name" text NOT NULL,
		"operator" text NOT NULL,
		"value" text NOT NULL,
		"type" text NOT NULL,
		"is_and" integer,
		"sort_order" integer NOT NULL DEFAULT(-1),
		FOREIGN KEY (patch_id) REFERENCES "patches" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "dependencies" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"patch_id" integer NOT NULL DEFAULT(-1),
		"name" text NOT NULL,
		"operator" text NOT NULL,
		"value" text NOT NULL,
		"type" text NOT NULL,
		"is_and" integer,
		"sort_order" integer NOT NULL DEFAULT(-1),
		FOREIGN KEY (patch_id) REFERENCES "patches" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'CREATE TABLE IF NOT EXISTS "criteria" (
		"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
		"component_id" integer NOT NULL DEFAULT(-1),
		"name" text NOT NULL,
		"operator" text NOT NULL,
		"value" text NOT NULL,
		"type" text NOT NULL,
		"is_and" integer,
		"sort_order" integer NOT NULL DEFAULT(-1),
		FOREIGN KEY (component_id) REFERENCES "components" (id) ON DELETE CASCADE
	)';
	$pdo->exec($sql);

	$sql = 'PRAGMA foreign_keys = ON';
	$pdo->exec($sql);

} catch(PDOException $e) {

	echo "<div class=\"alert alert-danger\"><strong>ERROR:</strong> ".$e->getMessage()."</div>";

}

?>