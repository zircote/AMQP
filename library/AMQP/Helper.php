<?php
namespace AMQP;
/**
 *
 */
/**
 *
 */
class Helper
{

    /**
     * @static
     *
     * @param $s
     */
    public static function debugMsg($s)
    {
        echo $s, PHP_EOL;
    }

    /**
     * @static
     *
     * @param $a
     *
     * @return string
     */
    public static function methodSig($a)
    {
        if (is_string($a)) {
            return $a;
        } else {
            return sprintf("%d,%d", $a[ 0 ], $a[ 1 ]);
        }
    }

    /**
     * @static
     *
     * @param $bytes
     */
    public static function saveBytes($bytes)
    {
        $fh = fopen('/tmp/bytes', 'wb');
        fwrite($fh, $bytes);
        fclose($fh);
    }

    /**
     * View any string as a hexdump.
     *
     * This is most commonly used to view binary data from streams
     * or sockets while debugging, but can be used to view any string
     * with non-viewable characters.
     *
     * @version     1.3.2
     * @author      Aidan Lister <aidan@php.net>
     * @author      Peter Waller <iridum@php.net>
     * @link        http://aidanlister.com/repos/v/function.hexdump.php
     *
     * @param       string  $data        The string to be dumped
     * @param       bool    $htmloutput  Set to false for non-HTML output
     * @param       bool    $uppercase   Set to true for uppercase hex
     * @param       bool    $return      Set to true to return the dump
     *
     * @return string
     */
    public static function hexdump($data, $htmloutput = true,
                                   $uppercase = false, $return = false)
    {
        $hexi = '';
        $ascii = '';
        $offset = 0;
        $len = strlen($data);
        $x = ($uppercase === false) ? 'x' : 'X';
        $dump = ($htmloutput === true) ? '<pre>' : '';

        for ($i = $j = 0; $i < $len; $i++) {
            $hexi .= sprintf("%02$x ", ord($data[ $i ]));

            if (ord($data[ $i ]) >= 32) {
                $ascii .= ($htmloutput === true) ?
                    htmlentities($data[ $i ]) :
                    $data[ $i ];
            } else {
                $ascii .= '.';
            }

            if ($j === 7) {
                $hexi .= ' ';
                $ascii .= ' ';
            }

            if (++$j === 16 || $i === $len - 1) {
                $dump .= sprintf("%04$x  %-49s  %s", $offset, $hexi, $ascii);

                $hexi = $ascii = '';
                $offset += 16;
                $j = 0;

                if ($i !== $len - 1) {
                    $dump .= PHP_EOL;
                }
            }
        }
        $dump .= $htmloutput === true ? '</pre>' : '';
        $dump .= PHP_EOL;
        if ($return === false) {
            echo $dump;
        }
        return $dump;
    }
}
