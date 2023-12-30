<?php
$_site = require_once("special_autoload.php");

$eng = $_site->dbinfo->engine;
$dat = $_site->dbinfo->database;

$siteInfo = print_r($_site, true);
$S = new SimpleSiteClass($_site);
$SInfo = print_r($S, true);

$S->banner = "<h1>Hello from UsingPdo</h1><p>Using driver=$eng, database=$dat</p>";

$counts = "<p>The info from the logagent table:</p>";
$T = new SimpledbTables($S);

$sql = "select site, ip, agent, count, datetime(lasttime, '-5 hour') AS NewYorkTime from logagent order by lasttime";
$tbl = $T->maketable($sql, ["attr" => ["border" => "1"]])[0];

[$top, $footer] = $S->getPageTopBottom();

echo <<<EOF
$top
$counts
$tbl
<pre>
$siteInfo
$SInfo
</pre>
$footer
EOF;
