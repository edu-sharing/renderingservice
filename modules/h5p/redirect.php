<?php
require_once __DIR__ . '/../../conf/system.conf.php';
$pathArr = explode('modules/h5p', $_REQUEST['ID']);
$path = $CC_RENDER_PATH . '/h5p' . $pathArr[1];

//check if requested path is child of h5p cache directory
if(strpos(realpath($path), realpath($CC_RENDER_PATH . '/h5p') === false))
    throw new Exception('Access not allowed');

$filesize = filesize($path);

$path_parts = pathinfo($path);
if($path_parts['extension'] === 'css') {
    header("Content-type: text/css");
} else {
    header('Content-type: ' . mime_content_type($path));
}
header('Content-length: ' . $filesize);
header('Access-Control-Allow-Origin: *');


if($path_parts['extension'] != 'mp4') {
    echo file_get_contents($path);
} else {
    // TAKEN FROM: http://mobiforge.com/developing/story/content-delivery-mobile-devices
    $file = $path;
    $fp = @fopen($file, 'rb');

    $size = filesize($file);
    // File size
    $length = $size;
    // Content length
    $start = 0;
    // Start byte
    $end = $size - 1;
    // End byte
    // Now that we've gotten so far without errors we send the accept range header
    /* At the moment we only support single ranges.
     * Multiple ranges requires some more work to ensure it works correctly
     * and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
     *
     * Multirange support annouces itself with:
     * header('Accept-Ranges: bytes');
     *
     * Multirange content must be sent with multipart/byteranges mediatype,
     * (mediatype = mimetype)
     * as well as a boundry header to indicate the various chunks of data.
     */
    header("Accept-Ranges: 0-$length");
    // header('Accept-Ranges: bytes');
    // multipart/byteranges
    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
    if (isset($_SERVER['HTTP_RANGE'])) {

        $c_start = $start;
        $c_end = $end;
        // Extract the range string
        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        // Make sure the client hasn't sent us a multibyte range
        if (strpos($range, ',') !== false) {

            // (?) Shoud this be issued here, or should the first
            // range be used? Or should the header be ignored and
            // we output the whole content?
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            // (?) Echo some info to the client?
            exit ;
        }
        // If the range starts with an '-' we start from the beginning
        // If not, we forward the file pointer
        // And make sure to get the end byte if spesified
        if ($range == '-') { //fix
            // The n-number of the last bytes is requested
            $c_start = $size - substr($range, 1);
        } else {

            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }
        /* Check the range and make sure it's treated according to the specs.
         * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
         */
        // End bytes can not be larger than $end.
        $c_end = ($c_end > $end) ? $end : $c_end;
        // Validate the requested range and return an error if it's not correct.
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {

            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            // (?) Echo some info to the client?
            exit ;
        }
        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;
        // Calculate new content length
        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
    }
    // Notify the client the byte range we'll be outputting
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $length");

    // Start buffered download
    $buffer = 1024 * 8;
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {

        if ($p + $buffer > $end) {

            // In case we're only outputtin a chunk, make sure we don't
            // read past the length
            $buffer = $end - $p + 1;
        }
        set_time_limit(0);
        // Reset time limit for big files
        echo fread($fp, $buffer);
        flush();
        // Free up memory. Otherwise large files will trigger PHP's memory limit.
    }

    fclose($fp);
}

exit(0);
