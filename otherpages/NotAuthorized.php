<?php
// BLP 2023-10-18 - This is the catch all file when someone tries to run one of my include file
// directly from the browser.

$ip = $_SERVER['REMOTE_ADDR'];
$agent = $_SERVER['HTTP_USER_AGENT'];
$self = htmlentities($_SERVER['PHP_SELF']);
$requestUri = $_SERVER['REQUEST_URI'];

error_log("NotAuthorized.php: Call from browser, Go Away: $ip, $self, $requestUri, $agent");
echo <<<EOF
<h1>Not Authorized</h1>
<p>This page should only be referenced from another page on our site.</p>
<a href="https://bartonphillips.com">Return to our home page</a>
EOF;
exit();
