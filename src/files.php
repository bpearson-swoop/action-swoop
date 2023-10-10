<?php

class RecursiveDotFilterIterator extends \RecursiveFilterIterator
{

    /**
     * Check whether the current element of the iterator is acceptable.
     *
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        return substr($this->current()->getFilename(), 0, 1) !== '.';

    }//end accept()


}//end class


/**
 * Returns an iterator for all files in the current directory.
 *
 * @return \RecursiveIteratorIterator
 */
function getFiles()
{
    $rdi = new \RecursiveDirectoryIterator('.', \RecursiveDirectoryIterator::SKIP_DOTS);
    $rfi = new \RecursiveDotFilterIterator($rdi);
    $rii = new \RecursiveIteratorIterator($rfi);

    return $rii;

}//end getFiles()


/**
 * Get a line count from a file.
 *
 * @param string $file The file to get the line count from.
 *
 * @return int
 */
function getLines($file)
{
    $lines = 0;
    exec("wc -l $file", $output);
    if (!empty($output)) {
        $lines = (int) trim(explode(' ', $output[0])[0]);
    }//end if

    return $lines;

}//end getLines()
