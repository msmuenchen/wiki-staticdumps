<?
// helper include file: read a map

//increase the memory limit
ini_set('memory_limit', '3096M');

function readmap($filename) {
  echo "Loading map, fs ".filesize($filename)." bytes...\n";
  $start_time=microtime(true);
  $fp=fopen($filename,"r");
  if($fp===false)
    die("could not open mapfile $filename\n");
  $buf=fread($fp,filesize($filename));
  if($buf===false)
    die("Error in fread for mapfile $filename\n");
  $ret=fclose($fp);
  if($ret===false)
    die("Error in fclose for mapfile $filename\n");
  if(strlen($buf)!=filesize($filename))
    die("Error while reading mapfile $filename: did not read whole mapfile\n");
  echo "Map read, unserializing...\n";
  $buf=unserialize($buf);
  if($buf===false)
    die("Error while reading mapfile $filename: unserialize failed\n");
  $stop_time=microtime(true);
  echo "Map loaded in ".($stop_time-$start_time)." secs, ".sizeof($buf)." entries\n";
  $mem=memory_get_usage();
  $peak_mem=memory_get_peak_usage();
  echo "$mem bytes allocated right now, peak was $peak_mem\n";
  return $buf;
}
?>