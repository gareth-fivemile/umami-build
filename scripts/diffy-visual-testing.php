<?php

/**
 * Integration with https://diffy.website visual regression testing tool.
 *
 * When deployment to master branch happens, we just create new set of
 * screenshots.
 *
 * If it is not a master branch deployment, create a set of screenshots
 * and compare it with last screenshots from master.
 */

define("DIFFY_API_BASE_URL", "https://app.diffy.website");
define('DEBUG', FALSE);

$PLATFORM_VARIABLES = json_decode(base64_decode(getenv('PLATFORM_VARIABLES')), TRUE);

$branch = $_ENV['PLATFORM_BRANCH'];
$key = $PLATFORM_VARIABLES['DIFFY_ACCESS_TOKEN'];
$project_id = $PLATFORM_VARIABLES['DIFFY_PROJECT_ID'];
$basic_auth_user = $PLATFORM_VARIABLES['BASICAUTH_USER'];
$basic_auth_pass = $PLATFORM_VARIABLES['BASICAUTH_PASS'];

// What is URL of each branch.
$urls = [
  'test-environment-1' => 'https://' . $basic_auth_user . ':' . $basic_auth_pass . '@test-environment-1-5n57owi-acn64pnrbyo7q.eu.platform.sh',
  'test-environment-2' => 'https://' . $basic_auth_user . ':' . $basic_auth_pass . '@test-environment-2-bt6dcra-acn64pnrbyo7q.eu.platform.sh',
  'test-environment-3' => 'https://' . $basic_auth_user . ':' . $basic_auth_pass . '@test-environment-3-sop7gpy-acn64pnrbyo7q.eu.platform.sh/',
];

// First we need to get Bearer token for accessing API's.
echo 'Diffy: Getting a token to access API' . PHP_EOL;
$token = initiateToken($key);
echo 'Diffy: Token received (' . strlen($token) . ' chars long)' . PHP_EOL;

if ($branch == 'master') {
  $screenshots_id = triggerScreenshotJobProduction($token, $project_id);
  echo 'Diffy: Screenshots from master branch started. See: ' . DIFFY_API_BASE_URL . '/#/snapshots/' . $screenshots_id . PHP_EOL;
}
else {
  if (!isset($urls[$branch])) {
    echo 'Diffy: Unrecognized branch: ' . $branch . PHP_EOL;
    exit;
  }

  $screenshot_production_id = getLatestScreenshotFromProduction($token, $project_id);
  echo 'Diffy: Latest production screenshots set found (' . $screenshot_production_id . ')' . PHP_EOL;
  $screenshot_branch_id = triggerScreenshotJobBranch($token, $project_id, $urls[$branch]);
  echo 'Diffy: Started taking screenshots from branch (' . $screenshot_branch_id . ')' . PHP_EOL;
  $diff_id = triggerCompareJob($token, $project_id, $screenshot_production_id, $screenshot_branch_id);
  updateDiffName($token, $diff_id, $branch);

  echo 'Diffy: Screenshots and comparison started. Check the diff: ' . DIFFY_API_BASE_URL . '/#/diffs/' . $diff_id;
}


/**
 * Helper functions.
 */

function updateDiffName($token, $diff_id, $branch) {
  $curl = curl_init();
  $authorization = 'Authorization: Bearer ' . $token;
  $curlOptions = array(
    CURLOPT_URL => rtrim(DIFFY_API_BASE_URL, '/') . '/api/diffs/' . $diff_id,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json' , $authorization ),
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_POSTFIELDS => json_encode(array(
      'name' => $branch,
    ))
  );
  curl_setopt_array($curl, $curlOptions);
  $curlResponse = json_decode(curl_exec($curl));
  $curlErrorMsg = curl_error($curl);
  $curlErrno= curl_errno($curl);
  curl_close($curl);
  if ($curlErrorMsg) {
    echo 'Diffy: Error changing diff name: ' . $diff_id . ' to ' . $branch . '. Error: ' . $curlErrno . ': ' . $curlErrorMsg . PHP_EOL;
    exit;
  }
}

function triggerCompareJob($token, $project_id, $screenshot_production_id, $screenshot_branch_id) {
  $curl = curl_init();
  $authorization = 'Authorization: Bearer ' . $token;
  $curlOptions = array(
    CURLOPT_URL => rtrim(DIFFY_API_BASE_URL, '/') . '/api/projects/' . $project_id . '/diffs',
    CURLOPT_HTTPHEADER => array('Content-Type: application/json' , $authorization ),
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_POSTFIELDS => json_encode(array(
      'snapshot1' => $screenshot_production_id,
      'snapshot2' => $screenshot_branch_id,
    ))
  );
  curl_setopt_array($curl, $curlOptions);
  $curlResponse = json_decode(curl_exec($curl));
  $curlErrorMsg = curl_error($curl);
  $curlErrno= curl_errno($curl);
  curl_close($curl);
  if ($curlErrorMsg) {
    echo 'Diffy: Error triggering compare job. Screenshot ids are: ' . $screenshot_production_id . ' and ' . $screenshot_branch_id . '. Error: ' . $curlErrno . ': ' . $curlErrorMsg . PHP_EOL;
    exit;
  }
  if (DEBUG) {
    var_export($curlResponse);
  }
  return (int) $curlResponse;
}

