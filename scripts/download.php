<?
// Step 1: download the articles from a list
// Call: php download.php CFGFILE LISTFILE
// CFGFILE is the name of a configfile in the config/ directory
// LISTFILE is the name of an article list in the lists/ directory

require("common.php");

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

//Instantiate CURL object
$c=curl_init();
if($c===false)
  die("Could not instantiate CURL\n");
curl_setopt_array($c,array(CURLOPT_USERAGENT=>$curl_ua, CURLOPT_ENCODING=>"gzip", CURLOPT_RETURNTRANSFER=>true, CURLINFO_HEADER_OUT=>true, CURLOPT_VERBOSE=>true, CURLOPT_HEADER=>true));

//Loop over the article IDs
while(($buf=fgets($fp))!==false) {
  //prepare article id
  $aid=trim($buf);
  
  //skip over blank lines
  if($aid=="")
    continue;
  
  //prepare URL
  echo "Getting article ID $aid from server\n";
  $url=$api_url."?action=parse&prop=text&format=json&oldid=$aid";
  echo "URL is $url\n";
  curl_setopt($c,CURLOPT_URL,$url);
  
  //run CURL request
  $ret=curl_exec($c);
  if($ret===false || curl_getinfo($c,CURLINFO_HTTP_CODE)!=200) {
    echo "CURL error\n";
    break;
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
  $article_html=$data["parse"]["text"]["*"];
  
  //write output file
  $outfile="$dlpath/$aid.html";
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
  $outfile="$dlpath/$aid.meta";
  $d_fp=fopen($outfile,"w");
  if($d_fp===false)
    die("Error writing $outfile\n");
  $res=fwrite($d_fp,print_r($data,true));
  if($res===false)
    die("Error writing $outfile\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $outfile\n");
  echo "Wrote to $outfile\n";
}

//close CURL object
curl_close($c);

//close list handle
fclose($fp);
?>