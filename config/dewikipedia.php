<?
$name="dewiki";				//project name (free to choose)
$wiki_url="http://de.wikipedia.org";	//base URL of wiki
$api_url="$wiki_url/w/api.php";			//API endpoint
$siteinfo=unserialize(file_get_contents("$confdir/dewikipedia.info"));
$siteinfo=$siteinfo["query"];
?>