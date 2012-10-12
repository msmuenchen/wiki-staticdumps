<?
// Step 1: download the articles from a list
// Call: php download.php CFGFILE LISTFILE [SKIP]
// CFGFILE is the name of a configfile in the config/ directory
// LISTFILE is the name of an article list in the lists/ directory
// SKIP: optional, skip SKIP entries in the list

require("common.php");

$debug=false;

$cfgfile=$argv[1];
if(!is_file("$confdir/$cfgfile"))
  die("Broken config file $cfgfile\n");
require("$confdir/$cfgfile");

$listfile=$argv[2];
if(!is_file("$listdir/$listfile"))
  die("Broken list file $listfile\n");

//Open the list file
$fp=fopen("$listdir/$listfile","r");
if($fp===false)
  die("Error in fopen\n");

$dlpath="$dump_dl_dir/$listfile";
if(!is_dir($dlpath)) {
  echo "Creating download data directory $dlpath\n";
  mkdir($dlpath);
} else
  echo "Download directory $dlpath already exists\n";

//open log file
$logfile="$logdir/$listfile.download.log";
$log_fp=fopen($logfile,"a");
if($log_fp===false)
  die("Error in fopen (logfile)\n");

//Instantiate CURL object
$c=curl_init();
if($c===false)
  die("Could not instantiate CURL\n");
curl_setopt_array($c,array(CURLOPT_USERAGENT=>$curl_ua, CURLOPT_ENCODING=>"gzip", CURLOPT_RETURNTRANSFER=>true, CURLINFO_HEADER_OUT=>true, CURLOPT_VERBOSE=>true, CURLOPT_HEADER=>true));

//check if skipping is enabled
if(isset($argv[3]))
  $skip=$argv[3];
else
  $skip=0;
  
$total_start=microtime(true);

$counter=0;
//Loop over the article IDs
while(($buf=fgets($fp))!==false) {
  $counter++;
  
  //skip, if needed
  if($counter<$skip) {
    echo "\x1b[1`";
    echo "$counter - SKIP TO $skip";
    continue;
  }

  begin:
  
  //start timer
  $start_time=microtime(true);
  
  //prepare article id
  $data=trim($buf);

  //skip over blank lines
  if($data=="") {
    echo "\x1b[1`";
    echo "$counter - BLANK LINE";
    continue;
  }
  
  $a=array();
  list($a["id"],$a["title"],$a["aid"],$a["is_redir"],$a["ns"])=explode("|",$data);
  $cvid=$a["aid"];
  $outfile="$dlpath/$cvid.html";  
  $metafile="$dlpath/$cvid.meta";
  
  //check if files already exist
  if(is_file($outfile) && is_file($metafile)) {
    echo "\x1b[1`";
    echo "$counter - ALREADY EXISTS";
    $skip=$counter+70;
    continue;
  }

  //start output buffering in case error happens
  ob_start();
  
  //prepare URL
  echo "Getting article ID $cvid from server\n";
  $url=$api_url."?action=parse&prop=text|displaytitle&format=json&maxlag=5&oldid=$cvid";
  echo "URL is $url\n";
  curl_setopt($c,CURLOPT_URL,$url);
  
  //run CURL request
  $ret=curl_exec($c);
  
  //check for CURL fail => fatal error
  if($ret===false) {
    fwrite($log_fp,"At $counter: Error while downloading article with curID $cvid\n");
    $ob=ob_get_clean(); fwrite($log_fp,$ob);
    $str="CURL fatal error: ".curl_error($c)."\n";
    fwrite($log_fp,$str);
    die("\n$str");
  }
  
  //check for HTTP != 200
  if(curl_getinfo($c,CURLINFO_HTTP_CODE)!=200) {
    fwrite($log_fp,"At $counter: Error while downloading article with curID $cvid\n");
    $ob=ob_get_clean(); fwrite($log_fp,$ob);
    $str="HTTP retcode: ".curl_getinfo($c,CURLINFO_HTTP_CODE)."\n";
    fwrite($log_fp,$str);
    echo "\n".$str;
    sleep(5);
    goto begin;
  }
  
  //get request info
  $header_size=curl_getinfo($c,CURLINFO_HEADER_SIZE);
  $header=substr($ret, 0, $header_size);
  $body=substr($ret, $header_size);
  
  //dump request info
  echo "Data was ".strlen($body)." bytes long\n";
  echo "CURL info: ".print_r(curl_getinfo($c),true)."\n";
  echo "Out header: ".curl_getinfo($c,CURLINFO_HEADER_OUT)."\n";
  echo "In header: $header\n";
  
  //decode the JSON object
  $data=json_decode($body,true);
  
  //check for API error
  if(isset($data["error"])) {
    if($data["error"]["code"]=="maxlag") {
      echo "At $counter: encountered db lag, sleeping 5 seconds and retrying...\n";
      sleep(5);
      goto begin;
    }
    fwrite($log_fp,"At $counter: Error while downloading article with curID $cvid: {$data['error']['code']} // {$data['error']['info']}\n");
    continue;
  }
  
  $article_html=$data["parse"]["text"]["*"];
  $data["parse"]["text"]["*"]="removed";
  $aname=$data["parse"]["title"];
  
  //write output file
  $d_fp=fopen($outfile,"w");
  if($d_fp===false)
    die("Error writing $outfile\n");
  $res=fwrite($d_fp,$article_html);
  if($res===false)
    die("Error writing $outfile\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $outfile\n");
  echo "Wrote to $outfile\n";

  //write meta to output file
  $d_fp=fopen($metafile,"w");
  if($d_fp===false)
    die("Error writing $metafile\n");
  $res=fwrite($d_fp,json_encode($data["parse"],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP));
  if($res===false)
    die("Error writing $metafile\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $metafile\n");
  echo "Wrote to $metafile\n";

  $stop_time=microtime(true);
  $aname_safe=preg_replace('/[^(\x20-\x7F)]*/','', $aname);
  fwrite($log_fp,"At $counter: downloaded article $aname_safe (curID $cvid, aid {$a['id']}) to $outfile, metadata to $metafile in ".($stop_time-$start_time)." sec\n");

  //delete output buffer if nothing happened...
  ob_end_clean();
  
  //show progress
  echo "\x1b[1`";
  echo str_pad(" ",80," ");
  echo "\x1b[1`";
  echo "$counter - $aname_safe - $cvid";
  
}

$total_stop=microtime(true);
$str="Total elapsed time: ".($total_stop-$total_start)." seconds\n";
echo "\n".$str;
fwrite($log_fp,$str);

//close CURL object
curl_close($c);

//close logfile handle
fclose($log_fp);

//close list handle
fclose($fp);

?>