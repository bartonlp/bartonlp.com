<?php
$_site = require_once(getenv("SITELOADNAME"));
$S = new SiteClass($_site);
ErrorClass::setDevelopment(true);

$ipv6long = "123456789012345678901234567890";

//$ipv6long = gmp_strval(gmp_init($ipv6long, 2), 10);
$ipv6long = gmp_strval($ipv6long, 10);
echo "num: $ipv6long<br>";

