<?php

require_once dirname(__DIR__).'/src/defines.php';
require_once dirname(__DIR__).'/src/environment.php';
require_once dirname(__DIR__).'/src/files.php';
require_once dirname(__DIR__).'/src/git.php';
require_once dirname(__DIR__).'/src/output.php';

// Defaults.
$standard   = 'SWOOP';
$extensions = 'php';
$msgLevel   = INFO;

// Environment variables.
$standard   = environment('STANDARD', $standard);
$extensions = environment('EXTENSIONS', $extensions);
$msgLevel   = environment('MSGLEVEL', $msgLevel);

logmsg("Using Coding Standard: " . $standard, INFO);
logmsg("Note: In GH, there is a limit 10 errors and 10 warnings per job", INFO);

$extArray = explode(',', $extensions);
$extRegex = implode('|', $extArray);
if ($standard === 'SWOOP') {
    $standard = "./{$standard}/ruleset.xml";
}//end if

$exitCode = 0;

// PR number.
$pr = ($argv[1] ?? null);
if ($pr === null) {
    logmsg("No PR number provided", ERROR);
    exit(1);
}

if ($msgLevel == 8) {
    // Extra ddebugging help.
    $output = [];
    $command = sprintf('gh pr diff %s --name-only | grep -v -E "/(vendor|SWOOP|public_html/dist|node_modules)/" | grep -E %s', escapeshellarg($pr), escapeshellarg("\.({$extRegex})"));
    logmsg("Files changed", DEBUG);
    $retVal  = exec($command, $output, $exitCode);
    logmsg(implode("\n", $output), DEBUG);

    $output = [];
    $command = sprintf('gh pr diff %s --name-only | grep -v -E "/(vendor|SWOOP|public_html/dist|node_modules)/" | grep -E %s | xargs phpcs --report=checkstyle --standard=%s', escapeshellarg($pr), escapeshellarg("\.({$extRegex})"), escapeshellarg($standard));
    logmsg("PHPCS Command: {$command}", DEBUG);
    $retVal  = exec($command, $output, $exitCode);
    logmsg(implode("\n", $output), DEBUG);
}//end if

$output  = [];
$command = sprintf('gh pr diff %s --name-only | grep -v -E "/(vendor|SWOOP|public_html/dist|node_modules)/" | grep -E %s | xargs phpcs --report=checkstyle --standard=%s | cs2pr', escapeshellarg($pr), escapeshellarg("\.({$extRegex})"), escapeshellarg($standard));
logmsg("Command: {$command}", DEBUG);
$retVal  = exec($command, $output, $exitCode);
if ($exitCode === 0) {
    // PHPCS passed.
    logmsg("PHPCS success", INFO);
    logmsg(implode("\n", $output), DEBUG);
} else {
    logmsg("PHPCS failed", ERROR);
    logmsg(implode("\n", $output), ERROR);
}//end if

exit($exitCode);
