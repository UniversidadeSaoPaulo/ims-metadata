<?php
/**
 * +----------------------------------------------------------------------+
 * | PHP Version 4                                                        |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2002-2005 Heinrich Stamerjohanns                       |
 * |                                                                      |
 * | getrecord.php -- Utilities for the OAI Data Provider                 |
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

// parse and check arguments
foreach($args as $key => $val) {
    switch ($key) {
        case 'identifier':
            $identifier = $val;
            if (!is_valid_uri($identifier)) {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;
        case 'metadataPrefix':
            if (isset($OAI_CONFIG['METADATAFORMATS'][$val]) &&
                is_array($OAI_CONFIG['METADATAFORMATS'][$val]) && isset($OAI_CONFIG['METADATAFORMATS'][$val]['myhandler'])) {
                $metadataPrefix = $val;
                $inc_record  = $OAI_CONFIG['METADATAFORMATS'][$val]['myhandler'];
            } else {
                $errors .= oai_error('cannotDisseminateFormat', $key, $val);
            }
            break;
        default:
            $errors .= oai_error('badArgument', $key, $val);
    }
}

if (!isset($args['identifier'])) {
    $errors .= oai_error('missingArgument', 'identifier');
}
if (!isset($args['metadataPrefix'])) {
    $errors .= oai_error('missingArgument', 'metadataPrefix');
}

// remove the OAI part to get the identifier
if (empty($errors)) {
    if (!$record = get_post(str_replace($OAI_CONFIG['oaiprefix'], '', $identifier))) {
        $errors .= oai_error('idDoesNotExist', '', $identifier);
    }
}

// break and clean up on error
if ($errors != '') { oai_exit(); }

$output .= " <GetRecord>\n";

$status_deleted = false;
$identifier = $OAI_CONFIG['oaiprefix'].$record->ID;
$datestamp = date2UTCdatestamp(get_post_modified_time('U', false, $record), $OAI_CONFIG['granularity']);

if ($OAI_CONFIG['deletedRecord'] && $record->post_status == 'trash') {
    $status_deleted = true;
}
 
// print header
$output .='   <record>'."\n";
$output .='    <header';
if ($status_deleted) { $output .= ' status="deleted"'; }
$output .='>'."\n";

// use xmlrecord since we include stuff from database;
$output .= xmlrecord($identifier, 'identifier', '', 5);
$output .= xmlformat($datestamp, 'datestamp', '', 5);
if (!$status_deleted) {
    $categories = wp_get_object_terms($record->ID, 'category');
    foreach ($categories as $category) {
        if (isset($OAI_CONFIG['SETS'][$category->term_id])) {
            $output .= xmlrecord($OAI_CONFIG['SETS'][$category->term_id]['setSpec'], 'setSpec', '', 5);
        }
    }
}
$output .='    </header>'."\n"; 

// return the metadata record itself
if (!$status_deleted) {
    include('oai2/'.$inc_record);
}
$output .='  </record>'."\n"; 

// End GetRecord
$output .=' </GetRecord>'."\n"; 

?>
