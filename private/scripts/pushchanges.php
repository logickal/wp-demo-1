<?php

$commitStatus = pushChanges();



function pushChanges()
{
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
$buildMetadata['url'] = "git@github.com:logickal/wp-demo-1.git";
$upstreamRepo = $buildMetadata['url'];
$upstreamRepoWithCredentials = $upstreamRepo;
$upstreamRepoWithCredentials = str_replace('git@github.com:', 'https://github.com/', $upstreamRepoWithCredentials);
// Remember, no auth yet.
//$upstreamRepoWithCredentials = str_replace('https://', "https://$git_token:x-oauth-basic@", $upstreamRepoWithCredentials);
$branch = 'master';

$commitToSubmit = exec("git -C $fullRepository rev-parse HEAD");
print "Commit to submit is $commitToSubmit.\n";
$targetBranch = $branch;

print "::::::::::::::::: Info :::::::::::::::::\n";
print "We are going to check out $branch from {$buildMetadata['url']} and cherry-pick $commitToSubmit onto it\n";

$canonicalRepository = "$workDir/scratchRepository";
$workbranch = "recommit-work";

    // Make a working clone of the Git branch. Clone just the branch
    // and commit we need.
    passthru("git clone $upstreamRepoWithCredentials --depth=1 --branch $branch --single-branch $canonicalRepository 2>&1");

        // If there have been extra commits, then unshallow the repository so that
    // we can make a branch off of the commit this multidev was built from.
    print "git rev-parse HEAD\n";
    $remoteHead = exec("git -C $canonicalRepository rev-parse HEAD");
//    if ($remoteHead != $fromSha) {
//        // TODO: If we had git 2.11.0, we could use --shallow-since with the date
//        // from $buildMetadata['commit-date'] to get exactly the commits we need.
//        // Until then, though, we will just `unshallow` the whole branch if there
//        // is a conflicting commit.
//        print "git fetch --unshallow\n";
//        passthru("git -C $canonicalRepository fetch --unshallow 2>&1");
//    }

    // Get metadata from the commit at the HEAD of the full repository
    $comment = escapeshellarg(exec("git -C $fullRepository log -1 --pretty=\"%s\""));
    $commit_date = escapeshellarg(exec("git -C $fullRepository log -1 --pretty=\"%at\""));
    $author_name = exec("git -C $fullRepository log -1 --pretty=\"%an\"");
    $author_email = exec("git -C $fullRepository log -1 --pretty=\"%ae\"");
    $author = escapeshellarg("$author_name <$author_email>");

        print "Comment is $comment and author is $author and date is $commit_date\n";
    // Make a safe space to store stuff
    $safe_space = "$workDir/safe-space";
    mkdir($safe_space);

    // If there are conflicting commits, or if this new commit is on the master
    // branch, then we will work from and push to a branch with a different name.
    // The user should then create a new PR, and use the Git Provider UI to resolve
    // any conflicts (or clone the branch locally to do the same thing).
    $createNewBranchReason = '';
    if ($branch == 'master') {
        $createNewBranchReason = "the $branch branch cannot be pushed to directly";
    }
//    elseif ($remoteHead != $fromSha) {
//        $createNewBranchReason = "new conflicting commits (e.g. $remoteHead) were added to the upstream repository";
//    }
    if (!empty($createNewBranchReason)) {
        // Warn that a new branch is being created.
        $targetBranch = substr($commitToSubmit, 0, 5) . $branch;
        print "git checkout -B $targetBranch";
        passthru("git -C $canonicalRepository checkout -B $targetBranch 2>&1");
    }

        // Now for some git magic.
    //
    // - $fullRepository contains all of the files we want to commit (and more).
    // - $canonicalRepository is where we want to commit them.
    //
    // The .gitignore file in the canonical repository is correctly configured
    // to ignore the build results that we do not want from the full repository.
    //
    // To affect the change, we will:
    //
    // - Copy the .gitignore file from the canonical repository to the full repo.
    // - Operate on the CONTENTS of the full repository with the .git directory
    //   of the canonical repository via the --git-dir and -C flags.
    // - We restore the .gitignore at the end via `git checkout -- .gitignore`.

    //$gitignore_contents = file_get_contents("$canonicalRepository/.gitignore");
    //file_put_contents("$fullRepository/.gitignore", $gitignore_contents);

    //print "::::::::::::::::: .gitignore :::::::::::::::::\n$gitignore_contents\n";
    $fromSha = exec("git --git-dir=$canonicalRepository/.git --work-tree=$canonicalRepository rev-parse HEAD");
    print 'Using commit ' . $fromSha . ' from the canonical repository';
    // Git diff
    passthru("git --git-dir=$canonicalRepository/.git --work-tree=$canonicalRepository diff --name-only $fromSha $commitToSubmit");
    // Add our files and make our commit
    print "git add .\n";
    passthru("git --git-dir=$canonicalRepository/.git -C $fullRepository add .", $status);
    if ($status != 0) {
        print "FAILED with $status\n";
    }
    // We don't want to commit the build-metadata to the canonical repository.
//    passthru("git --git-dir=$canonicalRepository/.git -C $fullRepository reset HEAD $buildMetadataFile");
    // TODO: Copy author, message and perhaps other attributes from the commit at the head of the full repository
    passthru("git --git-dir=$canonicalRepository/.git -C $fullRepository commit -q --no-edit --message=$comment --author=$author --date=$commit_date", $commitStatus);

    // Get our .gitignore back
    passthru("git -C $fullRepository checkout -- .gitignore");

    // Make sure that HEAD changed after 'git apply'
    $appliedCommit = exec("git -C $canonicalRepository rev-parse HEAD");

    // Seatbelts: this generally should not happen. If it does, we will presume
    // it is not an error; this situation might arise if someone commits only
    // changes to build result files from dashboard.
    if ($appliedCommit == $remoteHead) {
        print "'git commit' did not add a commits. Status code: $commitStatus\n";
        return;
    }

    exec("git -C $canonicalRepository diff-tree --no-commit-id --name-only -r HEAD", $committedFiles);
    $committedFiles = implode("\n", $committedFiles);
    if (empty($committedFiles)) {
        print "Commit $appliedCommit does not contain any files.\n";
        return;
    }
    // Even more seatbelts: ensure that there is nothing in the
    // commit that should not have been modified. Our .gitignore
    // file should ensure this never happens. For now, only test
    // 'vendor'.
    if (preg_match('#^vendor/#', $committedFiles)) {
        print "Aborting: commit $appliedCommit contains changes to the 'vendor' directory.\n";
        return 1;
    }

    // If the apply worked, then push the commit back to the light repository.
    if (($commitStatus == 0) && ($appliedCommit != $remoteHead)) {

        // Push the new branch back to Pantheon
        passthru("git -C $canonicalRepository push $upstreamRepoWithCredentials $targetBranch 2>&1");

        // TODO: If a new branch was created, it would be cool to use the Git API
        // to create a new PR. If there is an existing PR (i.e. branch not master),
        // it would also be cool to cross-reference the new PR to the old PR. The trouble
        // here is converting the branch name to a PR number.
    }

    return $commitStatus;
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
