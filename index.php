<?php 
// $Id$
//
$mtime = explode(" ", microtime());
$start = doubleval($mtime[1]) + doubleval($mtime[0]);

require("dbconnect.php");
require("functions.php");

if (!isset($template) || !empty($template)) {
    $template = "classic";
} 
if (!empty($HTTP_POST_VARS["template"])) {
    $template = $HTTP_POST_VARS["template"];
} elseif (!empty($HTTP_GET_VARS["template"])) {
    $template = $HTTP_GET_VARS["template"];
} 
setcookie("template", $template, (time() + 2592000));

echo "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head><title>glftpd xferlogDB</title>
<?php
echo "<link rel=\"stylesheet\" href=\"templates/" . $template . ".css\" type=\"text/css\" />\n";

?>
</head>
<body>
<h1>xferlogDB</h1>
<div style="text-align: right; color: grey"><a href="http://xferlogdb.sf.net">xferlogDB</a> v0.9-cvs by webbie and r0ach</div>

<?php
$last_updated = lastupdate();
echo "<p>last updated: " . date('Y.m.d H:i:s', $last_updated[0]) . " with " . $last_updated[1] . " log entries</p>\n";

$newDirscount = (count($newDirs) -1);
if ((isset($HTTP_GET_VARS["user"])) AND (!isset($HTTP_GET_VARS["index"]))) {
    $gluser = $HTTP_GET_VARS["user"];
    echo "<a href=\"index.php?template=$template\">general stats</a> | <a href=\"?index=0&step=250&user=$gluser&template=$template\">full log list</a><br />\n";
    echo "<h3>User stats for $gluser</h3>\n";
    echo "<div class=\"box-border\">\n";
    echo "<div class=\"box-text\">\n";
    online($gluser);
    $transfer = usertransfer($gluser);
    echo "<span class=\"down\">Downloaded</span>: " . $transfer[1] . " MB<br />\n";
    echo "<span class=\"up\">Uploaded</span>: " . $transfer[0] . " MB<br />\n";
    echo "</div>\n</div>\n";
    echo "<div style=\"clear: both; margin-bottom: 20px;\">\n";
    latest_files(1, $gluser);
    echo "</div>\n";
    echo "<div style=\"clear: both; margin-bottom: 20px;\">\n";
    latest_files(0, $gluser);
    echo "</div>\n";
    echo "<div style=\"clear: both; margin-bottom: 20px;\">\n";
    echo "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"2\" border=\"0\">\n";
    echo "<tr><td><b id=\"dir\"></b></td>\n";
    echo "<td align=\"right\">\n";

    foreach ($newDirs as $key => $val) {
        echo "<a href='javascript:(update" . $val . "())'>" . $val . "</a>";
        if ($key != $newDirscount) {
            echo " | ";
        } else {
            echo "&nbsp;&nbsp;\n";
        } 
    } 

    echo "</td></tr></table>\n";
    echo "<div class=\"box-border\">\n";
    echo "<div class=\"box-text\" id=\"newDirs\">\n";
    echo "</div>\n";
    echo "</div>\n";

    echo "<script language=\"javascript\">\n";
    foreach ($newDirs as $val) {
        new_dirs($val, $gluser);
        echo "function update" . $val . "() {\n";
        echo "document.getElementById('newDirs').innerHTML=" . $val . "\n";
        echo "document.getElementById('dir').innerHTML=\"Latest 10 " . $val . " directories\"\n";
        echo "}\n";
    } 
    echo "update$newDirs[0]()\n";
    echo "</script>\n";
} elseif (isset($HTTP_GET_VARS["index"])) {
    echo "<a href=\"index.php?template=$template\">general stats</a> | full log list<br />\n";
    echo "<h3>Log list</h3>\n";
    list_log($HTTP_GET_VARS["index"], $HTTP_GET_VARS["step"], $HTTP_GET_VARS["user"], $template);
} else {
    echo "general stats | <a href=\"?index=0&step=250&user=&template=$template\">full log list</a>\n";
    echo "<table width=\"100%\" border=\"0\"><tr><td><h3>General stats for ftp server</h3></td>";
    echo "<td align=\"right\"><a href=\"javascript:(updateAllTime())\">All Time</a> | <a href=\"javascript:(updateMonth())\">Month</a> | <a href=\"javascript:(updateWeek())\">Week</a> | <a href=\"javascript:(updateDay())\">Day</a> | <a href=\"javascript:(updateTotals())\">Site Totals</a>&nbsp;&nbsp;</td></tr></table>\n";
    echo "<div style=\"float: left; width: 25%; text-align: left\">\n";
    echo "<div class=\"box-border\">\n";
    echo "<div class=\"box-text\">\n";
    list_users($template);
    echo "<br />\nClick on username to see user stats<br />\n";
    echo "</div>\n</div>\n</div>\n";
    echo "<div style=\"float: left; width: 75%; text-align: left\">\n";
    echo "<table border=\"0\" width=\"100%\"><tr>";
    echo "<td valign=\"top\"><div style=\"float: left; width: 100%; text-align: left\" id=\"dnByte\">\n";
    echo "</div></td>\n";
    echo "<td valign=\"top\"><div style=\"float: left; width: 100%; text-align: left\" id=\"upByte\">\n";
    echo "</div></td></tr>\n";
    echo "<tr><td><div style=\"float: left; width: 100%; text-align: left\" id=\"dnFiles\">\n";
    echo "</div></td>\n";
    echo "<td><div style=\"float: left; width: 100%; text-align: left\" id=\"upFiles\">\n";
    echo "</div></td></tr></table>\n";
    echo "<div style=\"float: left; width: 100%; text-align: left\">\n";
    pop_files(5);
    echo "</div>\n";
    echo "</div>\n";
    echo "<div style=\"clear: both; margin-bottom: 20px;\">\n";
    latest_files(1);
    echo "</div>\n";
    echo "<div style=\"clear: both; margin-bottom: 20px;\">\n";
    latest_files(0);
    echo "</div>\n";
    echo "<div style=\"clear: both; margin-bottom: 20px;\">\n";
    echo "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"2\" border=\"0\">\n";
    echo "<tr><td><b id=\"dir\"></b></td>\n";
    echo "<td align=\"right\">\n";

    foreach ($newDirs as $key => $val) {
        echo "<a href='javascript:(update" . $val . "())'>" . $val . "</a>";
        if ($key != $newDirscount) {
            echo " | ";
        } else {
            echo "&nbsp;&nbsp;\n";
        } 
    } 

    echo "</td></tr></table>\n";
    echo "<div class=\"box-border\">\n";
    echo "<div class=\"box-text\" id=\"newDirs\">\n";
    echo "</div>\n";
    echo "</div>\n"; 
    echo "</div>\n"; 
    
    // call functions for javascript
    echo "<script language=\"javascript\">\n";
    top10bytes(1, 5);
    top10bytes(0, 5);
    top10files(1, 5);
    top10files(0, 5);

    echo "function updateAllTime() {\n";
    echo "document.getElementById('dnByte').innerHTML=dnAllBytes\n";
    echo "document.getElementById('upByte').innerHTML=upAllBytes\n";
    echo "document.getElementById('dnFiles').innerHTML=dnAllFiles\n";
    echo "document.getElementById('upFiles').innerHTML=upAllFiles\n";
    echo "}\n";

    getdata("Month", "Bytes", 1, 5);
    getdata("Month", "Bytes", 0, 5);
    getdata("Month", "Files", 1, 5);
    getdata("Month", "Files", 0, 5);

    echo "function updateMonth() {\n";
    echo "document.getElementById('dnByte').innerHTML=dnMonthBytes\n";
    echo "document.getElementById('upByte').innerHTML=upMonthBytes\n";
    echo "document.getElementById('dnFiles').innerHTML=dnMonthFiles\n";
    echo "document.getElementById('upFiles').innerHTML=upMonthFiles\n";
    echo "}\n";

    getdata("Week", "Bytes", 1, 5);
    getdata("Week", "Bytes", 0, 5);
    getdata("Week", "Files", 1, 5);
    getdata("Week", "Files", 0, 5);

    echo "function updateWeek() {\n";
    echo "document.getElementById('dnByte').innerHTML=dnWeekBytes\n";
    echo "document.getElementById('upByte').innerHTML=upWeekBytes\n";
    echo "document.getElementById('dnFiles').innerHTML=dnWeekFiles\n";
    echo "document.getElementById('upFiles').innerHTML=upWeekFiles\n";
    echo "}\n";

    getdata("Day", "Bytes", 1, 5);
    getdata("Day", "Bytes", 0, 5);
    getdata("Day", "Files", 1, 5);
    getdata("Day", "Files", 0, 5);

    echo "function updateDay() {\n";
    echo "document.getElementById('dnByte').innerHTML=dnDayBytes\n";
    echo "document.getElementById('upByte').innerHTML=upDayBytes\n";
    echo "document.getElementById('dnFiles').innerHTML=dnDayFiles\n";
    echo "document.getElementById('upFiles').innerHTML=upDayFiles\n";
    echo "}\n";

    foreach ($newDirs as $val) {
        new_dirs($val);
        echo "function update" . $val . "() {\n";
        echo "document.getElementById('newDirs').innerHTML=" . $val . "\n";
        echo "document.getElementById('dir').innerHTML=\"Latest 10 " . $val . " directories\"\n";
        echo "}\n";
    } 
    echo "updateAllTime()\n";
    echo "update$newDirs[0]()\n";

    site_traffic("All");
    site_traffic("Month");
    site_traffic("Week");
    site_traffic("Day");
    echo "function updateTotals() {\n";
    echo "document.getElementById('dnByte').innerHTML=AllTotal\n";
    echo "document.getElementById('upByte').innerHTML=MonthTotal\n";
    echo "document.getElementById('dnFiles').innerHTML=WeekTotal\n";
    echo "document.getElementById('upFiles').innerHTML=DayTotal\n";
    echo "}\n";
    echo "</script>\n";
} 

$update_form = "<form method=\"POST\" action=\"\">\n"
 . "\tTemplate :&nbsp;\n"
 . "\t<select name=\"template\">\n";

$dir = opendir('templates/');
while (false !== ($file = readdir($dir))) {
    if ($file != 'CVS' && $file != 'xml' && $file != '.' && $file != '..') {
        $filelist[] = ereg_replace('.css', '', $file);
    } 
} 
closedir($dir);

asort($filelist);

while (list ($key, $val) = each ($filelist)) {
    if ($template == $val) {
        $update_form .= "\t\t<option value=\"$val\" SELECTED>$val</option>\n";
    } else {
        $update_form .= "\t\t<option value=\"$val\">$val</option>\n";
    } 
} 

$update_form .= "\t</select>\n"
 . "\t<input type=\"submit\" value=\"Submit\">\n"
 . "</form>\n";
print $update_form;

$mtime = explode(" ", microtime());
$end = doubleval($mtime[1]) + doubleval($mtime[0]);
print("xferlogDB generated in " . number_format(abs($start - $end), 5, '.', '') . " seconds.\n");

?>
</body>
</html>
