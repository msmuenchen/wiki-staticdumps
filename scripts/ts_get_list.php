<?

// Step 0: create article list from toolserver db (useless outside of WM environment!)
// Call: php ts_get_list.php CFGFILE LISTFILE WIKINAME
// CFGFILE is the name of a configfile in the config/ directory
// LISTFILE is the name of an article list in the lists/ directory
// WIKINAME is the project name (WIKINAME_p will be the TS database)

require("common.php");

$cfgfile=$argv[1];
if(!is_file("$confdir/$cfgfile"))
  die("Broken config file $cfgfile\n");
require("$confdir/$cfgfile");

$listfile=$argv[2];
//Open the list file
$fp=fopen("$listdir/$listfile","w");
if($fp===false)
  die("Error in fopen\n");

$wikiname=$argv[3];

//open log file
$logfile="$logdir/$listfile.gen_list.log";
$log_fp=fopen($logfile,"w");
if($log_fp===false)
  die("Error in fopen (logfile $logfile)\n");

//load TS access data and open MySQL connection
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/.my.cnf");
$db = mysql_connect("$wikiname-p.rrdb.toolserver.org", $ts_mycnf['user'], $ts_mycnf['password']);
$res=mysql_select_db("{$wikiname}_p",$db);
if($res===false)
  die("mysql error ".mysql_errno($db).": ".mysql_error($db)."\n");

//timer
$start_time=microtime(true);

//run the query
echo "loading page list (this can and will take time)\n";
$res=mysql_query("select /* SLOW_OK */ page_id,page_title,page_latest,page_is_redirect,page_namespace from page;",$db);
if($res===false)
  die("mysql error ".mysql_errno($db).": ".mysql_error($db)."\n");
elseif(mysql_num_rows($res)==0)
  die("target wiki is empty?\n");
else {
  $cnt=mysql_num_rows($res);
  echo "target wiki has $cnt entries.\n";
}

//loop over the results
$c=0;
$redirs=0;
while($row=mysql_fetch_assoc($res)) {
  $c++;
  //display progress every 1k entries
  if($c % 1000==0) {
    echo "\x1b[1`";
    echo str_pad("_",30,"_");
    echo "\x1b[1`";
    echo "$c of $cnt";
  }
  //b64 encode title to avoid UTF8 fuck-ups on console or in file
  $row["page_title"]=base64_encode($row["page_title"]);
  
  //page is a redirect? find out info!
  if($row["page_is_redirect"]==true) {
    $redirs++;
    $sr=mysql_query("select * from redirect where rd_from=".$row["page_id"].";",$db);
    if($sr===false)
      die("mysql error ".mysql_errno($db).": ".mysql_error($db)."\n");
    elseif(mysql_num_rows($sr)==0) {
      fwrite($log_fp,"redir from ".$row["page_id"]." (title ".$row["page_title"].") has no target\n");
    } else {
      $redir=mysql_fetch_assoc($sr);
      foreach($redir as $k=>$v)
        $redir[$k]=base64_encode($v);
      $row["page_is_redirect"]=base64_encode(implode("|",$redir));
    }
  }
  $str=implode("|",$row);
  fwrite($fp,$str."\n");
} 

$stop_time=microtime(true);
echo "$c articles, $redirs of them redirects, written to file in ".($stop_time-$start_time)." seconds.\n";
?>