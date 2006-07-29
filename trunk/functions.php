<?php 
// $Id$
//
function readlog($XFERLOG_FILE = "", $SITE_ROOT = "") {
    global $hideip;
    /**
     * Reads logfile and inserts data into the database
     * Old data is deleted from database before new data is added
     */

    if (strlen($XFERLOG_FILE) < 2) $XFERLOG_FILE = "/glftpd/ftp-data/logs/xferlog";
    if (strlen($SITE_ROOT) < 2) $SITE_ROOT = "/site";

    $xferlog_file = $XFERLOG_FILE;
    $site_root = $SITE_ROOT; 
    // site_root - will be removed from filename
    $fd = fopen ($xferlog_file, "r"); // full path to xferlog
    if (!$fd) {
        echo "<b>Error reading $xferlog_file !</b><br />\n";
        exit;
    } 

    $num_lines = 1;

    $elements = array();
    while (true) {
        $buffer_tmp = fgets($fd, 4096);

        $buffer = str_replace("'", "_", $buffer_tmp);

        $elements = preg_split("/[\s]+/", $buffer); // split by 1 or more whitespaces
        if ($elements[0] == '') {
            $num_lines--;
            break; // not a nice way to do it, but it works ;-)
        } 
        $timestring = $elements[1] . " " . $elements[2] . " " . $elements[4] . " " . $elements[3]; // create timestring
        if ($num_lines == 1) {
            $res = mysql_query("delete from log where time >= " . date('YmdHis', strtotime($timestring)));
        } 

        if ($hideip) {
            $host = "";
        } else {
            $host = $elements[6];
        } 

        if ($elements[9] == "b")
            $mode = 1; // binary: mode = 1
        else
            $mode = 0; // asci: mode = 0
        if ($elements[11] == "o")
            $direction = 1; // download = 1
        else
            $direction = 0; // upload = 0
        $res = mysql_query("insert into log (time,transfertime,host,bytes,file,mode,direction,user,pgroup) values(" . date('YmdHis', strtotime($timestring)) . "," . $elements[5] . ",'" . $host . "'," . $elements[7] . ",'" . addslashes(str_replace('?', ' ', str_replace($site_root, '', $elements[8]))) . "',$mode,$direction,'" . $elements[13] . "','" . $elements[14] . "')") or die(mysql_error()); // insert log data to database
        $num_lines++;
    } 
    fclose ($fd);

    $rs = mysql_query("select count(*) db_recs from log") or die(mysql_error());
    $row = mysql_fetch_array($rs);
    $db_recs = $row["db_recs"];
    mysql_free_result($rs);

    $res = mysql_query("insert into stats (lastupdate,numlines) values(" . date('YmdHis') . ",$db_recs)") or die(mysql_error()); // add some stats
} 

function lastupdate() {
    $res_update = mysql_query("select UNIX_TIMESTAMP(lastupdate),numlines from stats order by lastupdate desc limit 1") or die(mysql_error());
    $row_update = mysql_fetch_array($res_update);
    $update = array();
    $update[0] = $row_update[0];
    $update[1] = $row_update[1];
    return $update;
} 

function usertransfer($user) {
    /**
     * Returns users transfer stats in MB
     */
    $transfer = array();
    $res = mysql_query("select SUM(bytes) from log where user='$user' and direction=0") or die(mysql_error()); // sum of bytes uploaded
    $row = mysql_fetch_array($res);
    $transfer[0] = round($row[0] / 1048576, 2);
    $res = mysql_query("select SUM(bytes) from log where user='$user' and direction=1") or die(mysql_error()); // sum of bytes downloaded
    $row = mysql_fetch_array($res);
    $transfer[1] = round($row[0] / 1048576, 2);
    return $transfer;
} 

