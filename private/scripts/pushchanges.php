<?php

$bindingDir = $_SERVER['HOME'];
$fullRepository = realpath("$bindingDir/code");

print "Enter push-back. Repository root is $fullRepository.\n";

// Not doing any git secrets yet, but we would do it here.

$privateFiles = realpath("$bindingDir/files/private");
$workDir = sys_get_temp_dir() . "/pushback-workdir";

// Temporary:
passthru("rm -rf $workDir");
mkdir($workDir);



// Just Github for now.
$upstreamRepo = "git@github.com:logickal/wp-demo-1.git";
$upstreamRepoWithCredentials = $upstreamRepo;
$upstreamRepoWithCredentials = str_replace('git@github.com:', 'https://github.com/', $upstreamRepoWithCredentials);
// Remember, no auth yet.
//$upstreamRepoWithCredentials = str_replace('https://', "https://$git_token:x-oauth-basic@", $upstreamRepoWithCredentials);

// Does the build metadata exist?
$metadata = load_build_metadata($fullRepository);
if (!$metadata) {
    print "No build metadata found.\n";
    exit(1);
} else {
    var_dump($metadata);
}


/**
 * Read the Build Metadata file
 */
function load_build_metadata($fullRepository) {
    $buildMetadataFile = "build-metadata.json";
    if (!file_exists("$fullRepository/$buildMetadataFile")) {
        pantheon_raise_dashboard_error("Could not find build metadata file, $buildMetadataFile\n");
    }
    $buildMetadataFileContents = file_get_contents("$fullRepository/$buildMetadataFile");
    $buildMetadata = json_decode($buildMetadataFileContents, true);
    if (empty($buildMetadata)) {
        pantheon_raise_dashboard_error("No data in build providers\n");
    }

    return $buildMetadata;
}


/**
 * Function to report an error on the Pantheon dashboard
 *
 * Not supported; may stop working at any point in the future.
 */
function pantheon_raise_dashboard_error($reason = 'Uknown failure', $extended = FALSE) {
    // Make creative use of the error reporting API
    $data = array('file'=>'Push Changes script',
        'line'=>'Error',
        'type'=>'error',
        'message'=>$reason);
    $params = http_build_query($data);
    $result = pantheon_curl('https://api.live.getpantheon.com/sites/self/environments/self/events?'. $params, NULL, 8443, 'POST');
    error_log("Push Changes Integration failed - $reason");
    // Dump additional debug info into the error log
    if ($extended) {
        error_log(print_r($extended, 1));
    }
    die("Push Changes failed - $reason");
}