function triggerScreenshotJobBranch($token, $project_id, $url) {
  $curl = curl_init();
  $authorization = 'Authorization: Bearer ' . $token;
  $curlOptions = array(
    CURLOPT_URL => rtrim(DIFFY_API_BASE_URL, '/') . '/api/projects/' . $project_id . '/screenshots',
    CURLOPT_HTTPHEADER => array('Content-Type: application/json' , $authorization ),
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_POSTFIELDS => json_encode(array(
      'environment' => 'custom',
      'baseUrl' => $url,
    ))
  );
  curl_setopt_array($curl, $curlOptions);
  $curlResponse = json_decode(curl_exec($curl));
  $curlErrorMsg = curl_error($curl);
  $curlErrno= curl_errno($curl);
  curl_close($curl);
  if ($curlErrorMsg) {
    echo 'Diffy: Error triggering creating screenshots from ' .$url . '. Error: ' . $curlErrno . ': ' . $curlErrorMsg . PHP_EOL;
    exit;
  }
  return (int) $curlResponse;
}

function getLatestScreenshotFromProduction($token, $project_id, $page = 0) {
  if ($page > 10) {
    echo 'Diffy: Something went wrong. We were not able to find production screenshot after scrolling through 10 pages of screenshots. Please create one manually.' . PHP_EOL;
    exit;
  }

  $curl = curl_init();
  $authorization = 'Authorization: Bearer ' . $token;
  $curlOptions = array(
    CURLOPT_URL => rtrim(DIFFY_API_BASE_URL, '/') . '/api/projects/' . $project_id . '/screenshots?page=' . $page,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json' , $authorization ),
    CURLOPT_RETURNTRANSFER => 1
  );
  curl_setopt_array($curl, $curlOptions);
  $curlResponse = json_decode(curl_exec($curl), TRUE);
  $curlErrorMsg = curl_error($curl);
  $curlErrno= curl_errno($curl);

  if ($curlErrorMsg) {
    echo 'Diffy error getting project screenshots data: ' . $curlErrno . ': ' . $curlErrorMsg . PHP_EOL;
    exit;
  }

  if (empty($curlResponse['screenshots'])) {
    echo 'Diffy: No recent screenshot from production found.' . PHP_EOL;
    exit;
  }

  foreach ($curlResponse['screenshots'] as $screenshot) {
    if ($screenshot['environment'] == 'production' && $screenshot['baseline'] != TRUE) {
      return $screenshot['id'];
    }
  }

  // If none found check more items by using pager.
  return getLatestScreenshotFromProduction($token, $project_id, $page++);
}

function triggerScreenshotJobProduction($token, $project_id) {
  $curl = curl_init();
  $authorization = 'Authorization: Bearer ' . $token;
  $curlOptions = array(
    CURLOPT_URL => rtrim(DIFFY_API_BASE_URL, '/') . '/api/projects/' . $project_id . '/screenshots',
    CURLOPT_HTTPHEADER => array('Content-Type: application/json' , $authorization ),
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_POSTFIELDS => json_encode(array(
      'environment' => 'production'
    ))
  );
  curl_setopt_array($curl, $curlOptions);
  $curlResponse = json_decode(curl_exec($curl));
  $curlErrorMsg = curl_error($curl);
  $curlErrno= curl_errno($curl);
  curl_close($curl);
  if ($curlErrorMsg) {
    echo 'Diffy error triggering creating screenshots from production: ' . $curlErrno . ': ' . $curlErrorMsg . PHP_EOL;
    exit;
  }
  return (int) $curlResponse;
}

function initiateToken($key) {
  $curl = curl_init();
  $curlOptions = array(
    CURLOPT_URL => rtrim(DIFFY_API_BASE_URL, '/') . '/api/auth/key',
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    CURLOPT_POSTFIELDS => json_encode(array(
      'key' => $key,
    ))
  );

  curl_setopt_array($curl, $curlOptions);
  $curlResponse = json_decode(curl_exec($curl));
  $curlErrorMsg = curl_error($curl);
  $curlErrno= curl_errno($curl);
  curl_close($curl);

  if ($curlErrorMsg) {
    echo('Can not get access token: ' . $curlErrno . ': ' . $curlErrorMsg . PHP_EOL);
    exit;
  }

  if (isset($curlResponse->token)) {
    $token = $curlResponse->token;
  } else {
    echo('Can not get access token. Response does not have "token" property: ' . $curlResponse->message . PHP_EOL);
    exit;
  }

  return $token;
}