function getdata($range, $type, $direction, $limit = 10) {
    /**
     * range = month/week/day/user selected
     * type = byte/files
     * direction 0/1
     */
    switch ($range) {
        case "Month":
            $startDate = date("Ym01000000");
            $endDate = date("Ymt235959");
            break;
        case "Week":
            $startDay = date("d");
            $startWeek = date("w");
            if ($startWeek != 0) {
                for($i = 0; $startWeek != 0; $i++) {
                    $startWeek--;
                } 
                $startDay = $startDay - $i;
            } 

            $startDate = date("YmdHis", mktime (0, 0, 0, date("m"), $startDay, date("Y")));
            $endDate = date("YmdHis", mktime (23, 59, 59, date("m"), ($startDay + 6), date("Y")));
            break;
        case "Day":
            $startDate = date("Ymd000000");
            $endDate = date("Ymd235959");
            break;
    } 
    // set javascript varables
    if ($direction == 0) {
        $jsvar = "up" . $range . $type;
    } else {
        $jsvar = "dn" . $range . $type;
    } 

    if ($type == "Bytes") {
        selectedtop10bytes($jsvar, $range, $startDate, $endDate, $direction, $limit);
    } else {
        selectedtop10files($jsvar, $range, $startDate, $endDate, $direction, $limit);
    } 
} 

function top10bytes($direction, $limit = 10) {
    /**
     * Writes top transfer user/number of bytes (in MB) 
     * direction: 0 = upload, 1 = download
     */
    if ($direction == 0) {
        echo "var upAllBytes =\"<b>Top $limit <span class='up'>upload</span> [bytes]</b>\"\n";
        $jsallbyte = "upAllBytes";
    } else {
        echo "var dnAllBytes =\"<b>Top $limit <span class='down'>download</span> [bytes]</b>\"\n";
        $jsallbyte = "dnAllBytes";
    } 
    echo "$jsallbyte +=\"<div class='box-border'>\"\n";
    echo "$jsallbyte +=\"<div class='box-text'>\"\n";
    $top10 = array();
    $res_top10 = mysql_query("select SUM(bytes) as sum,user from log where direction=$direction GROUP by user order by sum desc limit $limit") or die(mysql_error());
    $num_rows = mysql_num_rows($res_top10);
    for($i = 0;$i < $num_rows;$i++) {
        $row_top10 = mysql_fetch_array($res_top10);
        $top10[$i][0] = $row_top10[0]; // 0 = sum of uploads 
        $top10[$i][1] = $row_top10[1]; // 1 = user
    } 
    rsort($top10);
    if ($num_rows > $limit) $num_rows = ($limit);
    for($i = 0;$i < $num_rows;$i++) {
        echo "$jsallbyte +=\"" . DownloadSize($top10[$i][0]) . " &nbsp;-&nbsp; " . $top10[$i][1] . "<br />\"\n";
    } 
    echo "$jsallbyte +=\"</div></div>\"\n";
} 

function selectedtop10bytes($jsvar, $range, $startDate, $endDate, $direction, $limit = 10) {
    /**
     * Writes top transfer user/number of bytes (in MB)
     * direction: 0 = upload, 1 = download
     */

    if ($direction == 0) {
        echo "var $jsvar = \"<b>$range Top $limit <span class='up'>upload</span> [bytes]</b>\"\n";
    } else {
        echo "var $jsvar = \"<b>$range Top $limit <span class='down'>download</span> [bytes]</b>\"\n";
    } 
    echo "$jsvar += \"<div class='box-border'>\"\n";
    echo "$jsvar += \"<div class='box-text'>\"\n";
    $top10 = array();
    $res_top10 = mysql_query("select SUM(bytes) as sum,user,time from log where direction=$direction and time >= $startDate and time <= $endDate GROUP by user order by sum desc limit $limit") or die(mysql_error());
    $num_rows = mysql_num_rows($res_top10);
    for($i = 0;$i < $num_rows;$i++) {
        $row_top10 = mysql_fetch_array($res_top10);
        $top10[$i][0] = $row_top10[0]; // 0 = sum of uploads
        $top10[$i][1] = $row_top10[1]; // 1 = user
    } 
    rsort($top10);
    if ($num_rows > $limit) $num_rows = ($limit);
    for($i = 0;$i < $num_rows;$i++) {
        echo "$jsvar += \"" . DownloadSize($top10[$i][0]) . " &nbsp;-&nbsp; " . $top10[$i][1] . "<br />\"\n";
    } 
    echo "$jsvar += \"</div></div>\"\n";
} 

