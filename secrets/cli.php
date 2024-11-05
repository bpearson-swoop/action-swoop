<?php
/**
 * Calculate Shannon Entropy for the given path.
 *
 */
require_once __DIR__.'/secret.php';

// Check for input.
$path = $argv[1] ?? null;
if (file_exists($path) === false) {
    echo "Path not found\n";
    exit(1);
}//end if

// Initialise.
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

$files = $secret->getFiles($path);
$lines = $secret->run($files, $path);
[$code, $csxml, $xml] = $secret->output($lines);
echo "{$xml}\n";
exit($code);
