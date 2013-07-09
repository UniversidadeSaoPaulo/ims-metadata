<?php 

/*
 * +----------------------------------------------------------------------+
 * | PHP Version 4                                                        |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2002-2005 Heinrich Stamerjohanns                       |
 * |                                                                      |
 * | oaidp-util.php -- Utilities for the OAI Data Provider                |
 * |                                                                      |
 * | This is free software; you can redistribute it and/or modify it under|
 * | the terms of the GNU General Public License as published by the      |
 * | Free Software Foundation; either version 2 of the License, or (at    |
 * | your option) any later version.                                      |
 * | This software is distributed in the hope that it will be useful, but |
 * | WITHOUT  ANY WARRANTY; without even the implied warranty of          |
 * | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the         |
 * | GNU General Public License for more details.                         |
 * | You should have received a copy of the GNU General Public License    |
 * | along with  software; if not, write to the Free Software Foundation, |
 * | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA         |
 * |                                                                      |
 * +----------------------------------------------------------------------+
 * | Derived from work by U. Müller, HUB Berlin, 2002                     |
 * |                                                                      |
 * | Written by Heinrich Stamerjohanns, May 2002                          |
 * |            stamer@uni-oldenburg.de                                   |
 * +----------------------------------------------------------------------+
 */
//
// $Id: oaidp-util.php,v 1.3 2010/05/13 15:08:35 jmg324 Exp $
//

function get_token() {
    list($usec, $sec) = explode(" ", microtime());
    return ((int)($usec*1000) + (int)($sec*1000));
}

function oai_error($code, $argument = '', $value = '') {
    global $request;
    global $request_err;

    switch ($code) {
        case 'badArgument' :
            $text = "The argument '$argument' (value='$value') included in the request is not valid.";
            break;

        case 'badGranularity' :
            $text = "The value '$value' of the argument '$argument' is not valid.";
            $code = 'badArgument';
            break;

        case 'badResumptionToken' :
            $text = "The resumptionToken '$value' does not exist or has already expired.";
            break;

        case 'badRequestMethod' :
            $text = "The request method '$argument' is unknown.";
            $code = 'badVerb';
            break;

        case 'badVerb' :
            $text = "The verb '$argument' provided in the request is illegal.";
            break;

        case 'cannotDisseminateFormat' :
            $text = "The metadata format '$value' given by $argument is not supported by this repository.";
            break;

        case 'exclusiveArgument' :
            $text = 'The usage of resumptionToken as an argument allows no other arguments.';
            $code = 'badArgument';
            break;

        case 'idDoesNotExist' :
            $text = "The value '$value' of the identifier is illegal for this repository.";
            if (!is_valid_uri($value)) {
                $code = 'badArgument';
            }
            break;

        case 'missingArgument' :
            $text = "The required argument '$argument' is missing in the request.";
            $code = 'badArgument';
            break;

        case 'noRecordsMatch' :
            $text = 'The combination of the given values results in an empty list.';
            break;

        case 'noMetadataFormats' :
            $text = 'There are no metadata formats available for the specified item.';
            break;

        case 'noVerb' :
            $text = 'The request does not provide any verb.';
            $code = 'badVerb';
            break;

        case 'noSetHierarchy' :
            $text = 'This repository does not support sets.';
            break;

        case 'sameArgument' :
            $text = 'Do not use them same argument more than once.';
            $code = 'badArgument';
            break;

        case 'sameVerb' :
            $text = 'Do not use verb more than once.';
            $code = 'badVerb';
            break;

        default:
            $text = "Unknown error: code: '$code', argument: '$argument', value: '$value'";
            $code = 'badArgument';
    }

    if ($code == 'badVerb' || $code == 'badArgument') {
        $request = $request_err;
    }
    $error = ' <error code="'.xmlstr($code).'">'.xmlstr($text)."</error>\n";
    return $error;
}

function xmlstr($string, $charset = 'utf-8', $xmlescaped = false) {
    $xmlstr = stripslashes(trim($string));
    // just remove invalid characters
    $pattern ="/[\x-\x8\xb-\xc\xe-\x1f]/";
    $xmlstr = preg_replace($pattern, '', $xmlstr);

    // escape only if string is not escaped
    if (empty($xmlescaped)) {
        $xmlstr = htmlspecialchars($xmlstr, ENT_QUOTES);
    }

    if ($charset != "utf-8") {
        $xmlstr = utf8_encode($xmlstr);
    }

    return $xmlstr;
}

// will split a string into elements and return XML
// supposed to print values from database
function xmlrecord($sqlrecord, $element, $attr = '', $indent = 0) {
    global $SQL;
    global $xmlescaped;
    global $charset;
    
    $str = '';
    
    if ($attr != '') { $attr = ' '.$attr; }
    if ($sqlrecord != '') {
        if (isset($SQL['split'])) {
            $temparr = explode($SQL['split'], $sqlrecord);
            foreach ($temparr as $val) {
                $str .= str_pad('', $indent).'<'.$element.$attr.'>'.xmlstr($val, $charset, $xmlescaped).'</'.$element.">\n";
            }
            return $str;
        } else {
            return str_pad('', $indent).'<'.$element.$attr.'>'.xmlstr($sqlrecord, $charset, $xmlescaped).'</'.$element.">\n";
        }
    } else {
        return '';
    }
}

function xmlelement($element, $attr = '', &$indent, $open = true) {
    global $SQL;
    
    if ($attr != '') { $attr = ' '.$attr; }
    if ($open) {
        $return = str_pad('', $indent).'<'.$element.$attr.'>'."\n";
        $indent += 2;
        return $return;
    } else {
        $indent -= 2;
        return str_pad('', $indent).'</'.$element.'>'."\n";
    }
}

