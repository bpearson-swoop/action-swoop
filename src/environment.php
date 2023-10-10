<?php

function environment($name, $default=null)
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    switch ($name) {
    case 'INPUT_PHP_FILE_EXTENSIONS':
        $value = explode(',', $value);
        break;
    default:
        // Nothing.
        break;
    }//end switch

    return $value;
}
