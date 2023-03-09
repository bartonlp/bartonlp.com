<?php
$_site = require_once(getenv("SITELOADNAME"));
$whatis = new whatis\WhatIsLoaded($_site);
$S = new SiteClass($_site);
$S->title = "Get Versions";
$S->banner = "<h1>Get Versions</h1>";
$S->css = "td { padding: 0 10px; }";

[$top, $footer] = $S->getPageTopBottom();

$ret = $whatis->getInfo();

foreach($ret as $k=>$v) {
  $msg .= "<tr><td>$k</td><td>$v</td></tr>";
}

$msg = <<<EOF
<table border="1">
<tbody>
$msg
</tbody>
</table>
EOF;

echo <<<EOF
$top
<hr>
$msg
<hr>
$footer
EOF;
