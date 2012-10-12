<?
// Step 1: process the articles already downloaded
//   Processing does:
//    - remove redlinks (article does not exist)
//    - remove editsection links
//   Processing does currently not:
//    - image removal
//    - prepend / append the skinfiles to have static dumps usable without AJAX
// Call: php process.php CFGFILE LISTFILE
// CFGFILE is the name of a configfile in the config/ directory
// LISTFILE is the name of an article list in the lists/ directory

require("common.php");
require("readmap.inc.php");
require("ganon/ganon.php");

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

//Read map
$mapfile="$listdir/$listfile.map";
if(!is_file($mapfile))
  die("Map file $mapfile does not exist. Did you run genmap.php?\n");
//$map=readmap($mapfile);

//check if download directory exists
$dlpath="$dump_dl_dir/$listfile";
if(!is_dir($dlpath))
  die("Download directory $dlpath does not exist.\n");

//check if processing output directory exists
$procpath="$dump_proc_dir/$listfile";
if(!is_dir($procpath)) {
  echo "Creating process output directory $procpath\n";
  mkdir($procpath);
} else
  echo "Process output directory $procpath already exists\n";
      
      
//open log file
$logfile="$logdir/$listfile.process.log";
$log_fp=fopen($logfile,"a");
if($log_fp===false)
  die("Error in fopen (logfile)\n");

$total_start=microtime(true);

$counter=0;
//Loop over the article IDs
while(($buf=fgets($fp))!==false) {
  $counter++;
    
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
  $textfile="$dlpath/$cvid.html";  
  $metafile="$dlpath/$cvid.meta";
  $outfile="$procpath/$cvid.html";
  
  //check if input files exist
  if(!is_file($textfile) || !is_file($metafile)) {
    echo "\x1b[1`";
    echo "$counter - FILES DO NOT EXIST";
    continue;
  }

  //check if output files already exists
  if(is_file($outfile) && false) {
    echo "\x1b[1`";
    echo "$counter - ALREADY EXISTS";
    $skip=$counter+70;
    continue;
  }
  
  //read in HTML text
  $html=file_get_dom($textfile);
  
  //process block
  
  //step 1: remove redlinks (non-existing articles)
  foreach($html("a[class]") as $element) {
    if($element->class=="new")
      $element->setOuterText($element->getInnerText());
  }
  
  //step 2: remove editsection links
  foreach($html("span.editsection") as $element)
    $element->delete();
  
  //write output file
  $d_fp=fopen($outfile,"w");
  if($d_fp===false)
    die("Error writing $outfile\n");
  $res=fwrite($d_fp,$html);
  if($res===false)
    die("Error writing $outfile\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $outfile\n");
  echo "Wrote to $outfile\n";

  $stop_time=microtime(true);
  fwrite($log_fp,"At $counter: processed $textfile id {$a['id']}) to $outfile in ".($stop_time-$start_time)." sec\n");

  //show progress
/*  echo "\x1b[1`";
  echo str_pad(" ",80," ");
  echo "\x1b[1`";*/
  echo "$counter - $cvid\n";
  if($counter==1) {
  echo $data."\n";
  exit;
  }
}

$total_stop=microtime(true);
$str="Total elapsed time: ".($total_stop-$total_start)." seconds\n";
echo "\n".$str;
fwrite($log_fp,$str);

//close logfile handle
fclose($log_fp);

//close list handle
fclose($fp);

?>