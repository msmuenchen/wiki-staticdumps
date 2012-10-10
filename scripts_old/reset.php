<?
$stage=$argv[3];
$sbefore=$argv[4];
$safter=$argv[5];
//print_r($argv);
include("common.php");

echo "stage changer $sbefore -> $safter for stage $stage, target wiki: $tw\nproceed? (y/n)\n";
if(!$batch) {
  $r=trim(fgets(STDIN));
  if($r!="y")
    exit;
}

$res=mysql_query("select * from jobs where wiki='$tw' and type=$stage and state=$sbefore;",$db_w);
if($res===false)
  die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
elseif(mysql_num_rows($res)==0)
  die("no jobs to change for stage $stage and oldstatus=$sbefore\n");
else {
  $cnt=mysql_num_rows($res);
  echo "$cnt jobs to change. run? (y/n)\n";
  if(!$batch) {
    $r=trim(fgets(STDIN));
    if($r!="y")
      exit;
  }
  
  echo "changing status values\n";
  $c=0;
  while($row=mysql_fetch_assoc($res)) {
    $r=mysql_query("update jobs set state=$safter where id='".$row["id"]."';");
    if($r===false)
      die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
    else
      echo "changed ".$row["id"]." from ".$row["state"]." to $safter\n";
  }
  echo "\nfinished copying\n";
}
?>