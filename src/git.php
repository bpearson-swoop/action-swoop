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
    update($merge);

    $files = diff($source, $merge);

    return $files;

}//end getChangedFiles()


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
    $command = sprintf('git diff --name-only origin/%s origin/%s', $source, $merge);
    exec($command, $output, $return);

    return $output;

}//end diff()
