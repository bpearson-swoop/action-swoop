<?php

require_once dirname(__DIR__).'/src/defines.php';
require_once dirname(__DIR__).'/src/environment.php';
require_once dirname(__DIR__).'/src/files.php';
require_once dirname(__DIR__).'/src/git.php';
require_once dirname(__DIR__).'/src/output.php';

// Defaults.
$phpextensions = ['php'];
$msgLevel      = INFO;

// Environment variables.
$phpextensions = environment('INPUT_PHP_FILE_EXTENSIONS', $phpextensions);
$msgLevel      = environment('MSGLEVEL', $msgLevel);
$baseRef       = environment('GITHUB_BASE_REF', 'master');
$headRef       = environment('GITHUB_HEAD_REF', false);
$event         = environment('GITHUB_EVENT_NAME', false);

if ($headRef === false) {
    logmsg("No HEAD ref found", ERROR);
    exit(1);
}//end if

if ($event === false && !in_array($event, ['push', 'pull_request'])) {
    logmsg("Invalid event: {$event}", ERROR);
    exit(1);
}//end if

$phpextensions = array_map('strtolower', array_map('trim', $phpextensions));

logmsg("PHP File extensions: " . implode(', ', $phpextensions), DEBUG);

$counts = [
    'check' => 0,
    'skip'  => 0,
];
$exit   = 0;

$files = getFiles();
$limit = [];
if ($event !== 'push' && $baseRef !== $headRef) {
    $limit = getChangedFiles($baseRef, $headRef);
    logmsg(sprintf("Files between %s and %s changed: ", $baseRef, $headRef, implode(',', $limit)), DEBUG);
}//end if

foreach ($files as $file => $info) {
    $path = substr($file, 2);
    if (!in_array($path, $limit)) {
        // Skip files that are not in the limit.
        continue;
    }//end if

    if (in_array($info->getExtension(), $phpextensions)) {
        logmsg("Checking file: $file", DEBUG);
        $command = sprintf('php -l %s 2>&1', escapeshellarg($file));
        $output  = [];
        $retVal  = exec($command, $output, $exitCode);

        $counts['check']++;
        if ($exitCode === 0) {
            // Lint passed.
            logmsg("No syntax error in file: $file", DEBUG);
            continue;
        }//end if

        $exit    = 1;
        $echoed  = false;
        $lines   = getLines($file);
        foreach ($output as $line) {
            // Error should read: PHP Parse error:  syntax error, unexpected '}' in /path/to/file.php on line 1
            $matched = preg_match("/Parse error:\s+(?P<error>.*) in (?P<file>.*) on line (?P<line>\d+)/", $line, $matches);
            if ($matched) {
                $relativePath = substr($file, 2);
                $line         = min($matches['line'], $lines);
                $echoed       = true;
                logmsg("Syntax error on {$relativePath}:{$line} - {$matches['error']}", ERROR);
                break;
            }//end if
        }//end foreach

        if ($echoed === false) {
            // Fallback if anything goes wrong.
            logmsg("Syntax error in {$file}", ERROR);
        }//end if
    } else {
        logmsg("Skipping file: {$file}", DEBUG);
        $counts['skip']++;
        continue;
    }//end if
}//end foreach

exit($exit);
