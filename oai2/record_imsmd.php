<?php
require_once('oai2/metadata.class.php');
// please change to the according metadata prefix you use
$prefix = 'oai_imsmd';
$myformat = $OAI_CONFIG['METADATAFORMATS'][$prefix];

$atts = array('xmlns:xsi' => $OAI_CONFIG['XMLSCHEMA'],
              'xsi:schemaLocation' => $myformat['metadataNamespace'].' '.$myformat['schema']);
if (!$myformat['defaultnamespace']) {
    $atts['xmlns:'.$prefix] = $myformat['metadataNamespace'];
}
if ($myformat['record_prefix'] && $myformat['record_namespace']) {
    $atts['xmlns:'.$myformat['record_prefix']] = $myformat['record_namespace'];
}

if (!isset($lom)) { // allows reuse of the class for listrecords
    $lom = new metadata('oai', $prefix, $atts, 1);
}

$output .= $lom->get_metadata($record, 'lom');

?>