function top10files($direction, $limit = 10) {
    /**
     * Writes top transfer user/number of files
     * direction: 0 = upload, 1 = download
     */
    if ($direction == 0) {
        echo "var upAllFiles =\"<b>Top $limit <span class='up'>upload</span> [files]</b>\"\n";
        $jsallfiles = "upAllFiles";
    } else {
        echo "var dnAllFiles =\"<b>Top $limit <span class='down'>download</span> [files]</b>\"\n";
        $jsallfiles = "dnAllFiles";
    } 
    echo "$jsallfiles +=\"<div class='box-border'>\"\n";
    echo "$jsallfiles +=\"<div class='box-text'>\"\n";
    $top10 = array();
    $res_top10 = mysql_query("select COUNT(user) as cnt,user from log where direction=$direction GROUP by user order by cnt desc limit $limit") or die(mysql_error());
    for($i = 0;$i < $limit;$i++) {
        $row_top10 = mysql_fetch_array($res_top10);
        $top10[$i][0] = $row_top10[0]; // 0 = number of files
        $top10[$i][1] = $row_top10[1]; // 1 = user
    } 
    rsort($top10);
    for($i = 0;$i < $limit;$i++) {
        echo "$jsallfiles +=\"" . $top10[$i][0] . " files &nbsp;-&nbsp; " . $top10[$i][1] . "<br />\"\n";
    } 
    echo "$jsallfiles +=\"</div></div>\"\n";
} 

function selectedtop10files($jsvar, $range, $startDate, $endDate, $direction, $limit = 10) {
    /**
     * Writes top transfer user/number of files
     * direction: 0 = upload, 1 = download
     */
    if ($direction == 0) {
        echo "var $jsvar =\"<b>$range Top $limit <span class='up'>upload</span> [files]</b>\"\n";
    } else {
        echo "var $jsvar =\"<b>$range Top $limit <span class='down'>download</span> [files]</b>\"\n";
    } 
    echo "$jsvar +=\"<div class='box-border'>\"\n";
    echo "$jsvar +=\"<div class='box-text'>\"\n";
    $top10 = array();
    $res_top10 = mysql_query("select COUNT(user) as cnt,user,time from log where direction=$direction and time >= $startDate and time <= $endDate GROUP by user order by cnt desc limit $limit") or die(mysql_error());
    for($i = 0;$i < $limit;$i++) {
        $row_top10 = mysql_fetch_array($res_top10);
        $top10[$i][0] = $row_top10[0]; // 0 = number of files
        $top10[$i][1] = $row_top10[1]; // 1 = user
    } 
    rsort($top10);
    for($i = 0;$i < $limit;$i++) {
        echo "$jsvar +=\"" . $top10[$i][0] . " files &nbsp;-&nbsp; " . $top10[$i][1] . "<br />\"\n";
    } 
    echo "$jsvar +=\"</div></div>\"\n";
} 

