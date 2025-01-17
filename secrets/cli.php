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
//$secret->minEntropy = 3.5;
$secret->ignoreDirs[] = 'sankey';
$secret->ignoreDirs[] = 'HTMLCS';
$secret->ignoreDirs[] = 'hc';
$secret->ignoreDirs[] = 'simplesaml';
$secret->ignoreDirs[] = 'saml';
$secret->ignoreDirs[] = 'font-awesome';
$secret->ignoreDirs[] = 'font-swoop';
$secret->ignoreDirs[] = 'miners';
$secret->ignoreDirs[] = 'PHPMailer';
$secret->ignoreDirs[] = 'solution';
$secret->ignoreDirs[] = 'debug';
$secret->ignoreDirs[] = 'release';
$secret->ignoreDirs[] = 'code-coverage';
$secret->ignoreFiles[] = '.min.';
$secret->ignoreFiles[] = '.compiled.';
$secret->ignoreFiles[] = '.bundle.';
$secret->ignoreFiles[] = 'styles.css';

$files = $secret->getFiles($path);
$lines = $secret->run($files, $path);
list($code, $csxml, $xml) = $secret->output($lines);
echo "{$xml}\n";
exit($code);
