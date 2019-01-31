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

// Kinobi configuration file location
define('KINOBI_CONF_PATH', dirname($_SERVER['DOCUMENT_ROOT']) . "/kinobi/conf/kinobi.json");


/**
 * Manage Settings for Kinobi
 *
 */
class KinobiConfig
{
    /**
     * kinobi settings parsed as an array
     * @var array
     */
    private $settings;

    /**
     * Constructor of KinobiConfig
     *
     */
    function __construct()
    {
        if (file_exists(KINOBI_CONF_PATH)) {
			$json_conf = file_get_contents(KINOBI_CONF_PATH);
			$this->settings = json_decode($json_conf, true);
		}
        if (!file_exists(KINOBI_CONF_PATH) || $this->settings == FALSE) {
            $this->settings = array();
        }
    }

    /**
     * Destructor of KinobiConfig
     *
     */
    function __destruct()
    {
    }

    /**
     * Get Setting
     *
     * @param string $setting
     *
     * @return mixed Returns an array, or null if not found
     */
    public function getSetting($setting)
    {
        if (array_key_exists($setting, $this->settings)) {
            return $this->settings[$setting];
        }
    }

    /**
     * Set Setting
     *
     * @param string $setting
     * @param mixed  $value
     *
     * @return null
     */
    public function setSetting($setting, $value)
    {
        $this->settings[$setting] = $value;
        $this->saveSettings();
    }

    /**
     * Delete Setting
     *
     * @param string $setting
     *
     * @return null
     */
    public function deleteSetting($setting)
    {
        if (array_key_exists($setting, $this->settings)) {
            unset($this->settings[$setting]);
            $this->saveSettings();
        }
    }

    /**
     * Save Settings
     *
     * @return null
     */
    private function saveSettings()
    {
        $json_conf = json_encode($this->settings);
        file_put_contents(KINOBI_CONF_PATH, $json_conf);
    }
}


// Initialise configuration object
$kinobi = new KinobiConfig();

// Default Preferences
if (is_null($kinobi->getSetting("uuid"))) {
	$kinobi->setSetting("uuid", createUuid());
}
if (is_null($kinobi->getSetting("users"))) {
	$kinobi->setSetting("users", array("webadmin" => array("password" => hash("sha256", "webadmin"), "web" => true)));
}
if (is_null($kinobi->getSetting("pdo"))) {
	$kinobi->setSetting("pdo", array("dsn" => array("prefix" => "sqlite", "dbpath" => dirname($_SERVER['DOCUMENT_ROOT']) . "/kinobi/db/patch_v1.sqlite")));
}
if (is_null($kinobi->getSetting("backup"))) {
	$kinobi->setSetting("backup", array("path" => dirname($_SERVER['DOCUMENT_ROOT']) . "/kinobi/backup", "retention" => 7));
}
if (is_null($kinobi->getSetting("subscription"))) {
	$kinobi->setSetting("subscription", array("url" => null, "token" => null, "refresh" => 3600));
}
if (is_null($kinobi->getSetting("api"))) {
	$kinobi->setSetting("api", array("auto" => true, "reqauth" => false, "authtype" => "basic"));
}

// Common Functions

/**
 * Generate UUID
 *
 * @return string Returns a valid UUID v4 string
 */
function createUuid()
{
	return strtoupper(sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand(0, 0xffff), mt_rand(0, 0xffff),
		mt_rand(0, 0xffff),
		mt_rand(0, 0x0fff) | 0x4000,
		mt_rand(0, 0x3fff) | 0x8000,
		mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
	));
}

/**
 * Retrieve JSON from an external server
 *
 * @param  string $url   Server URL
 * @param  string $token Bearer token value
 *
 * @return array Returns decoded json array
 */