function site_traffic($range) {
    // im lazy, hardcoding this
    switch ($range) {
        case "Month":
            $startDate = date("Ym01000000");
            $endDate = date("Ymt235959");
            break;
        case "Week":
            $startDay = date("d");
            $startWeek = date("w");
            if ($startWeek != 0) {
                for($i = 0; $startWeek != 0; $i++) {
                    $startWeek--;
                } 
                $startDay = $startDay - $i;
            } 

            $startDate = date("YmdHis", mktime (0, 0, 0, date("m"), $startDay, date("Y")));
            $endDate = date("YmdHis", mktime (23, 59, 59, date("m"), ($startDay + 6), date("Y")));
            break;
        case "Day":
            $startDate = date("Ymd000000");
            $endDate = date("Ymd235959");
            break;
    } 
    $res = array();
    if ($range == "All") {
        $res_query = mysql_query("select direction,sum(bytes),count(distinct file) from log group by direction order by direction");
    } else {
        $res_query = mysql_query("select direction,sum(bytes),count(distinct file) from log where time >= $startDate and time <= $endDate group by direction order by direction");
    } 
    // set vars to 0
    $DownByte = 0;
    $DownFile = 0;
    $UpByte = 0;
    $UpFile = 0;

    $resrow = mysql_num_rows($res_query);
    for($i = 0;$i < $resrow;$i++) {
        $res = mysql_fetch_array($res_query);
        if ($res[0] == 0) {
            $UpByte = DownloadSize($res[1]);
            $UpFile = $res[2];
        } elseif ($res[0] == 1) {
            $DownByte = DownloadSize($res[1]);
            $DownFile = $res[2];
        } 
    } 
    echo "var " . $range . "Total =\"<b>$range Totals</b>\"\n";
    echo "" . $range . "Total +=\"<div class='box-border'>\"\n";
    echo "" . $range . "Total +=\"<div class='box-text'>\"\n";
    echo "" . $range . "Total += \"<b>bytes:</b> <br />\"\n";
    echo "" . $range . "Total += \"<span class='down'>downloaded: </span>" . $DownByte . "<br />\"\n";
    echo "" . $range . "Total += \"<span class='up'>uploaded: </span> " . $UpByte . "<br />\"\n";
    echo "" . $range . "Total += \"<b>files:</b> <br />\"\n";
    echo "" . $range . "Total += \"<span class='down'>downloaded: </span>" . $DownFile . "<br />\"\n";
    echo "" . $range . "Total += \"<span class='up'>uploaded: </span>" . $UpFile . "<br />\"\n";
    echo "" . $range . "Total += \"</div></div>\"\n";
} 

function latest_files($direction, $user = "", $limit = 10) {
    global $hideip;

    /**
     * Writes the latest log entries
     * direction: 0 = upload, 1 = download
     * If user is set, users stats are displayed
     */
    if (strlen($user) > 1) $query_string = "select UNIX_TIMESTAMP(time),file,bytes,user,host from log where direction=$direction and user='$user' order by time desc limit $limit";
    else $query_string = "select UNIX_TIMESTAMP(time),file,bytes,user,host from log where direction=$direction order by time desc limit $limit";
    $res_latest = mysql_query($query_string) or die(mysql_error());
    $num_files = mysql_num_rows($res_latest);
    if ($num_files > 0) {
        if ($num_files < $limit) $limit = $num_files;
        if ($direction == 0) echo "<b>Latest $limit <span class=\"up\">uploaded</span> files</b>\n";
        else echo "<b>Latest $limit <span class=\"down\">downloaded</span> files</b>\n";
        echo "<div class=\"box-border\">\n";
        echo "<div class=\"box-text\">\n";

        while ($row_latest = mysql_fetch_array($res_latest)) {
            if (!$hideip) {
                echo date('Y.m.d H:i:s', $row_latest[0]) . " : $row_latest[file] : " . DownloadSize($row_latest[bytes]) . " : $row_latest[user] : $row_latest[host]<br />\n";
            } else {
                echo date('Y.m.d H:i:s', $row_latest[0]) . " : $row_latest[file] : " . DownloadSize($row_latest[bytes]) . " : $row_latest[user]<br />\n";
            } 
        } 
        echo "</div>\n</div>\n";
    } 
} 

