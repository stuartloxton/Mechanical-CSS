<?php
	header('Content-type: text/css');
include('../lib/mcss.php');
$css = new MCSS('example.css', '../examples');
echo strlen($css->css)."\n\n\n".$css->css;
?>