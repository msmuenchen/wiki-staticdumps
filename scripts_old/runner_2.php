<?
$jt=2; //job stage
// process articles: remove edit section links, add retrieval jobs for special media (math imgs)
/* states for this stage (only stage1, status2 is accepted as input):
  1 - open
  2 - processing finished
  5 - error during dl or compression
*/

include("common.php");

echo "stage $jt runner for target wiki: $tw\nproceed? (y/n)\n";
if(!$batch) {
  $r=trim(fgets(STDIN));
  if($r!="y")
    exit;
}

$res=mysql_query("select * from jobs where wiki='$tw' and type=$jt and state=1;",$db_w);
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
  
  echo "processing articles\n";
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
    /* code from here */
tar --strip-components -x -f ../tarballs/dewikiversity.2010-10-24-17.tar mnt/user-store/dewiki_static/articles/dewikiversity.33516.html
    
  }
  echo "\nfinished processing\n";
}
?>