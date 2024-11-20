<?php

require_once dirname(__DIR__).'/src/defines.php';
require_once dirname(__DIR__).'/src/environment.php';
require_once dirname(__DIR__).'/src/files.php';
require_once dirname(__DIR__).'/src/git.php';
require_once dirname(__DIR__).'/src/output.php';

// Defaults.
$groups   = ['nodatabase'];
$msgLevel = INFO;
$include  = '.';
$path     = './tests/';

// Environment variables.
$groups   = environment('GROUP', $groups);
$include  = environment('INCLUDEPATH', $include);
$msgLevel = environment('MSGLEVEL', $msgLevel);

logmsg("PHPUnit Groups: " . $groups, DEBUG);

$exit = 0;

$output  = [];
$command = sprintf('./vendor/bin/phpunit --no-coverage --include-path %s --group %s %s 2>&1', escapeshellarg($include), escapeshellarg($groups), $path);
logmsg("Running command: {$command}", DEBUG);
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
