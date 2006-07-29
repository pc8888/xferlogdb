<?php
// $Id$
//
$mtime = explode(" ", microtime());
$start = doubleval($mtime[1]) + doubleval($mtime[0]);

require("dbconnect.php");
require("functions.php");

read_glftpdlog($GLFTPD_FILE, $SITE_ROOT);
readlog($XFERLOG_FILE, $SITE_ROOT);
if ($glftpd_version >= 1.31) {
  read_loginlog($LOGIN_FILE, $SITE_ROOT);
} 

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<title>glftpd xferlogDB</title>
<link rel="stylesheet" href="templates/classic.css" type="text/css" />
</head>
<body>
<div class="box-border">
<div class="box-text">
<?php
echo "logfile " . $XFERLOG_FILE . " imported successful.";

?>
</div>
</div>
<?php
$mtime = explode(" ", microtime());
$end = doubleval($mtime[1]) + doubleval($mtime[0]);
print("xferlogDB generated in " . number_format(abs($start - $end), 5, '.', '') . " seconds.\n");

?>

</body>
</html>
