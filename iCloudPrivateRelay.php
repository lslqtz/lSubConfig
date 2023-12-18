<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	die("File not found.\n");
}
$iCloudIPRangeCSV = file('https://mask-api.icloud.com/egress-ip-ranges.csv');
echo "payload:\n";
foreach ($iCloudIPRangeCSV as $iCloudIPLine) {
	$iCloudIP = explode(',', $iCloudIPLine, 2);
	if (count($iCloudIP) < 1) {
		continue;
	}
	echo "    - '{$iCloudIP[0]}'\n";
}
?>
