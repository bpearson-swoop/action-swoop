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
            $contents = file_get_contents($file);
            $chars    = "-_0123456789\/+*^%$#@!~&:?.";
            foreach (str_word_count($contents, 2, $chars) as $pos => $word) {
                $word = $this->_clean($word);
                if (strlen($word) < 8) {
                    continue;
                }//end if

                if (preg_match('/^((sha|md)[0-9]{1,3}\-)/', $word) === 1) {
                    // Skip words starting with sha hash prefixes.
                    continue;
                }//end if

                if (strpos($word, 'data:') === 0
                    || (strpos($word, 'http') === 0 && @parse_url($word) !== false)
                    || (strpos($word, '/') !== false && file_exists($path.'/'.$word) === true)
                ) {
                    // Skip URLs/paths etc.
                    continue;
                }//end if

                $entropy = round($this->_shannon($word), 2);
                if ($entropy > 4.53) {
                    $results[] = [
                        "type"    => "error",
                        "file"    => ($path !== null) ? str_replace(realpath($path).'/', '', $file) : $file,
                        "lineno"  => substr_count($contents, "\n", 0, $pos),
                        "data"    => $word,
                        "entropy" => $entropy,
                        "message" => "Secret detected (Score: {$entropy}).",
                    ];
                }//end if
            }//end foreach
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
        $xml  = "<"."?xml version=\"1.0\" encoding=\"UTF-8\"?".">\n";
        $xml .= "<checkstyle version=\"8.0\">\n";
        $code = 0;
        $errors   = 0;
        $warnings = 0;
        if (empty($lines) === false) {
            $currFile = null;
            foreach ($lines as $line) {
                if ($currFile === null || $currFile !== $line['file']) {
                    if ($currFile !== null) {
                        $xml .= "    </file>\n";
                    }//end if

                    $currFile = $line['file'];
                    $xml     .= "    <file name=\"{$currFile}\">\n";
                }//end if

                $xml .= "        <{$line['type']} line=\"{$line['lineno']}\" severity=\"{$line['type']}\" message=\"{$line['message']}\" data=\"{$line['data']}\"/>\n";
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
     * Clean up the word.
     *
     * @param string $word The word to clean.
     *
     * @return string
     */
    private function _clean(string $word): string
    {
        // Remove hrefs.
        if (strpos($word, 'href=') === 0) {
            $word = str_ireplace('href=', '', $word);
        }//end if

        // Remove leading/trailing characters.
        $word = trim($word, " \t\n\r\"'`");

        // Remove known patterns.
        $word = str_ireplace('abcdefghijklmnopqrstuvwxyz', '', $word);
        $word = str_ireplace('abcdefghjklmnpqrstuvwxyz', '', $word);
        $word = str_ireplace('0123456789', '', $word);
        $word = str_ireplace('1234567890', '', $word);
        $word = str_ireplace('23456789', '', $word);

        return $word;

    }//end _clean()


    /**
     * Filter by file path.
     *
     * @param string $path The path to filter by.
     *
     * @return mixed
     */
    private function _filterByPath(string $path): mixed
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
