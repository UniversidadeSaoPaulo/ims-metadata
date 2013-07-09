<?php 
/**
 * +----------------------------------------------------------------------+
 * | PHP Version 4                                                        |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2002-2005 Heinrich Stamerjohanns                       |
 * |                                                                      |
 * | oaidp-config.php -- Configuration of the OAI Data Provider           |
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

/*
 * This is the configuration file for the PHP OAI Data-Provider.
 * Please read through the WHOLE file, there are several things, that
 * need to be adjusted:

 - where to find the PEAR classes (look for PEAR SETUP)
 - parameters for your database connection (look for DATABASE SETUP)
 - the name of the table where you store your data
 - the encoding your data is stored (all below DATABASE SETUP)
 */

$OAI_CONFIG = array();
// The content-type the WWW-server delivers back. For debug-puposes, "text/plain"
// is easier to view. On a production site you should use "text/xml".
$OAI_CONFIG['CONTENT_TYPE'] = 'Content-Type: text/xml';

// do not change
$OAI_CONFIG['MY_URI'] = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];

// MUST (only one)
// please adjust
$OAI_CONFIG['repositoryName'] = get_site_option('site_name', $_SERVER['SERVER_NAME']); //$SITE->fullname;
$OAI_CONFIG['baseURL'] = $OAI_CONFIG['MY_URI'];

// You can use a static URI as well.
// $baseURL 			= "http://my.server.org/oai/oai2.php";
// do not change
$OAI_CONFIG['protocolVersion'] = '2.0';

// How your repository handles deletions
// no: 			The repository does not maintain status about deletions.
//				It MUST NOT reveal a deleted status.
// persistent:	The repository persistently keeps track about deletions
//				with no time limit. It MUST consistently reveal the status
//				of a deleted record over time.
// transient:   The repository does not guarantee that a list of deletions is
//				maintained. It MAY reveal a deleted status for records.
//
// If your database keeps track of deleted records change accordingly.
// Currently if $record['deleted'] is set to 'false', $status_deleted is set.
// Some lines in listidentifiers.php, listrecords.php, getrecords.php
// must be changed to fit the condition for your database.
$OAI_CONFIG['deletedRecord'] = false;

// MAY (only one)
// granularity is days $granularity = 'YYYY-MM-DD';
// granularity is seconds
$OAI_CONFIG['granularity'] = 'YYYY-MM-DDThh:mm:ssZ';

// MUST (only one)
// the earliest datestamp in your repository,
// please adjust
$OAI_CONFIG['earliestDatestamp'] = '2006-10-25T00:00:00Z';

// MUST (multiple)
// please adjust
$OAI_CONFIG['adminEmail'] = get_site_option('admin_email', 'cotactemail');

// MAY (multiple)
// Comment out, if you do not want to use it.
// Currently only gzip is supported (you need output buffering turned on,
// and php compiled with libgz).
// The client MUST send "Accept-Encoding: gzip" to actually receive
// compressed output.
$OAI_CONFIG['compression'] = array('gzip');

// MUST (only one)
// should not be changed
$OAI_CONFIG['delimiter'] = ':';

// MUST (only one)
// You may choose any name, but for repositories to comply with the oai
// format for unique identifiers for items records.
// see: http://www.openarchives.org/OAI/2.0/guidelines-oai-identifier.htm
// Basically use domainname-word.domainname
// please adjust
$OAI_CONFIG['repositoryIdentifier'] = str_replace('http://', '', get_site_url());

// description is defined in identify.php
$OAI_CONFIG['show_identifier'] = true;

// You maximum mumber of the records to deliver
// If there are more records to deliver
// a ResumptionToken will be generated.
$OAI_CONFIG['MAXRECORDS'] = 10;

// maximum mumber of identifiers to deliver
// If there are more identifiers to deliver
// a ResumptionToken will be generated.
$OAI_CONFIG['MAXIDS'] = 20;

// After 24 hours resumptionTokens become invalid.
//$tokenValid = 24*3600;
//$expirationdatetime = gmstrftime('%Y-%m-%dT%TZ', time()+$tokenValid);

