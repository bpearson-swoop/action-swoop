<?php

require_once dirname(__DIR__).'/src/defines.php';
require_once dirname(__DIR__).'/src/environment.php';
require_once dirname(__DIR__).'/src/files.php';
require_once dirname(__DIR__).'/src/git.php';
require_once dirname(__DIR__).'/src/output.php';

// Defaults.
$groups   = ['nodatabase'];
$msgLevel = INFO;
$path     = 'tests/';

// Environment variables.
$groups   = environment('GROUP', $groups);
$msgLevel = environment('MSGLEVEL', $msgLevel);

logmsg("PHPUnit Groups: " . $groups, DEBUG);

$exit = 0;

$command = sprintf('./vendor/bin/phpunit --no-coverage --group %s %s 2>&1', escapeshellarg($groups), escapeshellarg($path));
$output  = [];
$retVal  = exec($command, $output, $exitCode);
if ($exitCode === 0) {
    // Lint passed.
    logmsg("PHPUnit success", INFO);
    logmsg(var_export($output, true), DEBUG);
} else {
    logmsg("PHPUnit failed", ERROR);
    logmsg(var_export($output, true), ERROR);
}//end if

exit($exitCode);
