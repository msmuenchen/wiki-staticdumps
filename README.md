static wiki dumps
================

swd is a tool to create (usable) static dumps from any MediaWiki installation.

Requirements
----------------

Client-side swd requires php5, php5-curl and a direct connection to the server hosting the wiki.

Currently there is NO proxy support.

Server-side swd requires MediaWiki and its api.php with the action=parse functionality enabled.

Usage
================

Setup
----------------
1. Grab yourself a copy of the latest source-code.
2. Create the PHP file config/local.php and define the variable $curl_ua there to have an unique user agent.
3. Copy config/dewikipedia.php and adjust it to your own needs.

Running a dump
----------------
1. Grab yourself a newline-separated list of those revision IDs you'd like to see in the dump.
2. Save the list to (e.g.) lists/dewikipedia_20121001 (no file extension please!)
3. Download the HTML from the wiki:
   * cd scripts
   * php download.php dewikipedia.php dewikipedia_20121001
4. Check the output in the logfiles for errors, diagnose them if applicable
   * for singular HTTP fetch-errors (proxy or network gone down), delete the file in dump_dl/dewikipedia_20121001 and re-run the script
   * for any else errors: file a bug in github. I'll need the list and config file to debug.

