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