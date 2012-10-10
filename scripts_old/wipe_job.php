<?
$jt=99;
include("common.php");

echo "about to wipe all data for dump run $dumpkey at wiki $tw. proceed (y/n)?\n";
if(!$batch) {
  $r=trim(fgets(STDIN));
  if($r!="y")
    exit;
}
$r=mysql_query("delete from jobs where dumpkey='$dumpkey';",$db_w);
if($r===false)
  die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
$r=mysql_query("delete from `keys` where dumpkey='$dumpkey';",$db_w);
if($r===false)
  die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
echo("rm -f ".$tpath.$dumpkey.".*.tar");
echo "jobs wiped.\n";
?>