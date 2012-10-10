<?
ini_set("user_agent","swd static wiki dumper 1.0 (irc: harddisk_wp for problems)");
$apath="/mnt/user-store/dewiki_static/articles/"; //article cache dir
$tpath="/mnt/user-store/dewiki_static/tarballs/"; //tarball dir
$cpath="/mnt/user-store/dewiki_static/control/";  //control files
$lpath="/mnt/user-store/dewiki_static/logs/";     //log files


// handle forking in batch mode
if($argv[2]=="1") {
  $batch=true;
  $pid=pcntl_fork();
  if($pid==-1)
    die("fork failed\n");
  elseif($pid)
    exit("forked off \n");
  else { // do nothing, we're the child...
  }
}
else
  $batch=false;

//die($argv[0]);
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/.my.cnf");
$db_w = mysql_connect("sql", $ts_mycnf['user'], $ts_mycnf['password']);
if($db_w===false)
  die("can't connect to mysql, error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
mysql_select_db("u_harddisk_djrunner",$db_w);

if($jt!=0) { //unless we are stage 0 (job list creation), the 1st param is the dump key  
  $dumpkey=$argv[1];
  $r=mysql_query("select * from `keys` where dumpkey='$dumpkey';",$db_w);
  if($r===false)
    die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
  elseif(mysql_num_rows($r)!=1)
    die("dumpkey invalid!\n");
  $data=mysql_fetch_assoc($r);
  $tw=$data["wiki"];
} else {
  $tw=$argv[1];
  $dumpkey=md5(date("d.m.Y H:i:s"));
}

ob_implicit_flush(true);
ob_start("logmsg",2);

$sn=$tw."-p";//server name hosting the db
$dn=$tw."_p";//db name
echo "scrape dumper stage $jt\n";
echo "started at: ".date("d.m.Y H:i:s")."\n";
echo "batch mode: $batch\n";


echo "checking for other jobs currently active on this run\n";
//ob_flush();

$r=mysql_query("select add_info from `keys` where dumpkey='$dumpkey'");
if($r===false)
  die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
elseif(mysql_num_rows($r)==1) {
  $row=mysql_fetch_assoc($r);
  $a=unserialize($row["add_info"]);
  if($a["state"]=="working") {
    echo "error: another job runner (stage ".$a["stage"].") is currently active for $dumpkey, exiting\n";
    $no_cleanup=true;
    exit;
  }
  $status=serialize(array("state"=>"working","stage"=>$jt,"ts"=>time()));
  $status=mysql_escape_string($status);
  $r=mysql_query("update `keys` set add_info='$status' where dumpkey='$dumpkey';",$db_w);
  if($r===false)
    die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");  
}
echo "connecting to db host for target wiki $tw\n";

$db_r = mysql_connect("$sn.rrdb.toolserver.org", $ts_mycnf['user'], $ts_mycnf['password']);
if($db_w===false)
  die("can't connect to mysql, error ".mysql_errno($db_r).": ".mysql_error($db_r)."\n");
unset($ts_mycnf);
unset($ts_pw);
mysql_select_db("toolserver",$db_w);
$r=mysql_query("select domain,script_path from wiki where dbname='$dn';",$db_w);
if($r===false)
  die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
elseif(mysql_num_rows($r)!=1)
  die("target wiki does not exist\n");
$r=mysql_fetch_assoc($r);
$wu="http://".$r["domain"].$r["script_path"];

mysql_select_db($dn, $db_r);
mysql_select_db("u_harddisk_djrunner",$db_w);


//output buffer callback
function logmsg($buf) {
  global $lpath,$tw,$dumpkey,$jt,$batch;
  file_put_contents($lpath.$dumpkey.".".$tw.".".$jt.".txt",$buf,FILE_APPEND);
  if($batch)
    return '';
  else
    return $buf;
}
function cleanup() {
  global $db_w,$dumpkey,$no_cleanup,$jt;
  if(!$no_cleanup) {
    $status=serialize(array("state"=>"quitted","stage"=>$jt,"ts"=>time()));
    $status=mysql_escape_string($status);
    $r=mysql_query("update `keys` set add_info='$status' where dumpkey='$dumpkey';",$db_w);
    if($r===false)
      die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
  }
  echo "stopped at: ".date("d.m.Y H:i:s")."\n";
}
register_shutdown_function("cleanup");
//die($wu."\n");
?>