// takes either an array or a string and outputs them as XML entities
function xmlformat($record, $element, $attr = '', $indent = 0)
{
    global $charset;
    global $xmlescaped;

    if ($attr != '') {
        $attr = ' '.$attr;
    }

    $str = '';
    if (is_array($record)) {
        foreach  ($record as $val) {
            $str .= str_pad('', $indent).'<'.$element.$attr.'>'.xmlstr($val, $charset, $xmlescaped).'</'.$element.">\n";
        }
        return $str;
    } elseif ($record != '') {
        return str_pad('', $indent).'<'.$element.$attr.'>'.xmlstr($record, $charset, $xmlescaped).'</'.$element.">\n";
    } else {
        return '';
    }
}

function date2UTCdatestamp($date, $granularity) {
    if ($date == NULL) return '';
    
    $datetime = new DateTime();
    $datetime->setTimestamp($date);

    switch ($granularity) {
        case 'YYYY-MM-DDThh:mm:ssZ':
            return $datetime->format('Y-m-d\TH:i:s\Z');
            break;
        case 'YYYY-MM-DD':
            return $datetime->format('Y-m-d');
            break;
        default: die("Unknown granularity!");
    }
}

function UTCdatestamp2date($value, $granularity) {
    if ($value == NULL) return '';
    $format = NULL;
    switch ($granularity) {
        case 'YYYY-MM-DDThh:mm:ssZ':
            $format = 'Y-m-d\TH:i:s\Z';
            break;
        case 'YYYY-MM-DD':
            $format = 'Y-m-d';
            break;
        default: die("Unknown granularity!");
    }
    
    $datetime = DateTime::createFromFormat($format, $value);
    if (!$datetime) {
        $datetime = DateTime::createFromFormat('YYYY-MM-DDThh:mm:ssZ', $value);
        if (!$datetime)  {
            $datetime = DateTime::createFromFormat('YYYY-MM-DD', $value);
        }
    }
    return $datetime->getTimestamp();
}

function checkDateFormat($value, $granularity) {
    global $message;
    $format = NULL;
    switch ($granularity) {
        case 'YYYY-MM-DDThh:mm:ssZ':
            $format = 'Y-m-d\TH:i:s\Z';
            break;
        case 'YYYY-MM-DD':
            $format = 'Y-m-d';
            break;
        default: die("Unknown granularity!");
    }
    if (DateTime::createFromFormat($format, $value)) {
        return 1;
    } else {
        $message = "Invalid Date Format: $value does not comply to the date format $granularity.";
        return 0;
    }
}

function formatDatestamp($datestamp) {
    global $granularity;
    
    $datestamp = date2UTCdatestamp($datestamp);
    if (!checkDateFormat($datestamp)) {
        if ($granularity == 'YYYY-MM-DD') {
            return '2002-01-01';
        } else {
            return '2002-01-01T00:00:00Z';
        }
    } else {
        return $datestamp;
    }
}

function oai_close($oaiolexp='') {
    global $compress;
    
    if (empty($oaiolexp)) { echo "</OAI-PMH>\n"; }
    
    if ($compress) {
        ob_end_flush();
    }
}

function oai_exit() {
    global $OAI_CONFIG;
    global $request;
    global $errors;

    header($OAI_CONFIG['CONTENT_TYPE']);
    echo $OAI_CONFIG['xmlheader'];
    echo $request;
    echo $errors;

    oai_close();
    exit();
}

function php_is_at_least($version) {
    list($c_r, $c_mj, $c_mn) = explode('.', phpversion());
    list($v_r, $v_mj, $v_mn) = explode('.', $version);

    if ($c_r >= $v_r && $c_mj >= $v_mj && $c_mn >= $v_mn) return TRUE;
    else return FALSE;
}

function is_valid_uri($url) {
    return((bool)preg_match("'^[^:]+:(?://)?(?:[a-z_0-9-]+[\.]{1})*(?:[a-z_0-9-]+\.)[a-z]{2,3}.*$'i", $url));
}

function metadataHeader($prefix) {
    global $METADATAFORMATS;
    global $XMLSCHEMA;

    $myformat = $METADATAFORMATS[$prefix];

    $str =
    '     <'.$prefix;
    if ($myformat['record_prefix']) {
        $str .= ':'.$myformat['record_prefix'];
    }
    $str .= "\n".
    '       xmlns';
    if (!$myformat['defaultnamespace']) {
        $str .= ":".$prefix;
    }

    $str .=
        '="'.$myformat['metadataNamespace'].'"'."\n";
    if ($myformat['record_prefix'] && $myformat['record_namespace']) {
        $str .=
        '       xmlns:'.$myformat['record_prefix'].'="'.$myformat['record_namespace'].'"'."\n";
    }
    $str .=
    '       xmlns:xsi="'.$XMLSCHEMA.'"'."\n".
    '       xsi:schemaLocation="'.$myformat['metadataNamespace'].
    '       '.$myformat['schema'].'">'."\n";

    return $str;
}

function convert_datestamp_if_needed(&$record) {
    global $SQL;
    if(!empty($SQL['datestamp_is_time'])) {
        //  The datestamp is actually a unix timestamp. Comvert it to a datestamp
        //  with the correct granularity
        $record['_'.$SQL['datestamp']] = $record[$SQL['datestamp']];
        $record[$SQL['datestamp']] = date('Y-m-d H:i:s', $record[$SQL['datestamp']]);
    }
}

function convert_date_to_time_if_needed_and_quote_if_date(&$date) {
    global $SQL;
    if(!empty($SQL['datestamp_is_time'])) {
        //  The datestamp is actually a unix timestamp in the db. Convert the
        //  datestamp we are using to search into a timestamp
        $date = strtotime($date);
    } else {
        $date = "'$date'";
    }
}

?>
