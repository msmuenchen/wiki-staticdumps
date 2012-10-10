<?
$jt=1; //job stage
/* states for this stage:
  1 - open
  2 - download ok
  3 - page is redirect
  4 - error (page deleted)
  5 - error during dl or compression
*/

include("common.php");

echo "stage $jt runner for target wiki: $tw\nproceed? (y/n)\n";
if(!$batch) {
  $r=trim(fgets(STDIN));
  if($r!="y")
    exit;
}

$res=mysql_query("select * from jobs where dumpkey='$dumpkey' and type=$jt and state=1;",$db_w);
if($res===false)
  die("mysql error ".mysql_errno($db_w).": ".mysql_error($db_w)."\n");
elseif(mysql_num_rows($res)==0)
  die("no open jobs for stage $jt runner\n");
else {
  $cnt=mysql_num_rows($res);
  echo "$cnt jobs left. run? (y/n)\n";
  if(!$batch) {
    $r=trim(fgets(STDIN));
    if($r!="y")
      exit;
  }
  
  echo "downloading articles\n";
  $c=0;
  while($row=mysql_fetch_assoc($res)) {
    if(is_file($cpath.$dumpkey)) {
      $cmd=trim(file_get_contents($cpath.$dumpkey));
      if($cmd=="exit") {
        echo "got exit command\n";
        echo "stopped at: ".date("d.m.Y H:i:s")."\n";
        unlink($cpath.$dumpkey);
        exit;
      }
    }
    $c++;
    $data=unserialize(file_get_contents($wu."api.php?action=query&prop=revisions&revids=".$row["article_revid"]."&format=php"));
    if(isset($data["query"]["badrevids"])) {
      echo "job ".$row["id"]." error: article got deleted\n";
      $r=mysql_query("update jobs set state=4 where id='".$row["id"]."';",$db_w);
      continue;
    }
    if($row["article_is_redir"]==1) {
      echo "job ".$row["id"]." warn: article is redirect\n";
      $r=mysql_query("update jobs set state=3 where id='".$row["id"]."';",$db_w);
      continue;
    }
    $afname=$apath.$tw.".".$row["article_id"].".html";
    $cmd="wget -q '".$wu."index.php?oldid=".$row["article_revid"]."&action=render' -O '$afname'";
    system($cmd,$ret);
    if($ret==0) {
      $tarname=$row["dumpkey"].".$tw.".date("Y-m-d-H").".tar";
      $tarpath=$tpath.$tarname;
      if(is_file($tarpath))
        $cmd="tar --remove-files -r -f '$tarpath' '$afname' &> /dev/null";
      else
        $cmd="tar --remove-files -c -f '$tarpath' '$afname' &> /dev/null";
      system($cmd,$ret);
      if($ret==0) {
        if(!$batch) echo "\r";
        $r=mysql_query("update jobs set state=2 where id='".$row["id"]."';",$db_w);
        $r2=mysql_query("insert into jobs set type=2,state=1,article_id='".$row["article_id"]."',article_revid='".$row["article_revid"]."',article_title='".mysql_escape_string($row["article_title"])."',article_is_redir='".$row["article_is_redir"]."', dumpkey='".$row["dumpkey"]."',add_info='".mysql_escape_string(serialize(array("tarfile"=>$tarname)))."';",$db_w);
        if($r!==false&&$r2!==false)
          echo "download $c of $cnt: article ".$row["article_id"]." (job ".$row["id"].") to tarfile $tarname\n";
        else
          echo "download $c of $cnt: article ".$row["article_id"]." (job ".$row["id"].") to tarfile $tarname ok but job change failed\n";
      } else {
        echo "job ".$row["id"]." error: tar failed\n";
        $r=mysql_query("update jobs set state=5 where id='".$row["id"]."';",$db_w);
        continue;
      }
    } else {
      echo "job ".$row["id"]." error: wget failed\n";
      $r=mysql_query("update jobs set state=5 where id='".$row["id"]."';",$db_w);
      continue;
    }
//    if($c==10) exit;
    sleep(1);
  }
  echo "\nfinished copying\n";
}
?>