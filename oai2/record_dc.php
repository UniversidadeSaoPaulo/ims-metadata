<?php
require_once('oai2/metadata.class.php');
// please change to the according metadata prefix you use
$prefix = 'oai_dc';
$myformat = $OAI_CONFIG['METADATAFORMATS'][$prefix];

$atts = array('xmlns:xsi' => $OAI_CONFIG['XMLSCHEMA'],
              'xsi:schemaLocation' => $myformat['metadataNamespace'].' '.$myformat['schema'],
              'xmlns:oai_dc' => $myformat['metadataNamespace'],
              'xmlns:dc' => $myformat['record_namespace']);

if (!isset($lom)) { // allows reuse of the class for listrecords
    $lom = new metadata('oai', $prefix, $atts, 2);
}

$output .= $lom->get_metadata($record, 'dc');

?>
