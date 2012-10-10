<?
// Step 1: download the articles from a list
// Call: php download.php CFGFILE LISTFILE
// CFGFILE is the name of a configfile in the config/ directory
// LISTFILE is the name of an article list in the lists/ directory

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
$log_fp=fopen($logfile,"w");
if($log_fp===false)
  die("Error in fopen (logfile)\n");

//Instantiate CURL object
$c=curl_init();
if($c===false)
  die("Could not instantiate CURL\n");
curl_setopt_array($c,array(CURLOPT_USERAGENT=>$curl_ua, CURLOPT_ENCODING=>"gzip", CURLOPT_RETURNTRANSFER=>true, CURLINFO_HEADER_OUT=>true, CURLOPT_VERBOSE=>true, CURLOPT_HEADER=>true));

$total_start=microtime(true);

$counter=0;
//Loop over the article IDs
while(($buf=fgets($fp))!==false) {
  //start timer
  $start_time=microtime(true);
  
  //prepare article id
  $data=trim($buf);
  $a=array();
  list($a["id"],$a["title"],$a["aid"],$a["is_redir"],$a["ns"])=explode("|",$data);
  $cvid=$a["aid"];
  //skip over blank lines
  if($cvid=="")
    continue;
  
  //prepare URL
  if($debug)
    echo "Getting article ID $cvid from server\n";
  $url=$api_url."?action=parse&prop=text|displaytitle&format=json&oldid=$cvid";
  if($debug)
    echo "URL is $url\n";
  curl_setopt($c,CURLOPT_URL,$url);
  
  //run CURL request
  $ret=curl_exec($c);
  if($ret===false || curl_getinfo($c,CURLINFO_HTTP_CODE)!=200) {
    fwrite($log_fp,"Error while downloading article with curID $cvid\n");
    echo "CURL error\n";
    break;
  }
  
  //get request info
  $header_size=curl_getinfo($c,CURLINFO_HEADER_SIZE);
  $header=substr($ret, 0, $header_size);
  $body=substr($ret, $header_size);
  
  //dump request info
  if($debug) {
    echo "Data was ".strlen($body)." bytes long\n";
    echo "CURL info: ".print_r(curl_getinfo($c),true)."\n";
    echo "Out header: ".curl_getinfo($c,CURLINFO_HEADER_OUT)."\n";
    echo "In header: $header\n";
  }
  
  //decode the JSON object
  $data=json_decode($body,true);
  
  //check for API error
  if(isset($data["error"])) {
    fwrite($log_fp,"Error while downloading article with curID $cvid: {$data['error']['code']} // {$data['error']['info']}\n");
    continue;
  }
  
  $article_html=$data["parse"]["text"]["*"];
  $data["parse"]["text"]["*"]="removed";
  $aname=$data["parse"]["title"];
  
  //write output file
  $outfile="$dlpath/$cvid.html";
  $d_fp=fopen($outfile,"w");
  if($d_fp===false)
    die("Error writing $outfile\n");
  $res=fwrite($d_fp,$article_html);
  if($res===false)
    die("Error writing $outfile\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $outfile\n");
  if($debug)
    echo "Wrote to $outfile\n";

  //write meta to output file
  $metafile="$dlpath/$cvid.meta";
  $d_fp=fopen($metafile,"w");
  if($d_fp===false)
    die("Error writing $metafile\n");
  $res=fwrite($d_fp,json_encode($data["parse"],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP));
  if($res===false)
    die("Error writing $metafile\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $metafile\n");
  if($debug)
    echo "Wrote to $metafile\n";

  $stop_time=microtime(true);
  $aname_safe=preg_replace('/[^(\x20-\x7F)]*/','', $aname);
  fwrite($log_fp,"Downloaded article $aname_safe (curID $cvid, aid {$a['id']}) to $outfile, metadata to $metafile in ".($stop_time-$start_time)." sec\n");

  //show progress
  echo "\x1b[1`";
  echo str_pad(" ",60," ");
  echo "\x1b[1`";
  echo "$counter - $aname_safe - $cvid";
  
  $counter++;
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