function pop_files($limit = 10) {
    /**
     * Writes the most popular files (downloaded, there is no point in showing upload :-)
     */

    $res_pop = mysql_query("select COUNT(file) as cnt,file from log where direction=1 GROUP by file order by cnt desc limit $limit") or die(mysql_error());
    echo "<b>$limit most popular files</b>\n";
    echo "<div class=\"box-border\">\n";
    echo "<div class=\"box-text\">\n";
    $pop_files = array();
    for($i = 0;$i < $limit;$i++) {
        $row_pop = mysql_fetch_array($res_pop);
        $pop_files[$i][0] = $row_pop[0]; // 0 = number
        $pop_files[$i][1] = $row_pop[1]; // 1 = name
    } 
    rsort($pop_files);
    for($i = 0;$i < $limit;$i++) {
        echo "" . $pop_files[$i][1] . " - <span class=\"down\">downloaded</span>: " . $pop_files[$i][0] . " times<br />\n";
    } 
    echo "</div>\n</div>\n";
} 

function list_log($index = 0, $step, $user, $template) {
    global $hideip;

    /**
     * List all log data if not $limit is set
     */
    if (!empty($user)) {
        $query_string = "select UNIX_TIMESTAMP(time),direction,file,bytes,user,host from log where user='$user' order by time desc limit $index,$step";
    } else {
        $query_string = "select UNIX_TIMESTAMP(time),direction,file,bytes,user,host from log order by time desc limit $index,$step";
    } 
    $res_total = mysql_query("select COUNT(id) as total from log") or die(mysql_error());
    $row_total = mysql_fetch_array($res_total);
    $last_index = floor(($row_total[total] / $step)) * $step;

    $res_list = mysql_query($query_string) or die(mysql_error());
    $num_rows = mysql_num_rows($res_list);

    if ($index == 0) {
        $navstr = "&lt; &nbsp; &lt;&lt;";
    } else {
        $navstr = "<a href=\"?index=" . ($index - $step) . "&step=$step&user=$user&template=$template\">&lt;</a> &nbsp; <a href=\"?index=0&step=$step&user=$user&template=$template\">&lt;&lt;</a>";
    } 
    $navstr = $navstr . " | ";
    if ($num_rows == $step) {
        $navstr = $navstr . "<a href=\"?index=" . $last_index . "&step=$step&user=$user&template=$template\">&gt;&gt;</a> &nbsp; <a href=\"?index=" . ($index + $step) . "&step=$step&user=$user&template=$template\">&gt;</a>";
    } else {
        $navstr = $navstr . "&gt;&gt; &nbsp; &gt;";
    } 
    echo $navstr;
    echo "<br />\n";
    echo "<div class=\"box-border\">\n";
    echo "<div class=\"box-text\">\n";
    while ($row_list = mysql_fetch_array($res_list)) {
        $dir_text = $row_list[direction] ? '<span class="down">download</span>' : '<span class="up">upload</span>';
        if (!$hideip) {
            echo date('Y.m.d H:i:s', $row_list[0]) . " : $dir_text : $row_list[file] : $row_list[bytes] : $row_list[user] : $row_list[host]<br />\n";
        } else {
            echo date('Y.m.d H:i:s', $row_list[0]) . " : $dir_text : $row_list[file] : $row_list[bytes] : $row_list[user]<br />\n";
        } 
    } 
    echo "</div>\n</div>\n";
    echo $navstr;
    echo "<br />\n";
} 

/**
 * ++++++++++++++++++   glftpd.log functions   ++++++++++++++++++++++++++++
 */