function fetchJsonArray($url, $token = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if (null !== $token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $title = curl_exec($ch);
    curl_close($ch);
    return json_decode($title, true);
}

/**
 * Get Software Title Summary
 *
 * @param  object  $pdo      PDO database connection
 * @param  string  $ids      Comma sepoarated list of Software Title ids
 * @param  integer $enabled  Return only enabled Software Titles
 *
 * @return array Returns an array.
 */
function getSoftwareTitleSummary($pdo, $ids, $enabled = 1)
{
	$summary = array();

	if (null === $ids) {
		$ids = $pdo->query("SELECT name_id FROM titles")->fetchAll(PDO::FETCH_COLUMN);
	} else {
		$ids = explode(",", trim($ids, ","));
	}

	foreach ($ids as $name_id) {
		$stmt = $pdo->prepare("SELECT name, publisher, modified AS 'lastModified', current AS 'currentVersion', name_id AS 'id' FROM titles WHERE enabled >= ? AND name_id = ?");
		$stmt->execute(array($enabled, $name_id));
		while ($sw_title = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$sw_title['lastModified'] = gmdate("Y-m-d\TH:i:s\Z", $sw_title['lastModified']);
			array_push($summary, $sw_title);
		}
	}

	return $summary;
}

/**
 * Get Software Title
 *
 * @param  object  $pdo      PDO database connection
 * @param  string  $id       Software Title id
 * @param  integer $enabled  Return only enabled Software Titles
 *
 * @return array Returns an array, or NULL if not found.
 */
function getSoftwareTitle($pdo, $id, $enabled = 1)
{
	$stmt = $pdo->prepare("SELECT id FROM titles WHERE enabled >= ? AND name_id = ?");
	$stmt->execute(array($enabled, $id));
	$title_id = $stmt->fetch(PDO::FETCH_COLUMN);

	if ($title_id) {
		$title = $pdo->query("SELECT name, publisher, app_name AS 'appName', bundle_id AS 'bundleId', modified AS 'lastModified', current AS 'currentVersion', name_id AS 'id' FROM titles WHERE id = ".$title_id)->fetch(PDO::FETCH_ASSOC);
		$title['lastModified'] = gmdate("Y-m-d\TH:i:s\Z", $title['lastModified']);

		// requirements
		$title['requirements'] = $pdo->query("SELECT name, operator, value, type, is_and AS 'and' FROM requirements WHERE title_id = ".$title_id." ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
		foreach($title['requirements'] as $key => $value) {
			$title['requirements'][$key]['and'] = (is_null($value['and']) ? true : (bool)$value['and']);
		}

		// patches
		$title['patches'] = array();
		$stmt = $pdo->query("SELECT id, version, released AS 'releaseDate', standalone, min_os AS 'minimumOperatingSystem', reboot FROM patches WHERE title_id = ".$title_id." AND enabled >= ".$enabled." ORDER BY sort_order");
		while ($patch = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$patch['releaseDate'] = gmdate("Y-m-d\TH:i:s\Z", $patch['releaseDate']);
			$patch['standalone'] = (bool)$patch['standalone'];
			$patch['reboot'] = (bool)$patch['reboot'];

			// killApps
			$patch['killApps'] = $pdo->query("SELECT bundle_id AS 'bundleId', app_name AS 'appName' FROM kill_apps WHERE patch_id = ".$patch['id'])->fetchAll(PDO::FETCH_ASSOC);

			// components
			$patch['components'] = array();
			$comp_stmt = $pdo->query("SELECT id, name, version FROM components WHERE patch_id = ".$patch['id']);
			while ($component = $comp_stmt->fetch(PDO::FETCH_ASSOC)) {
				// criteria
				$component['criteria'] = $pdo->query("SELECT name, operator, value, type, is_and AS 'and' FROM criteria WHERE component_id = ".$component['id']." ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
				foreach($component['criteria'] as $key => $value) {
					$component['criteria'][$key]['and'] = (is_null($value['and']) ? true : (bool)$value['and']);
				}

				unset($component['id']);
				array_push($patch['components'], $component);
			}

			// capabilities
			$patch['capabilities'] = $pdo->query("SELECT name, operator, value, type, is_and AS 'and' FROM capabilities WHERE patch_id = ".$patch['id']." ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
			foreach($patch['capabilities'] as $key => $value) {
				$patch['capabilities'][$key]['and'] = (is_null($value['and']) ? true : (bool)$value['and']);
			}

			// dependencies
			$patch['dependencies'] = $pdo->query("SELECT name, operator, value, type, is_and AS 'and' FROM dependencies WHERE patch_id = ".$patch['id']." ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
			foreach($patch['dependencies'] as $key => $value) {
				$patch['dependencies'][$key]['and'] = (is_null($value['and']) ? true : (bool)$value['and']);
			}

			unset($patch['id']);
			array_push($title['patches'], $patch);
		}

		// extensionAttributes
		$title['extensionAttributes'] = $pdo->query("SELECT key_id AS 'key', script AS 'value', name AS 'displayName' FROM ext_attrs WHERE title_id = ".$title_id)->fetchAll(PDO::FETCH_ASSOC);
		foreach($title['extensionAttributes'] as $key => $value) {
			$title['extensionAttributes'][$key]['value'] = base64_encode($value['value']);
		}

        return $title;
	} else {
		return null;
	}
}