<?php
// Auto load classes
// This may need to be edited depending on where things are.

function callback($class) {
  switch($class) {
    case "SimpleSiteClass":
      require("simple-site-class/includes/$class.php");
      break;
    default:
      $class = preg_replace("~^Simple~", "", $class);
      require("simple-site-class/includes/database-engines/$class.class.php");
      break;
  }
}

if(spl_autoload_register("callback") === false) exit("Can't Autoload");

require("simple-site-class/includes/database-engines/simple-helper-functions.php");

SimpleErrorClass::setDevelopment(true);

date_default_timezone_set('America/New_York'); // Done here and in dbPdo.class.php constructor.

return json_decode(stripComments(file_get_contents("mysitemap.json")));


