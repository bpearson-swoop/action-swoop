<?php

require_once dirname(__DIR__).'/src/defines.php';
require_once dirname(__DIR__).'/src/environment.php';
require_once dirname(__DIR__).'/src/files.php';
require_once dirname(__DIR__).'/src/git.php';
require_once dirname(__DIR__).'/src/output.php';
require_once __DIR__.'/secret.php';

// Defaults.
$msgLevel = INFO;

// Environment variables.
$msgLevel = environment('MSGLEVEL', $msgLevel);

logmsg("Note: In GH, there is a limit 10 errors and 10 warnings per job", INFO);

$exitCode = 0;

// PR number.
$pr = ($argv[1] ?? null);
if ($pr === null) {
    logmsg("No PR number provided", ERROR);
    exit(1);
}

$secret = new Secret();
$secret->ignoreDirs[] = 'sankey';
$secret->ignoreDirs[] = 'HTMLCS';
$secret->ignoreDirs[] = 'hc';
$secret->ignoreDirs[] = 'simplesaml';
$secret->ignoreDirs[] = 'saml';
$secret->ignoreDirs[] = 'font-awesome';
$secret->ignoreDirs[] = 'font-swoop';
$secret->ignoreDirs[] = 'miners';
$secret->ignoreDirs[] = 'PHPMailer';
$secret->ignoreFiles[] = '.min.';
$secret->ignoreFiles[] = '.compiled.';
$secret->ignoreFiles[] = '.bundle.';

$files = $secret->getPRFiles($pr);
$lines = $secret->run($files);
if ($msgLevel == 8) {
    // Extra ddebugging help.
    logmsg("Files changed", DEBUG);
    logmsg(implode("\n", $files), DEBUG);

    logmsg("Secrets output", DEBUG);
    logmsg(implode("\n", $lines), DEBUG);
}//end if

[$code, $csxml, $xml] = $secret->output($lines);
$output  = [];
$command = sprintf('cs2pr %s', escapeshellarg($csxml));
logmsg("Command: {$command}", DEBUG);
$retVal  = exec($command, $output);
if (empty($lines) === true) {
    // Secrets passed.
    logmsg("Secrets success", INFO);
} else {
    logmsg("Secrets failed", ERROR);
}//end if

exit($core);
