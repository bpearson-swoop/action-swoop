<?php
/**
 * Secret detection.
 *
 * Uses Shannon Entropy to detect secrets in code.
 * Can have a side effect of false positives if lines of code are too long,
 * which is also an opportunity to refactor the code.
 */
class Secret
{

    /**
     * Ignore directories (with defaults).
     *
     * @var array
     */
    public $ignoreDirs = [
        ".git",
        "node_modules",
        "vendor",
        "dist",
        "tests",
    ];

    /**
     * Ignore files (with defaults).
     *
     * @var array
     */
    public $ignoreFiles = [
        "sri_checksums.json",
        "package-lock.json",
        "composer-lock.json",
    ];

    /**
     * File extensions to check.
     *
     * @var array
     */
    public $extensions = [
        "php",
        "js",
        "html",
        "txt",
        "md",
        "json",
        "yml",
        "yaml",
        "xml",
    ];


    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {

    }//end __construct()


    /**
     * Return a list of files from a path.
     *
     * @param string $path The path to search.
     *
     * @return array
     */
    public function getFiles(string $path): array
    {
        $files = [];
        if (is_dir($path)) {
            $dir = new DirectoryIterator($path);
            foreach ($dir as $fileinfo) {
                if ($fileinfo->isDot() === true) {
                    continue;
                } else if ($fileinfo->isDir() === true && in_array($fileinfo->getFilename(), $this->ignoreDirs) === true) {
                    continue;
                } else if ($fileinfo->isDir() === true) {
                    $files = array_merge($files, $this->getFiles($fileinfo->getPathname()));
                } else if ($fileinfo->isFile() === true
                    && $this->_filterByPath($fileinfo->getFilename()) === false
                ) {
                    continue;
                } else if ($fileinfo->isFile() === true && in_array($fileinfo->getExtension(), $this->extensions) === true) {
                    $files[] = str_replace('//', '/', $path.'/'.$fileinfo->getFilename());
                }//end if
            }//end foreach
        } else if (is_file($path)) {
            $files[] = $path;
        }//end if

        return $files;

    }//end getFiles()


    /**
     * Get the files changed in the PR.
     *
     * @param string $pr The PR number.
     *
     * @return array
     */
    public function getPRFiles(string $pr): array
    {
        $output  = [];
        $command = sprintf('gh pr diff %s --name-only ', escapeshellarg($pr));
        $retVal  = exec($command, $output, $exitCode);

        $clean = [];
        foreach ($output as $file) {
            $file = trim($file);
            if (empty($file) === false && $this->_filterByPath($file) !== false) {
                $clean[] = $file;
            }//end if
        }//end foreach

        return $clean;

    }//end getPRFiles()


    /**
     * Run the Secrets check.
     *
     * @param array $files The files to check.
     * @param mixed $path  The path used to clean up the file path.
     *
     * @return array
     */
    public function run(array $files, mixed $path=null): array
    {
        $results = [];
        foreach ($files as $file) {
            $fh  = fopen($file, "r");
            $pos = 0;
            while (($line = fgets($fh)) !== false) {
                $pos++;
                $clean   = $this->_clean($line);
                $entropy = round($this->_shannon($clean), 2);
                if ($entropy > 5.1) {
                    if (strpos($line, 'integrity="sha') !== false) {
                        // Ignore SSI hashes.
                        continue;
                    }//end if

                    $message = "Secret detected (Score: {$entropy}).";
                    $type    = "error";
                    if (strlen($line) > 120) {
                        $message .= " Possibly a false positive, the line might need refactoring.";
                        $type     = "warning";
                    }//end if

                    $results[] = [
                        "type"    => $type,
                        "file"    => ($path !== null) ? str_replace(realpath($path).'/', '', $file) : $file,
                        "line"    => $line,
                        "lineno"  => $pos,
                        "data"    => $clean,
                        "entropy" => $entropy,
                        "message" => $message,
                    ];
                }//end if
            }//end while

            fclose($fh);
        }//end foreach

        return $results;

    }//end run()


    /**
     * Output the results in Checkstyle format.
     *
     * @param array $lines The lines to output.
     *
     * @return array
     */
    public function output(array $lines): array
    {
        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<checkstyle version=\"8.0\">\n";
        $code = 0;
        if (empty($lines) === false) {
            $currFile = null;
            $errors   = 0;
            $warnings = 0;
            foreach ($lines as $line) {
                if ($currFile === null || $currFile !== $line['file']) {
                    if ($currFile !== null) {
                        $xml .= "    </file>\n";
                    }//end if

                    $currFile = $line['file'];
                    $xml     .= "    <file name=\"{$currFile}\">\n";
                }//end if

                $xml .= "        <{$line['type']} line=\"{$line['lineno']}\" severity=\"{$line['type']}\" message=\"{$line['message']}\" />\n";
                if ($line['type'] === 'error') {
                    $errors++;
                    $code = 1;
                } else {
                    $warnings++;
                }//end if
            }//end foreach

            $xml .= "    </file>\n";
        }//end if

        $xml .= '</checkstyle>'."\n";
        $xml  = str_replace('<checkstyle ', "<checkstyle errors=\"{$errors}\" warnings=\"{$warnings}\" ", $xml);

        $csxml = tempnam(sys_get_temp_dir(), 'cs');
        file_put_contents($csxml, $xml);

        return [$code, $csxml, $xml];

    }//end output()