function read_glftpdlog($GLFTPD_FILE = "", $SITE_ROOT = "") {
    global $hideip;

    if (strlen($GLFTPD_FILE) < 2) $GLFTPD_FILE = "/glftpd/ftp-data/logs/glftpd.log";
    if (strlen($SITE_ROOT) < 2) $SITE_ROOT = "/site";

    $glftpd_file = $GLFTPD_FILE;
    $site_root = $SITE_ROOT;
    $num_lines = 1;
    $fd = fopen($glftpd_file, "r"); // full path to glftpd.log
    if (!$fd) {
        echo "<b>Error reading $glftpd_file !</b><br />\n";
        exit;
    } 
    $elements_tmp = array();
    $elements = array();
    $elements2 = array();
    while (true) {
        $buffer_tmp = fgets($fd, 4096);
        $buffer = str_replace("'", "_", $buffer_tmp);

        $elements_tmp = split(": ", $buffer); // spilt the string in 2 parts
        $elements_tmp_count = count($elements_tmp);

        $elements = preg_split("/[\s]+/", $elements_tmp[0]); // spilt the first part by one or more blanks
        $elements_count = count($elements);

        if ($elements_count > 5 && $elements[5] == 'DEBUG') {
            continue;
        } 

        if ($elements_count > 5 && $elements[5] == 'NEWDIR') {
            $elements2 = split(' "', $elements_tmp[1]); // if the second part is a NEWDIR, then split by ' "'
        } else {
            if ($elements_tmp_count > 1) {
                $elements2 = preg_split("/[\s]+/", $elements_tmp[1]); // else spilt the second part by one or more blanks
            } 
        } 

        if ($elements[0] == '') {
            break; // not a nice way to do it, but i works ;-)
            /**
             * MySQL table: id,time,type,host,user
             * type: 1=LOGIN,2=LOGOUT,3=TIMEOUT
             */
        } 

        if ($elements[5] == 'LOGIN' or $elements[5] == 'LOGOUT' or $elements[5] == 'TIMEOUT') { // the ones we want
            $timestring = $elements[1] . " " . $elements[2] . " " . $elements[4] . " " . $elements[3]; // create timestring
            $timestring = date('YmdHis', strtotime($timestring));
            if ($num_lines == 1) {
                $res = mysql_query("delete from online where time >= " . $timestring);
            } 
            $type = 0;
            if ($elements[5] == 'LOGIN') $type = 1;
            if ($elements[5] == 'LOGOUT') $type = 2;
            if ($elements[5] == 'TIMEOUT') $type = 3;

            $host = "";
            if ($elements[5] == 'LOGIN') {
                if ($hideip) {
                    $host = "";
                } else {
                    $host = preg_replace("/[(,)]+/", "", $elements2[1]); // strip '(' ')'
                } 
            } 

            $user = "";
            if ($elements[5] == 'TIMEOUT')
                $user = str_replace('"', '', $elements2[0]); // strip '"'
            else
                $user = str_replace('"', '', $elements2[2]); // strip '"'
            $res = mysql_query("insert into online (time,type,host,user) values('$timestring',$type,'$host','$user')") or die(mysql_error());
        } elseif ($elements[5] == 'NEWDIR') {
            $timestring = $elements[1] . " " . $elements[2] . " " . $elements[4] . " " . $elements[3]; // create timestring
            $timestring = date('YmdHis', strtotime($timestring));
            if ($num_lines == 1) {
                $res = mysql_query("delete from online where time >= " . $timestring);
            } 
            $type = 4;
            $host = str_replace($site_root, '', str_replace('"', '', $elements2[0])); // I know. $host should be for hostname, but I'll use it for NEWDIRS as well :-)
            $user = str_replace('"', '', $elements2[1]);

            $res = mysql_query("insert into online (time,type,host,user) values('$timestring',$type,'$host','$user')") or die(mysql_error());
        } 
        $num_lines++;
    } 
    fclose ($fd);
} 

