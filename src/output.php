<?php


/**
 * Log a message based on the level.
 *
 * @param string $message The message to log.
 * @param int    $level   The level of the message.
 *
 * @return void
 */
function logmsg($message, $level)
{
    global $msgLevel;

    if ($level <= $msgLevel) {
        echo $message . "\n";
    }//end if

}//end logmsg()
