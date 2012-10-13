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

//this function takes a displaytitle (e.g. "Portal:Brot") and returns the numeric ID of the
//namespace
function getNSID($target,$nsmap) {
  $colonpos=strpos($target,":");
  if($colonpos===false) //no colon, this is article (NS 0)
    return array(0,$target);
  $ns=substr($target,0,$colonpos); //namespace string
  $pname=substr($target,$colonpos+1); //page name
  foreach($nsmap as $name=>$id)
    if($ns==$name)
      return array($id,$pname);
  return 0;
}

//this function takes a displaytitle and returns the mapped revision-id
//return 0 if no revid can be found
//TODO: Resolve redirects
function getRevID($target,$nsmap) {
  global $map;
  global $counter;
  list($ns,$pname)=getNSID($target,$nsmap);
  $pname_enc=base64_encode($pname);
  $key="$ns|$pname_enc";
//  echo "link $target resolves to ns $ns and pagename $pname // enc $pname_enc and key -$key-\n";
  if(isset($map[$key]))
    return $map[$key];
  
  echo "Link $target in job $counter is invalid\n";
  return 0;
}
//this function takes a ganon node (html-rootnode) and applies transforms
//common to AJAX and static output
function process_common($node) {
  //step 1: remove redlinks (non-existing articles)
  foreach($node("a[class]") as $element) {
    if($element->class=="new")
      $element->setOuterText($element->getInnerText());
  }
  
  //step 2: remove editsection links
  foreach($node("span.editsection") as $element)
    $element->delete();
}

//this function takes a ganon node (html-rootnode) and applies transforms
//needed only for static-page output (each html-page is a independent fullpage
//after transform)
function process_static($node) {
  global $siteinfo;
  //create namespace mapping
  $nsmap=buildNSReverse($siteinfo["namespaces"],$siteinfo["namespacealiases"]);
  
  $apath=$siteinfo["general"]["articlepath"];
  //FIXME: This only works for Wikimedia-style urls. Need to safely create regex...
  $apath="/wiki/";

  //step 2: rewrite links to articles
  foreach($node("a[href]") as $element) {
    if(substr($element->href,0,strlen($apath))==$apath) {
      $target=substr($element->href,strlen($apath));
//      $target=str_replace("_"," ",$target);
      $target=urldecode($target);
      
      //link to segment => do not deliver section and hash to getRevId!
      $hashpos=strpos($target,"#");
      if($hashpos!==false) {
        $section=substr($target,$hashpos+1);
        $pname=substr($target,0,$hashpos);
      } else {
        $section="";
        $pname=$target;
      }
      $revid=getRevId($pname,$nsmap);
      
      if($section=="")
        $element->href="{$revid}_static.html";
      else
        $element->href="{$revid}_static.html#$section";
      
//      echo "static: got a wikilink to $target (pagename '$pname', section '$section') with mapped revid $revid\n";
      
    } elseif(substr($element->href,0,1)=="#") {
      //fragment (section) links stay as-is
//      echo "static: got a fragment link to ".$element->href."\n";
    } else {
      //external links stay as-is, maybe disable?
      //TODO: For Wikimedia, wtf to do with Commons links and material? Include in dump?
//      echo "static: got a ext link to ".$element->href."\n";
    }
    
  }
}

//this function takes a ganon node (html-rootnode) and applies transforms
//needed only for ajax-page output (each html-page depends on skin for display
//after transform)
function process_ajax($node) {
  global $siteinfo;
  //create namespace mapping
  $nsmap=buildNSReverse($siteinfo["namespaces"],$siteinfo["namespacealiases"]);
  
  $apath=$siteinfo["general"]["articlepath"];
  //FIXME: This only works for Wikimedia-style urls. Need to safely create regex...
  $apath="/wiki/";

  //step 2: rewrite links to articles
  foreach($node("a[href]") as $element) {
    if(substr($element->href,0,strlen($apath))==$apath) {
      $target=substr($element->href,strlen($apath));
//      $target=str_replace("_"," ",$target);
      $target=urldecode($target);
      
      //link to segment => do not deliver section and hash to getRevId!
      $hashpos=strpos($target,"#");
      if($hashpos!==false) {
        $section=substr($target,$hashpos+1);
        $pname=substr($target,0,$hashpos);
      } else {
        $section="";
        $pname=$target;
      }
      $revid=getRevId($pname,$nsmap);
      
      if($section=="")
        $jump="load('$revid'); return false;";
      else
        $jump="load('$revid','$section'); return false;";
      $element->href="";
      $element->onClick=$jump;
      
//      echo "ajax: got a wikilink to $target (pagename '$pname', section '$section') with mapped revid $revid\n";
      
    } elseif(substr($element->href,0,1)=="#") {
      $target=substr($element->href,1);
      $element->href="";
      $element->onClick="jump('$target'); return false;";
      echo "ajax: got a fragment link to $target\n";
    } else {
      //external links stay as-is, maybe disable?
      //TODO: For Wikimedia, wtf to do with Commons links and material? Include in dump?
//      echo "static: got a ext link to ".$element->href."\n";
    }
  }   
}

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
echo "Reading map...\n";
$map=readmap($mapfile);
echo "Map read\n";

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
    if($counter%10000==-1) {
      echo "\x1b[1`";
      echo "$counter - SKIP TO $skip";
    }
    continue;
  }
    
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
  $outfile_ajax="$procpath/{$cvid}_ajax.html";
  $outfile_static="$procpath/{$cvid}_static.html";
  
  //check if input files exist
  if(!is_file($textfile) || !is_file($metafile)) {
    echo "\x1b[1`";
    echo "$counter - FILES DO NOT EXIST";
    continue;
  }

  //check if output files already exists
  if(is_file($outfile_ajax) && is_file($outfile_static)) {
    echo "\x1b[1`";
    echo "$counter - ALREADY EXISTS";
    $skip=$counter+70;
    continue;
  }
  
  //read in HTML text
  $html_static=file_get_dom($textfile);
  $html_ajax=file_get_dom($textfile);
  
  process_common($html_static);
  process_common($html_ajax);
  
  process_static($html_static);
  process_ajax($html_ajax);
  
  //write ajax output file
  $d_fp=fopen($outfile_ajax,"w");
  if($d_fp===false)
    die("Error writing $outfile_ajax\n");
  $res=fwrite($d_fp,$html_ajax);
  if($res===false)
    die("Error writing $outfile_ajax\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $outfile_ajax\n");
  echo "Wrote to $outfile_ajax\n";

  //write static html outfile
  $d_fp=fopen($outfile_static,"w");
  if($d_fp===false)
    die("Error writing $outfile_static\n");
  $res=fwrite($d_fp,$html_static);
  if($res===false)
    die("Error writing $outfile_static\n");
  $res=fclose($d_fp);
  if($res===false)
    die("Error writing $outfile_static\n");
  echo "Wrote to $outfile_static\n";
  
  $stop_time=microtime(true);
  fwrite($log_fp,"At $counter: processed $textfile id {$a['id']}) to $outfile_static and $outfile_ajax in ".($stop_time-$start_time)." sec\n");

  //show progress
  echo "\x1b[1`";
  echo str_pad(" ",80," ");
  echo "\x1b[1`";
  echo "$counter - $cvid\n";
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