function read_loginlog($LOGIN_FILE = "", $SITE_ROOT = "") {
    global $hideip;

    if (strlen($LOGIN_FILE) < 2) $LOGIN_FILE = "/glftpd/ftp-data/logs/login.log";
    if (strlen($SITE_ROOT) < 2) $SITE_ROOT = "/site";

    $login_file = $LOGIN_FILE;
    $site_root = $SITE_ROOT;
    $num_lines = 1;
    $fd = fopen($login_file, "r"); // full path to login.log
    if (!$fd) {
        echo "<b>Error reading $login_file !</b><br />\n";
        exit;
    } 
    $elements_tmp = array();
    $elements = array();
    $elements2 = array();
    while (true) {
        $buffer = fgets($fd, 4096);

        $elements_tmp = split(": ", $buffer); // spilt the string in 2 parts
        $elements_tmp_count = count($elements_tmp);

        $elements = preg_split("/[\s]+/", $elements_tmp[0]); // spilt the first part by one or more blanks
        if ($elements_tmp_count > 1) {
            $elements2 = preg_split("/[\s]+/", $elements_tmp[1]); // else spilt the second part by one or more blanks
        } 

        if ($elements[0] == '') {
            break; // not a nice way to do it, but i works ;-)
        } 

        /**
         * MySQL table: id,time,type,host,user
         * type: 1=LOGIN,2=LOGOUT,3=TIMEOUT
         */

        if ($elements[7] == 'LOGIN' or $elements[7] == 'LOGOUT' or $elements[7] == 'TIMEOUT') { // the ones we want
            $timestring = $elements[1] . " " . $elements[2] . " " . $elements[4] . " " . $elements[3]; // create timestring
            $timestring = date('YmdHis', strtotime($timestring));
            if ($num_lines == 1) {
                $res = mysql_query("delete from online where type in (1,2,3) and time >= " . $timestring);
            } 

            $type = 0;
            if ($elements[7] == 'LOGIN') $type = 1;
            if ($elements[7] == 'LOGOUT') $type = 2;
            if ($elements[7] == 'TIMEOUT') $type = 3;

            $host = "";
            if ($elements[7] == 'LOGIN') {
                if ($hideip) {
                    $host = "";
                } else {
                    $host = preg_replace("/[(,)]+/", "", $elements2[1]); // strip '(' ')'
                } 
            } 

            $user = "";
            if ($elements[7] == 'TIMEOUT') {
                $user = str_replace('"', '', $elements2[0]); // strip '"'
            } elseif ($elements[7] == 'LOGOUT') {
                $user = str_replace('"', '', $elements2[2]); // strip '"'
            } elseif ($elements[7] == 'LOGIN') {
                $user = str_replace('"', '', $elements2[3]); // strip '"'
            } 

            $res = mysql_query("insert into online (time,type,host,user) values('$timestring',$type,'$host','$user')") or die(mysql_error());
        } 
        $num_lines++;
    } 
    fclose ($fd);
} 

function last_login($user) {
    $res = mysql_query("select UNIX_TIMESTAMP(time),host from online where user='$user' and type=1 order by time desc limit 1") or die(mysql_error());
    $row = mysql_fetch_array($res);
    return $row; // returns timestamp [0] and host [1]
} 

function last_logout($user) {
    $res = mysql_query("select UNIX_TIMESTAMP(time) from online where user='$user' and type=2 order by time desc limit 1") or die(mysql_error());
    $row = mysql_fetch_array($res);
    return $row; // returns timestamp
} 

