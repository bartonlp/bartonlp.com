<?php
$_site = require_once(getenv("SITELOADNAME"));
ErrorClass::setDevelopment(true);
$tbl = (require(SITECLASS_DIR . "/whatisloaded.class.php"))[0];

$S = new SiteClass($_site);
$S->title = "Get Versions";
$S->banner = "<h1>Get Versions</h1>";
$S->css = "td { padding: 0 10px; }";

[$top, $footer] = $S->getPageTopBottom();

foreach($ret as $k=>$v) {
  $msg .= "<tr><td>$k</td><td>$v</td></tr>";
}

echo <<<EOF
$top
<hr>
$tbl
<hr>
$footer
EOF;