// define all supported sets in your repository
$parent_id = 0;
$OAI_CONFIG['SETS'] = array ();
if (isset($IMSMD_CATEGORY) && !empty($IMSMD_CATEGORY)) {
    foreach ($IMSMD_CATEGORY as $category) {
        $parent_id = wp_create_category($category, $parent_id);
    }
}
$cat_args = array('type'=>'post', 'child_of'=>$parent_id, 'hierarchical'=>1, 'hide_empty'=>0, 'parent'=>$parent_id);
foreach (get_terms('category', $cat_args) as $parent_category) {
    $cat_args['parent'] = $parent_category->term_id;
    $cat_args['child_of'] = $parent_category->term_id;
    foreach (get_terms('category', $cat_args) as $category) {
        $OAI_CONFIG['SETS'][$category->term_id] = array('setSpec'=>$parent_category->slug.':'.$category->slug,
                                                        'setName'=>$parent_category->name.' '.$category->name,
                                                        'category__in'=>array($category->term_id));
    }
}

// define all supported metadata formats
// myhandler is the name of the file that handles the request for the
// specific metadata format.
// [record_prefix] describes an optional prefix for the metadata
// [record_namespace] describe the namespace for this prefix
$OAI_CONFIG['METADATAFORMATS'] = array (
    'oai_dc' => array(
        'metadataPrefix'=>'oai_dc',
        'schema'=>'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
        'metadataNamespace'=>'http://www.openarchives.org/OAI/2.0/oai_dc/',
        'myhandler'=>'record_dc.php',
        'record_prefix'=>'dc',
        'defaultnamespace'=>false,
        'record_namespace'=>'http://purl.org/dc/elements/1.1/'),
    //'oai_lom' => array(
    //    'metadataPrefix'=>'oai_lom',
    //    'schema'=>'http://ltsc.ieee.org/xsd/lomv1.0/lom.xsd',
    //    'metadataNamespace'=>'http://ltsc.ieee.org/xsd/LOM',
    //    'myhandler'=>'record_lom.php',
    //    'record_prefix'=>'lom',
    //    'defaultnamespace'=>false,
    //    'record_namespace'=>'http://ltsc.ieee.org/xsd/LOM'),
    'oai_imsmd' => array(
        'metadataPrefix'=>'oai_imsmd',
        'schema'=>'http://www.imsglobal.org/xsd/imsmd_loose_v1p3p2.xsd',
		'metadataNamespace'=>'http://ltsc.ieee.org/xsd/LOM',
		'myhandler'=>'record_imsmd.php',
        'record_prefix'=>'imsmd',
        'defaultnamespace'=>false,
        'record_namespace'=>'http://ltsc.ieee.org/xsd/LOM')
);

// the charset you store your metadata in your database
// currently only utf-8 and iso8859-1 are supported
$OAI_CONFIG['charset'] = "utf-8";

// if entities such as < > ' " in your metadata has already been escaped
// then set this to true (e.g. you store < as &lt; in your DB)
$OAI_CONFIG['xmlescaped'] = false;


// this is your external (OAI) identifier for the item
// this will be expanded to
// oai:$repositoryIdentifier:$idPrefix$SQL['identifier']
// should not be changed
$OAI_CONFIG['oaiprefix'] = "oai".$OAI_CONFIG['delimiter'].$OAI_CONFIG['repositoryIdentifier'].$OAI_CONFIG['delimiter'].'res.';

// this is the default options in search using wp
$OAI_CONFIG['wp_options'] = array();
$OAI_CONFIG['wp_options']['paged'] = 0;
$OAI_CONFIG['wp_options']['post_type'] = array();
foreach(get_option('imsmd_enable', array()) as $key=>$value) {
    if ($value['select'] == 'enable') {
        array_push($OAI_CONFIG['wp_options']['post_type'], $key);
    }
}
$OAI_CONFIG['wp_options']['post_status'] = array('publish', 'inherit');
if ($OAI_CONFIG['deletedRecord']) {
    array_push($OAI_CONFIG['wp_options']['post_status'], 'trash');
}
$OAI_CONFIG['wp_options']['posts_per_page'] = $OAI_CONFIG['MAXIDS'];

// Current Date
$datetime = gmstrftime('%Y-%m-%dT%T');
$responseDate = $datetime.'Z';

// Header dont change
$XMLHEADER = '<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">'."\n";
$OAI_CONFIG['xmlheader'] = $XMLHEADER.' <responseDate>'.$responseDate."</responseDate>\n";

// the xml schema namespace, do not change this
$OAI_CONFIG['XMLSCHEMA'] = 'http://www.w3.org/2001/XMLSchema-instance';

?>