function last_timeout($user) {
    $res = mysql_query("select UNIX_TIMESTAMP(time) from online where user='$user' and type=3 order by time desc limit 1") or die(mysql_error());
    if (mysql_num_rows($res) > 0) {
        $row = mysql_fetch_array($res);
        return $row; // returns timestamp
    } else {
        return 0;
    } 
} 
function new_dirs($jsvar, $user = "", $limit = 10) {
    $query_string = "";
    if (strlen($user) < 2) {
        if ($jsvar == "MIXED") {
            $query_string = "select UNIX_TIMESTAMP(time) as tm,host,user from online where type=4 order by time desc limit $limit";
        } else {
            $query_string = "select UNIX_TIMESTAMP(time) as tm,host,user from online where type=4 and host like \"%/$jsvar/%\" order by time desc limit $limit";
        } 
    } else {
        if ($jsvar == "MIXED") {
            $query_string = "select UNIX_TIMESTAMP(time) as tm,host,user from online where user='$user' and type=4 order by time desc limit $limit";
        } else {
            $query_string = "select UNIX_TIMESTAMP(time) as tm,host,user from online where user='$user' and type=4 and host like \"%/$jsvar/%\" order by time desc limit $limit";
        } 
    } 
    $res_newdirs = mysql_query($query_string) or die(mysql_error());
    $num_rows = mysql_num_rows($res_newdirs);
    echo "var $jsvar = \"\"\n";
    if ($num_rows > 0) {
        if ($num_rows < $limit) $limit = $num_rows;
        while ($row_newdir = mysql_fetch_array($res_newdirs)) {
            echo "$jsvar += \"" . date('Y.m.d H:i:s', $row_newdir["tm"]) . " : " . $row_newdir["host"] . " : " . $row_newdir["user"] . "<br />\"\n";
        } 
    } 
} 

function online($user) {
    global $hideip;

    $login = last_login($user);
    $logout = last_logout($user);
    $timeout = last_timeout($user);
    $status = "";

    if (is_online($user) == 1) {
        $status = "<span class=\"down\">online</span>\n";
    } else {
        $status = "<span class=\"up\">offline</span>";
        if (($logout[0] - $timeout[0]) > 0) $status .= " - logout time: " . date('Y.m.d H:i:s', $logout[0]);
        else $status .= " - logout time: " . date('Y.m.d H:i:s', $timeout[0]);
    } 

    echo "User is: $status <br />\n";
    echo "Last login time: " . date('Y.m.d H:i:s', $login[0]) . "<br />\n";
    if (!$hideip) {
        echo "Last login from: " . $login[1] . "<br />\n";
    } 
} 

function is_online($user) {
    $login = last_login($user);
    $logout = last_logout($user);
    $timeout = last_timeout($user);
    $status = 0;
    if (($login[0] - $logout[0]) > 0 and ($login[0] - $timeout[0]) > 0) $status = 1;
    return $status;
} 

function list_users($template) {
    $usr_online = array();
    $usr_offline = array();
    $cnt_online = 0;
    $cnt_offline = 0;
    $res_usr = mysql_query("select DISTINCT user from online where user!='glftpd' and user!='unknown' and type!=4 order by user") or die(mysql_error());
    while ($row_usr = mysql_fetch_array($res_usr)) {
        if (is_online($row_usr["user"]) == 1) {
            $usr_online[$cnt_online] = $row_usr["user"];
            $cnt_online++;
        } else {
            $usr_offline[$cnt_offline] = $row_usr["user"];
            $cnt_offline++;
        } 
    } 
    echo "<b>Online users</b> (".count($usr_online)."):<br />\n";
    if (sizeof($usr_online) == 0) {
        echo "<div style=\"margin-left: 10px\">None</div>\n";
    } else {
        foreach($usr_online as $val) {
            echo "<a style=\"margin-left: 10px\" href=\"index.php?user=$val&template=$template\">$val</a><br />\n";
        } 
    } 
    echo "<br />\n";
    echo "<b>Offline users</b> (" .count($usr_offline)."): <br />\n";
    if (sizeof($usr_offline) == 0) {
        echo "None";
    } else {
        foreach($usr_offline as $val) {
            echo "<a style=\"margin-left: 10px\" href=\"index.php?user=$val&template=$template\">$val</a><br />\n";
        } 
    } 
    echo "<br />\n";
} 
function DownloadSize($size) {
  $sizes = Array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
  $ext = $sizes[0];
  for ($i=1; (($i < count($sizes)) && ($size >= 1024)); $i++) {
   $size = $size / 1024;
   $ext  = $sizes[$i];
  }
  return round($size, 2). " " .$ext;
}
?>
