<?
// Step 2: process the articles from a list and create a Name=>RevID mapping
// Call: php genmap.php CFGFILE LISTFILE
// CFGFILE is the name of a configfile in the config/ directory
// LISTFILE is the name of an article list in the lists/ directory

//increase memory limit. the map will be real huge
ini_set('memory_limit', '3096M');

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

$map=array();

$counter=0;

//start timer
$start_time=microtime(true);

echo "Starting...\n";

//Loop over the article IDs
while(($buf=fgets($fp))!==false) {
  $counter++;
  
  
  //prepare article id
  $data=trim($buf);
  
  //skip over blank lines
  if($data=="") {
    echo "$counter - BLANK LINE\n";
    continue;
  }

  $a=array();
  list($a["id"],$a["title"],$a["aid"],$a["is_redir"],$a["ns"])=explode("|",$data);
  $key=$a["ns"]."|".$a["title"];
  if(isset($map[$key])) {
    echo "In $key: key already exists, pointer to ".$map[$key]."\n";
  }
  $map[$key]=$a["aid"];

  if($counter%100000==0) {
    echo "\x1b[1`";
    echo "$counter - read";
  }
}

$stop_time=microtime(true);
echo "\nTotal: ".sizeof($map)." entries read in ".($stop_time-$start_time)." seconds\n";

$data=serialize($map);

echo "Serialized map, length is ".strlen($data)."\n";

//Open the map file
$fp_map=fopen("$listdir/$listfile.map","w");
if($fp_map===false)
  die("Error in fopen\n");

//write map to file
$ret=fwrite($fp_map,$data);
if($ret===false)
  echo "Failed at writing to mapfile\n";
else
  echo "Wrote $ret bytes to file\n";

//close mapfile handle
fclose($fp_map);

//close list handle
fclose($fp);

?>