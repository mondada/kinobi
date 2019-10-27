<?php

/**
 * Kinobi - external patch source for Jamf Pro
 *
 * @author      Duncan McCracken <duncan.mccracken@mondada.coma.au>
 * @copyright   2018-2019 Mondada Pty Ltd
 * @link        https://mondada.github.io
 * @license     https://github.com/mondada/kinobi/blob/master/LICENSE
 * @version     1.3
 *
 */

$subs = getSettingSubscription($pdo);
$last_checkin = (isset($subs['lastcheckin']) ? $subs['lastcheckin'] : 0);

// Remove Expired Subscription
if (!empty($subs_resp) && $subs_resp['timestamp'] - (14*24*60*60) >= $subs_resp['expires'] || empty($subs['url']) && empty($subs['token'])) {
	$removed = $pdo->exec('DELETE FROM titles WHERE source_id = "1"');
	if (!empty($removed)) {
		$warning_msg = "Titles imported from Kinobi have been removed.";
	}
}

if (isset($subs_resp['functions']) && isset($subs_resp['source']) && $last_checkin + $subs['refresh'] < time()) {
	require_once $subs_resp['functions'];

    // Get Remote /software Endpoint
    $software = fetchJsonArray($subs_resp['source'] . "/software/", $subs_resp['auth']);

    // lastModified Timestamps
    $timestamps = array();
    foreach ($software as $item) {
        $timestamps[$item['id']] = date("U", strtotime($item['lastModified']));
    }

    // Imported Titles
    $imported_titles = $pdo->query("SELECT name_id, modified FROM titles WHERE source_id = 1")->fetchAll(PDO::FETCH_ASSOC);

    // Refresh Titles
    foreach ($imported_titles as $imported) {
        if ($imported['modified'] < $timestamps[$imported['name_id']]) {
            $data = fetchJsonArray($subs_resp['source'] . "/patch/" . $imported['name_id'], $subs_resp['auth']);

            if (isset($data['id'])) {
                $pdo->exec("DELETE FROM titles WHERE name_id = '" . $data['id'] . "'");

                $ref_title_id = createSoftwareTitle($pdo, $data, 1);
            }
        }
    }

	$subs['lastcheckin'] = time();
    setSettingSubscription($pdo, $subs);
}
