<?php

require_once dirname(__DIR__).'/src/defines.php';
require_once dirname(__DIR__).'/src/environment.php';
require_once dirname(__DIR__).'/src/files.php';
require_once dirname(__DIR__).'/src/git.php';
require_once dirname(__DIR__).'/src/output.php';

// Defaults.
$groups   = ['nodatabase'];
$msgLevel = INFO;
$path     = './tests/';

// Environment variables.
$groups   = environment('GROUP', $groups);
$msgLevel = environment('MSGLEVEL', $msgLevel);

logmsg(ini_get('include_path'), INFO);

logmsg("PHPUnit Groups: " . $groups, DEBUG);

$exit = 0;

$command = sprintf('phpunit --no-coverage --group %s %s 2>&1', escapeshellarg($groups), $path);
$output  = [];
$retVal  = exec($command, $output, $exitCode);
if ($exitCode === 0) {
    // Lint passed.
    logmsg("PHPUnit success", INFO);
    logmsg(implode("\n", $output), INFO);
} else {
    logmsg("PHPUnit failed", ERROR);
    logmsg(implode("\n", $output), ERROR);
}//end if

exit($exitCode);
