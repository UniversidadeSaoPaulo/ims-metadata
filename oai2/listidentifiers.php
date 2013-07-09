<?php
/**
 * +----------------------------------------------------------------------+
 * | PHP Version 4                                                        |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2002-2005 Heinrich Stamerjohanns                       |
 * |                                                                      |
 * | listidentifiers.php -- Utilities for the OAI Data Provider           |
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

$page = 0;
$options = $OAI_CONFIG['wp_options']; 
unset($from); unset($until);

// Resume previous session?
if (isset($args['resumptionToken'])) {
    if (count($args) > 1) { // overwrite all other errors
        $errors = oai_error('exclusiveArgument');
    } else {
        $set_resumptionToken = TRUE;
        $args = unserialize(base64_decode($args['resumptionToken']));
        $options['offset'] = $OAI_CONFIG['MAXIDS'] * $args['page'];
        $page = $args['page'];
        unset($args['page']);
    }
} else { // no, new session
    if (!isset($args['metadataPrefix'])) {
        $errors .= oai_error('missingArgument', 'metadataPrefix');
    }
}

// parse and check arguments
foreach($args as $key => $val) {
    switch ($key) {
        case 'from': // prevent multiple from
            if (!checkDateFormat($val, $OAI_CONFIG['granularity'])) {
                $errors .= oai_error('badGranularity', 'from', $val);
            } else {
                $from = UTCdatestamp2date($val, $OAI_CONFIG['granularity']);
            }
            break;
        case 'until': // prevent multiple until
            if (!checkDateFormat($val, $OAI_CONFIG['granularity'])) {
                $errors .= oai_error('badGranularity', 'until', $val);
            } else {
                $until = UTCdatestamp2date($val, $OAI_CONFIG['granularity']);
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
        case 'set':
            foreach ($OAI_CONFIG['SETS'] as $set_id=>$SET) {
                if ($SET['setSpec'] == $val) {
                    $options['category__in'] = $SET['category__in'];
                    break;
                }
            }
            if (!isset($options['category__in']) || empty($options['category__in'])) {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;
        case 'resumptionToken':
            if (!isset($resumptionToken)) {
                $resumptionToken = $val;
            } else {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;
        default:
            $errors .= oai_error('badArgument', $key, $val);
    }
}

if (empty($errors)) {
    if (isset($from) || isset($until)) {
        add_filter('posts_where', array('IMSMetadata', 'filter_where'));
    }
    $query = new WP_Query($options);
    $records = $query->get_posts();
    if (isset($from) || isset($until)) {
        remove_filter('posts_where', array('IMSMetadata', 'filter_where'));
    }
    
    if (empty($records)) {
        $errors .= oai_error('noRecordsMatch');
    }
}

// break and clean up on error
if ($errors != '') { oai_exit(); }

$output .= " <ListIdentifiers>\n";    
// Will we need a ResumptionToken?
$num_rows = $query->found_posts;
if ($num_rows > $OAI_CONFIG['MAXIDS'] && (($page+1)*$OAI_CONFIG['MAXIDS']) < $num_rows) {
    $args['page'] = (int) $page + 1;
    $token = base64_encode(serialize($args));
    $restoken = '  <resumptionToken 
     completeListSize="'.$num_rows.'"
     cursor="'.$page * $OAI_CONFIG['MAXIDS'].'">'.$token."</resumptionToken>\n";
} // Last delivery, return empty ResumptionToken
elseif (isset($set_resumptionToken) && $set_resumptionToken) {
    $restoken = '  <resumptionToken completeListSize="'.$num_rows.'"
     cursor="'.$page * $OAI_CONFIG['MAXIDS'].'"></resumptionToken>'."\n";
}

foreach ($records as $record) {
    $status_deleted = false;
    
    $identifier = $OAI_CONFIG['oaiprefix'].$record->ID;
    $datestamp = date2UTCdatestamp(get_post_modified_time('U', false, $record), $OAI_CONFIG['granularity']);
    if ($OAI_CONFIG['deletedRecord'] && $record->post_status == 'trash') {
        $status_deleted = true;
    }
    
    $output .='  <header';
    if ($status_deleted) { $output .= ' status="deleted"'; }
    $output .='>'."\n";
    $output .= xmlrecord($identifier, 'identifier', '', 3);
    $output .= xmlformat($datestamp, 'datestamp', '', 3);
    if (!$status_deleted) {
        $categories = wp_get_object_terms($record->ID, 'category');
        foreach ($categories as $category) {
            if (isset($OAI_CONFIG['SETS'][$category->term_id])) {
                $output .= xmlrecord($OAI_CONFIG['SETS'][$category->term_id]['setSpec'], 'setSpec', '', 3);
            }
        }
    }
    $output .='  </header>'."\n"; 
}

// ResumptionToken
if (isset($restoken)) {
    $output .= $restoken;
}

$output .= " </ListIdentifiers>\n";

?>
