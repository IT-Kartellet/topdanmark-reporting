<?php
$filename ="excelreport.xls";
$contents = isset($_GET['data']) ? $_GET['data'] : "ERROR: NO DATA";
header('Content-type: application/ms-excel');
header('Content-Disposition: attachment; filename='.$filename);
echo $contents;
?>