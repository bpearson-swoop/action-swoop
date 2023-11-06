<?php


/**
 * Get a list of changed files.
 *
 * @param string $source The source commit (usually master).
 * @param string $merge  The merge branch (usually the PR branch).
 *
 * @return array
 */
function getChangedFiles($source, $merge)
{
    // By default, the action will do a shallow merge.
    fetch($source);
    fetch($merge);
    checkout($merge);
    update($merge);

    $files = diff($source, $merge);
    $fi    = [];
    foreach ($files as $file) {
        $fi[$file] = new SplFileInfo($file);
    }//end foreach

    return $fi;

}//end getChangedFiles()


/**
 * Check out a branch.
 *
 * @param string $branch The branch to checkout.
 *
 * @return void
 */
function checkout($branch)
{
    $output  = [];
    $command = sprintf('git checkout %s 2>/dev/null', $branch);
    exec($command, $output, $return);

}//end checkout()


/**
 * Grab the history of the repository.
 *
 * @param string $branch The branch to update.
 *
 * @return void
 */
function fetch($branch)
{
    $output  = [];
    $command = sprintf('git fetch -u origin %s 2>/dev/null', $branch);
    exec($command, $output, $return);

}//end fetch()


/**
 * Update the repository.
 *
 * @param string $branch The branch to update.
 *
 * @return void
 */
function update($branch)
{
    $output  = [];
    $command = sprintf('git pull origin %s 2>/dev/null', $branch);
    exec($command, $output, $return);

}//end update()


/**
 * Diff of files.
 *
 * @param string $source The source commit (usually master).
 * @param string $merge  The merge branch (usually the PR branch).
 *
 * @return array
 */
function diff($source, $merge)
{
    $output  = [];
    $command = sprintf("git diff --name-status origin/%s...origin/%s | grep -v -E '^D' | awk '{ print \$2 }'", $source, $merge);
    exec($command, $output, $return);

    return $output;

}//end diff()
