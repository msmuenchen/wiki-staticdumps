<? 
$jt=0;
include("common.php");


echo "initial loader for target wiki: $tw\nproceed? (y/n)\n";
if(!$batch) {
  $r=trim(fgets(STDIN));
  if($r!="y")
    exit;
}

echo "loading page list (this can and will take time)\n";
$res=mysql_query("select /* SLOW_OK */ page_id,page_title,page_latest,page_is_redirect from page where page_namespace=0;",$db_r);
if($res===false)
  die("mysql error ".mysql_errno($db_r).": ".mysql_error($db_r)."\n");
elseif(mysql_num_rows($res)==0)
  die("target wiki is empty?\n");
else {
  $cnt=mysql_num_rows($res);
  echo "target wiki has $cnt entries. import? (y/n)\n";
  if(!$batch) {
    $r=trim(fgets(STDIN));
    if($r!="y")
      exit;
  }
  
  /*
  echo "checking for stale job entries\n";  
  $res2=mysql_query("select id from jobs where wiki='$tw';",$db_w);
  if($res2===false)
    die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
  elseif(mysql_num_rows($res2)!=0) {
    echo "there are already ".mysql_num_rows($res2)." rows present for $tw. kill them or quit? (k/q)\n";
    if(!$batch) {
      $r=trim(fgets(STDIN));
      if($r!="k")
        exit;
    }
    echo "deleting stale job entries\n";
    $res3=mysql_query("delete from jobs where wiki='$tw';",$db_w);
    if($res3===false)
      die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
    echo mysql_affected_rows($db_w)." rows deleted\n";
  }*/
  
  echo "creating job. id is $dumpkey.\n";
  $status=serialize(array("state"=>"working","stage"=>0,"ts"=>time()));
  $status=mysql_escape_string($status);
  $r=mysql_query("insert into `keys` set wiki='$tw', dumpkey='$dumpkey',add_info='$status';",$db_w);
  if($r===false)
    die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
  echo "job created\n";
  
  echo "copying data\n";
  $c=0;
  while($row=mysql_fetch_assoc($res)) {
    $c++;
    $q="insert into jobs set type=1,state=1,article_id='".$row["page_id"]."',article_revid='".$row["page_latest"]."',article_title='".mysql_escape_string($row["page_title"])."',article_is_redir='".$row["page_is_redirect"]."', dumpkey='$dumpkey';";
//    echo $q."\n";
    $res4=mysql_query($q,$db_w);
    if($res4===false)
      die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
    echo "\rcopied row $c of $cnt, last id ".mysql_insert_id($db_w);
  }
  echo "\nfinished copying\n";
}
?>