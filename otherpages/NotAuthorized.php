<?php
// BLP 2023-10-18 - This is the catch all file when someone tries to run one of my include file
// directly from the browser.

$site = $_GET['site'];
$page = $_GET['page'];
$ip = $_GET['ip'];
$agent = $_GET['agent'];
if(empty($agent)) $agent = "NO_AGENT";

error_log("NotAuthorized.php: Call from browser, Go Away: site=$site, page=$page, ip=$ip, agent=$agent");

echo <<<EOF
<h1>Not Authorized</h1>
<p>This page should only be referenced from another page on our site.</p>
<a href="https://bartonphillips.com">Return to our home page</a>
EOF;
exit();
