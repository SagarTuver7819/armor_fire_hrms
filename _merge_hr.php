<?php
$base = 'c:/xampp/htdocs/armor_new_hrms/hr/dashboard.php';
$html = 'c:/xampp/htdocs/armor_new_hrms/hr/_dash_html.php';
file_put_contents($base, file_get_contents($base) . file_get_contents($html));
echo "merged, lines=" . count(file($base)) . "\n";
@unlink($html);
@unlink('c:/xampp/htdocs/armor_new_hrms/_truncate_hr.php');
