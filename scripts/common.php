<?

//cd to scripts directory
chdir(dirname(__FILE__));

$scriptdir=getcwd();			//here are the scripts
$rootdir=realpath("$scriptdir/../");	//root directory of the checkout
$confdir=$rootdir."/config";		//configurations for each project
$listdir=$rootdir."/lists";		//article lists
$dump_dl_dir=$rootdir."/dump_dl";	//raw, downloaded articles (rendered!)
$dump_proc_dir=$rootdir."/dump_proc";	//processed articles ready for compression and bundling
$dump_out_dir=$rootdir."/dump_finished";//finished dump bundles, ready for use
$logdir=$rootdir."/logs";		//logs
$curl_ua="swd static wiki dump v0.1a";	//CURL user agent

if(is_file($confdir."/local.php"))	
  require($confdir."/local.php");	//include local changes
?>