    /**
     * Clean a line.
     *
     * @param string $line The line to clean.
     *
     * @return string
     */
    private function _clean(string $line): string
    {
        // Remove HTML tags.
        $line = strip_tags($line);

        // Remove obvious false positives.
        $line = preg_replace("#(\\\$_(GET|POST|REQUEST|COOKIE|SERVER|ENV|FILES|SESSION|GLOBALS)\[([^\]]*)\])#", "", $line);
        $line = preg_replace("#((varchar|character varying|decimal|float|numeric)\([0-9]+\))#i", "", $line);
        $line = str_ireplace(["CREATE", "TEMPORARY", "TABLE", "NOT", "NULL", "FROM", "WHERE", "GROUP BY", "ORDER BY", "DEFAULT", "UPDATE", "SELECT", " AS ", "ILIKE", "LIKE"], "", $line);
        $line = str_ireplace(["mozilla/", "gecko/", "firefox/", "chrome/", "applewebkit/", "safari/"], "", $line);
        $line = str_ireplace(["samsung", "browser", "html", "mobile", "build", "safari/"], "", $line);
        $line = str_ireplace(["windows", "osx", "linux", "android", "ios"], "", $line);
        $line = str_ireplace(["http://", "https://", "ftp://", "file://", "data://", "mailto:"], "", $line);
        $line = str_ireplace(["&nbsp;", "&lt;", "&gt;", "&amp;", "&quot;", "&apos;"], "", $line);

        // Removing some obvious tokens (PHP, JS, etc).
        $line = str_ireplace(["<?php", "<?=", "<?", "?>"], "", $line);
        $line = str_ireplace(["//", "/*", "*/", "#", "/*", "*/"], "", $line);
        $line = str_ireplace([" return ", " = ", " == ", " === ", " != ", " !== ", " && ", " || ", " + ", " - ", " * ", " / ", " % ", " < ", " > ", " <= ", " > ", '->'], " ", $line);
        $line = str_ireplace([" ", "?", "\"", "'", ">", "<", ".", ",", ":", ";", "`", "(", ")", "[", "]", "{", "}", "%"], "", $line);
        $line = str_ireplace(['$this', "self::", "parent::", "static::"], "", $line);
        $line = str_ireplace(["curl_setopt", "curl_init", "curl_"], "", $line);

        // Remove sequences/false positives.
        $line = str_ireplace('abcdefghijklmnopqrstuvwxyz', '', $line);
        $line = str_ireplace('23456789', '', $line);
        $line = str_ireplace('abcdefghjklmmnpqrstuvwxyz', '', $line);
        $line = str_ireplace('0123456789', '', $line);
        $line = str_ireplace('1234567890', '', $line);
        $line = str_ireplace([" 0 ", " 1 ", " 2 ", " 3 ", " 4 ", " 5 ", " 6 ", " 7 ", " 8 ", " 9 "], " ", $line);

        return $line;

    }//end _clean()


    /**
     * Filter by file path.
     *
     * @param string $path The path to filter by.
     *
     * @return string|boolean
     */
    private function _filterByPath(string $path): string|bool
    {
        foreach ($this->ignoreDirs as $dir) {
            if (strpos($path, "/{$dir}/") !== false) {
                return false;
            }//end if
        }//end foreach

        foreach ($this->ignoreFiles as $file) {
            if (strpos($path, $file) !== false) {
                return false;
            }//end if
        }//end foreach

        return $path;

    }//end _filterByPath()


    /**
     * Get a temporary file.
     *
     * @return string
     */
    private function _getTempFile(): string
    {
        $suffix = '.xml';
        $sfile  = null;
        do {
            if (!empty($sfile) && file_exists($sfile)) {
                unlink($sfile);
            }//end if

            $file  = tempnam(sys_get_temp_dir(), 'secrets');
            $sfile = $file.$suffix;

            $fp = @fopen($sfile, 'x');
        } while (!$fp);

        fclose($fp);
        unlink($file);

        return $sfile;

    }//end _getTempFile()


    /**
     * Calculate Shannon Entropy for the given string.
     *
     * @param string $str The string to calculate entropy for.
     *
     * @return float
     */
    private function _shannon(string $str): float
    {
        $e  = 0.0;
        $ln = strlen($str);
        if ($ln === 0) {
            // Sanity check.
            return $e;
        }//end if

        foreach (count_chars($str, 1) as $cnt) {
            $p  = ($cnt / $ln);
            $e -= ($p * log((float) $p, 2));
        }

        return $e;

    }//end _shannon()


}//end class
