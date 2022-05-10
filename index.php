<?php

$defaultCollection = "iiif_collection_explorer.json";
$defaultTitle = "Exploring IIIF Collections";
$defaultPath = "https://research.ng-london.org.uk/iiif-projects/json/";
$defaultDir = "/var/www/www-research/iiif-projects/json/";

$root_parts = pathinfo($defaultCollection);
$rootName = $root_parts["filename"];

$extraCSS = false;
$extraJS = false;

if (!isset($_GET["search"])) {$_GET["search"] = false;}
$limitedMessage = false;
$cats = false;

if (!isset($_GET["root"]) or !$_GET["root"])
  {$_GET["root"] = $defaultCollection;}
  
if ($_GET["root"] == "debug")
  {$debug = true;
   $_GET["root"] = $defaultCollection;}
else
  {$debug = false;}

if (filter_var($_GET["root"], FILTER_VALIDATE_URL) === FALSE) {
  $_GET["root"] = $defaultPath.$_GET["root"];
  }

$tjson = getsslJSONfile ($_GET["root"], true);    
if(!$tjson) {$tjson = getsslJSONfile ($_GET["root"].".json", true);}

$rootDets = false;

if (isset($tjson["@context"])) {  
  if (is_array($tjson["@context"])) 
    {
    if (in_array("http://iiif.io/api/presentation/3/context.json", $tjson["@context"]))
      {$rootDets = $tjson;} 
    else if (in_array("https://iiif.io/api/presentation/3/context.json", $tjson["@context"]))
      {$rootDets = $tjson;}    
    }
  else if ($tjson["@context"] == "http://iiif.io/api/presentation/3/context.json")
    {$rootDets = $tjson;}
  }

if (!$rootDets)
  {$rootDets =  getsslJSONfile ($defaultPath.$defaultCollection, true);
   $_GET["root"] = $defaultCollection;}

if (isset($rootDets["label"]))
  {$title = getLangValue ($rootDets["label"], "en");}
else
  {$title = "Unknown";}

if (isset($rootDets["summary"]))
  {$comment = getLangValue ($rootDets["summary"], "en");}
else
  {$comment = "";}

$hasCols = false;
$hasTable = false;
$hasPDF = false;
$children = array();
$avrList = "";
$cats = array();
$tableHTML = false;
$tableJS = false;

if ($debug)
  {
  $files = scandir($defaultDir);
  $files = array_slice($files, 2); //removes "." and ".."
  
  foreach ($files as $k => $name)
    {
    if($name != "spring2012.json"){
      $cats[] = '{"manifestId": "'.$defaultPath.$name.'"}';}
    }
  }
else if ($rootDets["type"] == "Collection")
  {
  if (isset($rootDets["metadata"]))
    {foreach ($rootDets["metadata"] as $kk => $md)
      {$out = getMDLVpair($md);
       if ($out["label"] == "Related PDF")
        {$hasPDF = true;}}}
            
  foreach ($rootDets["items"] as $k => $avr)
    {
    if ($avr["type"] == "Collection")
      {$hasCols = true;}    
    
    if (isset($avr["label"]))
      {$clabel = getLangValue ($rootDets["label"], "en");
       $children[$clabel] = $avr["id"];
       $path_parts = pathinfo($avr["id"]);
       $avrList .= formatIIIFCard ($avr["id"], "./?root=".$path_parts["filename"]);
       $check = getsslJSONfile($avr["id"]);}
    else
      {$check = false;}
    
    if ($check)
      {
      // Allows the presentation of Manifests with separate PDFs rather than
      // A PDF for the collection links to multipole Manifests
      if (isset($check["metadata"]) and !$hasPDF)
        {foreach ($check["metadata"] as $kk => $md)
          {$out = getMDLVpair($md);
           if ($out["label"] == "Related PDF")
            {$hasCols = true;}}}
      $cats[] = $avr["id"];
      } 
    }
  }
else if ($rootDets["type"] == "Table")
  {
  $hasTable = true;
  $hd = array();
  
  $table = array();
  
  if (isset($rootDets["metadata"]))
    {foreach ($rootDets["metadata"] as $kk => $md)
      {$out = getMDLVpair($md);
       if ($out["label"] == "Related PDF")
        {$hasPDF = true;}}}
  
  $rootDets = buildTable ($rootDets);
  
  $extraJS = $rootDets["table"]["jsScripts"];
  $tableHTML = $rootDets["table"]["html"];
  $tableJS = $rootDets["table"]["js"];
  $extraCSS .= $rootDets["table"]["cssScripts"];
  }
else
  { 
  $check = getsslJSONfile($rootDets["id"]);
    if ($check)
      {$cats[] = $rootDets["id"];} 
  }
    
if ($avrList)  
  {$list = "<div class=\"alert alert-light\" role=\"alert\"><h4>Available 
    <span style=\"color:#0075a4;font-weight:bold;\">I</span><span style=\"color:#ef2638;font-weight:bold;\">I</span><span style=\"color:#0075a4;font-weight:bold;\">I</span><span style=\"color:#ef2638;font-weight:bold;\">F</span> Resources</h4>$avrList</div>";}
else
  {$list = false;}

if ($hasCols)
  {$cats = false;
   $M3Display = "none";}
else if ($hasTable)
  {$cats = false;
   $M3Display = "none";
   }
else
  {$list = false;
   if (count($cats) == 1)
    {$ms = "
        'windows': [
	  {'manifestId': '$cats[0]',
	  view: 'gallery',
	   },
	],
      ";}
   else
    {$ms = '"workspace": {"isWorkspaceAddVisible": true},';}
  
  $cats = "{'manifestId': '".implode("'},\n\t\t{'manifestId': '", $cats)."'},\n";
   $M3Display = "block";}

$mda = array();

foreach ($rootDets["metadata"] as $k => $md)
  {$tmp = getMDLVpair ($md);  
   $mda[$tmp["label"]] = $tmp["value"];}

if (isset($mda["Manifest Author"]))
  {unset($mda["Manifest Author"]);}

$bc = array();
if (isset($mda["Related Projects"]) and $mda["Related Projects"])
  {  
  if (!is_array($mda["Related Projects"]))
    {$mda["Related Projects"] = array($mda["Related Projects"]);}
   
  foreach ($mda["Related Projects"] as $rpk => $rpv)
   {
  // in case links have been wrapped in html
   if (preg_match("/^.+href=.(http.+)[\"][>].+$/", $rpv, $m))
    {$rpv = $m[1];}
    
   $path_parts = pathinfo($rpv);

   if ($path_parts["filename"] == $rootName)
    {$bc[] = "<a href=\"./?root=".$path_parts["filename"]."\" class=\"alert-link\">Home</a>";}
   else
    {$check = getsslJSONfile($rpv, true);
     if ($check)
      {$bctitle = getLangValue ($check["label"], "en");
       $bc[] = "<a href=\"./?root=".$path_parts["filename"]."\" class=\"alert-link\">$bctitle</a>";}}   
    }
  unset($mda["Related Projects"]);
  }
  
$bchtml = "<nav aria-label=\"breadcrumb\" style=\"padding-top:12px;\"><ol class=\"breadcrumb\">";
foreach ($bc as $bck => $bcl)
  {$bchtml .= "<li class=\"breadcrumb-item\">$bcl</li>";}

if ($title == $defaultTitle)
  {$dtitle = "Home";}
else
  {$dtitle = $title;}
  
$bchtml .= "<li class=\"breadcrumb-item active\" aria-current=\"page\">$dtitle</li></ol></nav>";

$bc = $bchtml;
    
if (isset($mda["Related PDF"]) and $mda["Related PDF"])
  {$pdfscr = $mda["Related PDF"];      
   unset($mda["Related PDF"]);
   }
else
  {$pdfscr = false;}
 
if ($pdfscr)
  {
  ob_start();
  echo <<<END
   <div class="col flex-grow-1"">
     <div class="h-100 d-flex flex-column">
        <div class="row justify-content-center flex-grow-1">               
                  
          <div class="h-100" style="position:relative;min-height:400px;display:block;" id="pdfviewer">  
            <iframe src="$pdfscr"  width="100%" height="100%" frameborder="0" allowfullscreen="" style="position:absolute; top:0; left: 0"></iframe>
          </div>
              
        </div>                
      </div>
    </div>  
END;
  $pdfHTML = ob_get_contents();
  ob_end_clean(); // Don't send output to client      
  }
else
  {$pdfHTML = false;}
  
if ($M3Display == "block")
  {

  ob_start();
  echo <<<END
    <div class="col flex-grow-1">							
      <div class="h-100 d-flex flex-column">
        <div class="row justify-content-center flex-grow-1">
               
          <div class="h-100" style="position:relative;min-height:400px;display:$M3Display;" id="iiifviewerM"></div>
              
        </div>                
      </div>
    </div> 
END;
  $m3HTML = ob_get_contents();
  ob_end_clean(); // Don't send output to client 
  
  ob_start();
  echo <<<END
  const config = {
      id: 'iiifviewerM',
      window: {
        views: [{ key: 'single' },{ key: 'gallery' }]},
      $ms
      "catalog": [
		$cats
        ],       
       };

    myMViewer = myMirador.viewer(config, [
      mymiradorImageToolsPlugin,
      //AnnotationPlugin,
      ]);  
END;
  $m3JS = ob_get_contents();
  ob_end_clean(); // Don't send output to client
  }
else
  {$m3HTML = false;
   $m3JS = false;}  
 
$links = array();
//prg(0, $mda);
foreach ($mda as $mk => $mv)
  {
  if (is_array($mv))
    {
    foreach ($mv as $mvk => $mvv) 
      {
      if (preg_match("/^.+href=.(http.+)[\"][>](.+)[<].+$/", $mvv, $m))
	{$mvv = $m[1];
	 $mvk = $m[2];}
      
    // Only include values that are links
      if (filter_var($mvv, FILTER_VALIDATE_URL))
	{$links[] = "<a href=\"$mvv\">$mvk</a>";}
      
      //// Could pull details from NG website - but does not work due to network issues.
      //if (preg_match("/^.+article$/", $mvk, $m))
      //	{prg(1, $mvv);}
      //else
	//{prg(0, $mvk);}
	
      }
    }
  else
    {
    // in case links have been wrapped in html
    if (preg_match("/^.+href=.(http.+)[\"][>].+$/", $mv, $m))
      {$mv = $m[1];}
    // Only include values that are links
    if (filter_var($mv, FILTER_VALIDATE_URL))
      {$links[] = "<a href=\"$mv\">$mk</a>";}
    }
  }
$links = implode(", ", $links);
if ($links)
  {$links = "<hr/><h5>Additional Links</h5>".$links;}

ob_start();
echo <<<END
<div class="alert alert-light" role="alert" style="margin-bottom:0px;padding: 0rem 1rem;">
  $bc
</div>
<div class="alert alert-primary" role="alert">
  <h4 class="alert-heading">$title</h4>
  <p>$comment</p>
  $links
</div>
$list
END;
$details = ob_get_contents();
ob_end_clean(); // Don't send output to client


ob_start();
echo <<<END
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Presenting a working example of a Simple IIIF Collection Browser." />
		<meta name="keywords" content="The National Gallery, London, National Gallery London, Scientific, Research, Heritage, Culture, JSON, PHP, Javascript, Dissemination, VRE, IIIF, Collection, Mirador, OpenSeadragon, AHRC, PDF" />
    <meta name="author" content="Joseph Padfield| joseph.padfield@ng-london.org.uk |National Gallery | London UK | website@ng-london.org.uk |www.nationalgallery.org.uk" />
    <meta name="image" content="" />
    <link rel="icon" href="https://research.ng-london.org.uk/favicon.ico">
    <title>NG Simple Site - viewer-ng</title>
    
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" integrity="sha256-mUZM63G8m73Mcidfrv5E+Y61y7a12O5mW4ezU3bxqW4=" crossorigin="anonymous">
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" integrity="sha256-pOI3ctfK9rsNBkOmvY02gQtB7Vb/YFyg3GBfxeLCdxY=" crossorigin="anonymous">
	
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" integrity="sha256-YvdLHPgkqJ8DVUxjjnGVlMMJtNimJ6dYkowFFvp4kKs=" crossorigin="anonymous">
	
  <link href="https://cdn.jsdelivr.net/npm/jquery.json-viewer@1.4.0/json-viewer/jquery.json-viewer.css" integrity="sha256-rXfxviikI1RGZM3px6piq9ZL0YZuO5ETcO8+toY+DDY=" crossorigin="anonymous" rel="stylesheet" type="text/css">
	
  <link href="https://cdn.jsdelivr.net/npm/highlight.js@11.2.0/styles/github.css" integrity="sha256-Oppd74ucMR5a5Dq96FxjEzGF7tTw2fZ/6ksAqDCM8GY=" crossorigin="anonymous" rel="stylesheet" type="text/css">	
  
  $extraCSS	
	
  <link href="./css/main.css" rel="stylesheet" type="text/css">
  
    <style>
    
		.modal {z-index: 1112;}
		.fixed-top {z-index:1111;}
    .updated2012 {color: green;}
	
    </style>
    
  </head>

<body onload="onLoad();" style="">
	<div id="wrap" class="container-fluid h-100">		
		<div class="row " style="padding-top:0px; padding-bottom:45px; min-height:100%;">				
			<div class="col-12 col-md-12">  
				<div class="h-100 d-flex flex-column"> 
          <div class="container-fluid ">
            <div class="row h-100">
              <div class="col-12">							
                <div class="h-100 d-flex flex-column">
                  
                  
<!-- ############################################################### -->


    <div class="container-fluid" style="padding:0px;">
			<div class="row">
				<div class="col-3 d-flex">
          <img id="page-logo" class="logo" title="Logo" src="https://research.ng-london.org.uk/ng/graphics/ng_logo_tr_125.png" style="" alt="The National Gallery">
        </div>
        <div class="col-6 d-flex justify-content-center text-primary">
        <h3 class="m-0 display-6">IIIF Collection Explorer</h3>
        </div>
        <div class="col-3 d-flex flex-row-reverse">
					<table"> 
						<tbody><tr>
              <!--<td>
                <a role=button" id="fred" title="Clear Viewer" type="button" style="margin-right:0px;padding:0px;" class="btn btn-primary">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 26 26" width="32" height="32">
                    <g fill="#ee00ee">
                      <path d="M 8 3 L 0.94335938 10.056641 L 0 11 L 0.94335938 11.943359 L 8 19 L 20.333984 19 L 22 19 L 22 3 L 20.333984 3 L 8 3 z M 11.320312 7 L 14 9.6796875 L 16.679688 7 L 18 8.3203125 L 15.320312 11 L 18 13.679688 L 16.679688 15 L 14 12.320312 L 11.320312 15 L 10 13.679688 L 12.679688 11 L 10 8.3203125 L 11.320312 7 z "/>  
                    </g>
                  </svg>
                </a>
              </td>-->
              
              <!--<td>
                <a href="./" role=button" title="Clear Viewer" type="button" style="margin-right:0px;padding:0px;" class="btn btn-primary">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 26 26" width="32" height="32">
                    <g fill="#eeeeee">
                      <path d="M 8 3 L 0.94335938 10.056641 L 0 11 L 0.94335938 11.943359 L 8 19 L 20.333984 19 L 22 19 L 22 3 L 20.333984 3 L 8 3 z M 11.320312 7 L 14 9.6796875 L 16.679688 7 L 18 8.3203125 L 15.320312 11 L 18 13.679688 L 16.679688 15 L 14 12.320312 L 11.320312 15 L 10 13.679688 L 12.679688 11 L 10 8.3203125 L 11.320312 7 z "/>  
                    </g>
                  </svg>
                </a>
              </td>-->
              
              <td>
                <button title="Further Information" type="button" style="margin-right:0px;padding:0px;" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#infoModal">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 161 161" width="32" height="32">
                    <g fill="#eeeeee">
                      <path d="m80 15c-35.88 0-65 29.12-65 65s29.12 65 65 65 65-29.12 65-65-29.12-65-65-65zm0 10c30.36 0 55 24.64 55 55s-24.64 55-55 55-55-24.64-55-55 24.64-55 55-55z"></path>
                      <path d="m57.373 18.231a9.3834 9.1153 0 1 1 -18.767 0 9.3834 9.1153 0 1 1 18.767 0z" transform="matrix(1.1989 0 0 1.2342 21.214 28.75)"></path>
                      <path d="m90.665 110.96c-0.069 2.73 1.211 3.5 4.327 3.82l5.008 0.1v5.12h-39.073v-5.12l5.503-0.1c3.291-0.1 4.082-1.38 4.327-3.82v-30.813c0.035-4.879-6.296-4.113-10.757-3.968v-5.074l30.665-1.105"></path>
                      </g>
                  </svg>
                </button>
              </td>
             <!--- <td>
                <button title="Open the simple search form" type="button" style="margin-right:0px;padding:0px;" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#searchModal">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 55 55" width="32" height="32">
                    <g id="XMLID_13_" transform="translate(-25.461,-22.738)" fill="#eeeeee">
                      <path d="M 69.902,72.704 58.967,61.769 c -2.997,1.961 -6.579,3.111 -10.444,3.111 -10.539,0 -19.062,-8.542 -19.062,-19.081 0,-10.519 8.522,-19.061 19.062,-19.061 10.521,0 19.06,8.542 19.06,19.061 0,3.679 -1.036,7.107 -2.828,10.011 l 11.013,11.011 c 0.583,0.567 0.094,1.981 -1.076,3.148 l -1.64,1.644 c -1.17,1.167 -2.584,1.656 -3.15,1.091 z M 61.249,45.799 c 0,-7.033 -5.695,-12.727 -12.727,-12.727 -7.033,0 -12.745,5.694 -12.745,12.727 0,7.033 5.712,12.745 12.745,12.745 7.032,0 12.727,-5.711 12.727,-12.745 z" id="path9"></path>
                    </g>
                  </svg>
                </button>
              </td>--->
            </tr></tbody>
          </table>
        </div>
      </div>
    </div>



<!-- ############################################################### -->
            
                </div>
              </div>
            </div>
          </div>
        <div class="" style="position:relative;min-height:200px;display:block;background:white;" id="info">
            $details
        </div>
        <div class="container-fluid flex-grow-1">
          <div class="row h-100">
            
            $pdfHTML
            
            $tableHTML
            
            $m3HTML
            
            
          </div>
        </div>
        
        
                <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="infoModalLabel">Further Information</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">                         
                          
                        <h4>Feedback and Questions</h4>
			<p>Please report any issues or submit any questions directly to the <a href="https://github.com/jpadfield/iiif-collection-explorer/issues">IIIF Collection Explorer GitHub repository</a>.</p>
			
			
			<h4>Acknowledgement</h4>

			  <p>This development of the IIIF Collection Explorer project has been directly supported by the following project:</p>
			  <br>
			  <h5>Practical applications of IIIF Project</h5>
			  <figure class="figure">
			    <img style="height:64px;" src="https://research.ng-london.org.uk/ss-iiif/graphics/TANC%20-%20IIIF.png" class="figure-img img-fluid rounded" alt="IIIF - TANC">
			    <figcaption class="figure-caption">AHRC funded - IIIF-TNC | Practical applications of IIIF as a building block towards a digital National Collection -   <a href="https://tanc-ahrc.github.io/IIIF-TNC">IIIF - TNC</a></figcaption>
			  </figure>

                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
                
                
                <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="searchModalLabel">Search Available IIIF Collections</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">Still todo !<!---
                        <form class="justify-content-center" style="padding:0.5rem 0px 0.5rem 0px;">
                          
                          <div class="mb-3">                           
                            <input class="form-control me-2" type="search" placeholder="Accession Number or Keyword(s)" aria-label="Search" id="search" name="search" value="$_GET[search]" aria-describedby="searchHelp">
                            <div id="searchHelp" class="form-text">Free text search. Multiple Inv. Numbers can be added as a comma separated list.</div>
                          </div>
                          <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" value="1" id="refresh" name="refresh"  aria-describedby="refreshHelp">
                            <label class="form-check-label" for="refresh">Force Refresh of Image Details</label>
                            <div id="refreshHelp" class="form-text">This option only works for Inv. Number searches - It will refresh all of the cached image details.</div>
                          </div>
                          <button type="submit" class="btn btn-outline-success searchsubmit float-end" id="submit-modal">Search</button>
                          
                        </form> --->
                      </div>
                    </div>
                  </div>          
                </div>
        
        
        
      </div>
    </div>
  </div>

<footer class="fixed-bottom" style="border:0px;">
		<div class="container-fluid">
			<div class="row">
				<div class="col-5" style="text-align:left;">&copy; The National Gallery 2022</div>
				<div class="col-2" style="text-align:center;"></div>
				<div class="col-5" style="text-align:right;"><a href='http://rightsstatements.org/vocab/InC-EDU/1.0/'><img height='16' alt='In Copyright - Educational Use Permitted' title='In Copyright - Educational Use Permitted' src='https://rightsstatements.org/files/buttons/InC-EDU.dark-white-interior-blue-type.svg'/></a><a rel='license' href='https://creativecommons.org/licenses/by-nc/4.0/'><img alt='Creative Commons Licence' style='border-width:0' src='https://i.creativecommons.org/l/by-nc/4.0/88x31.png' /></a></div>
			</div>
		</div>        
  </footer>
</div>
	
	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
	
  <script src="https://cdn.jsdelivr.net/npm/tether@2.0.0/dist/js/tether.min.js" integrity="sha256-cExSEm1VrovuDNOSgLk0xLue2IXxIvbKV1gXuCqKPLE=" crossorigin="anonymous"></script>
	
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha256-9SEPo+fwJFpMUet/KACSwO+Z/dKMReF9q4zFhU/fT9M=" crossorigin="anonymous"></script>
	
  <script src="https://cdn.jsdelivr.net/npm/jquery.json-viewer@1.4.0/json-viewer/jquery.json-viewer.js" integrity="sha256-klSHtWPkZv4zG4darvDEpAQ9hJFDqNbQrM+xDChm8Fo=" crossorigin="anonymous"></script>
	
  <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.2.0/build/highlight.min.js"></script>	
  
  $extraJS
	<script>
   
     
  
  var myMirador = 1;
  var mymiradorImageToolsPlugin = 1;
  
  var myModal = document.getElementById('searchModal')
  var myInput = document.getElementById('search')

  myModal.addEventListener('shown.bs.modal', function () {
    myInput.focus()
    })
    
  function addRes() {
  //console.log(myMirador);
  //console.log(myMViewer);
  //var action = myMirador.actions.addResource('https://scientific.ng-london.org.uk/iiif/manifest/NG1/collection')
  // Now we can dispatch it.
  myMViewer.store.dispatch(action);   
  }
		
    function onLoad() {
  
    $limitedMessage
        
    $m3JS
    
    $tableJS
    }
      
    
      
</script>
<script src="dist/main.js" defer></script>
</body>

</html>
END;
$html = ob_get_contents();
ob_end_clean(); // Don't send output to client

echo $html;

		
function getsslJSONfile ($uri, $decode=true)
	{
	$arrContextOptions=array(
    "ssl"=>array(
        "verify_peer"=>false,
        "verify_peer_name"=>false,),);  

	$response = @file_get_contents($uri, false, stream_context_create($arrContextOptions));
	
	if ($decode)
		{return (json_decode($response, true));}
	else
		{return ($response);}
	}
	
function getExternalDetails($searchterm, $uri="https://scientific.ng-london.org.uk/tools/md/api-2.php?search=", $extra="")
	{$uri = $uri.$searchterm.$extra;
	 $arr = getsslJSONfile($uri);
	 return($arr);}


function prg($exit=false, $alt=false, $noecho=false)
	{
	if ($alt === false) {$out = $GLOBALS;}
	else {$out = $alt;}
	
	ob_start();
  
  if (php_sapi_name() === 'cli')
    {echo "\n";}
  else
    {echo "<pre class=\"wrap\">";}
    
	if (is_object($out))
		{var_dump($out);}
	else
		{print_r ($out);}

  if (php_sapi_name() === 'cli') 
    {echo "\n";}
  else
    {echo "</pre>";}
    
	$out = ob_get_contents();
	ob_end_clean(); // Don't send output to client
  
	if (!$noecho) {echo $out;}
		
	if ($exit) {exit;}
	else {return ($out);}
	}   

function getMDLVpair ($arr, $lang="en")
  {
  $label = getLangValue ($arr["label"], $lang);
  $value = getLangValue ($arr["value"], $lang);
  
  $out = array(
    "label" => $label,
    "value" => $value);
  
  return ($out);    
  }
  
function getLangValue ($arr, $lang="en", $force=false)
  {
  if (isset($arr[$lang]))
    {$lv = $arr[$lang];}
  else if (current($arr))
    {$lv = current($arr);}
  else
    {$lv = array("Unknown");}
    
  if (count($lv) == 1)
    {$value = $lv[0];}
  else if ($force)  
    {$value = $lv[0];}
  else
    {$value = $lv;}
    
  return($value);
  }
  
function buildTable ($d)
  {
  $table = array();
//prg(1, $d["metadata"]);  
  if (isset($d["metadata"]))
    {foreach ($d["metadata"] as $kk => $md)
      {$out = getMDLVpair($md);
       if ($out["label"] == "Table Title")
        {$table["title"] = $out["value"];
         unset($d["metadata"][$kk]);}
       else if ($out["label"] == "Table Header")
        {$table["header"] = $out["value"];
         unset($d["metadata"][$kk]);}
       else if ($out["label"] == "Table Data")
        {$table["data"] = $out["value"];
         unset($d["metadata"][$kk]);}
       else if ($out["label"] == "Table Footnote")
        {$table["footnote"] = $out["value"];
         unset($d["metadata"][$kk]);}}} 
  
  $th = "";
  $tids = array();

  foreach ($table["header"] as $hk => $hv)
    {
    $df = strtolower(strip_tags($hv));
    $tids[$hk] = $df;
    $th .= "
<th data-field=\"$df\" data-sortable=\"true\">$hv</th>";    
    }
 
  $data = array();
  foreach ($table["data"] as $tk => $tv)
    {
    $ta = array();
  
    foreach ($tv as $fk => $fv)
      {$ta[$tids[$fk]] = $fv;}
    
    $data[] = $ta;
    }
  
  $json = json_encode($data);

  ob_start();
  echo <<<END

<div class="col flex-grow-1" style="overflow-x:auto">							
      <div class="h-100 d-flex flex-column">
        <div class="row justify-content-center flex-grow-1">
               
          <div class="h-100 table-responsive" style="position:relative;min-height:400px;" id="tableviewer">
<b>$table[title]</b>
<table
  id="table"
  data-search="true"
  data-height="650"
  
  data-show-pagination-switch="true"
  data-pagination="true"
  data-title="fred"
  data-show-columns="true"
  data-show-columns-toggle-all="true"
  
  data-page-size="5"
  data-page-list="[5, 10, 25, 50, all]"
  data-show-fullscreen="true"
  data-sort-name="name"
  data-sort-order="desc">
  <thead>
    <tr>$th
    </tr>
  </thead>
</table>
<div style="font-size: 0.75em;">$table[footnote]</div>
</div>
              
        </div>                
      </div>
    </div> 

END;
  $d["table"]["html"] = ob_get_contents();
  ob_end_clean(); // Don't send output to client
  
  ob_start();
  echo <<<END
  var \$table = $('#table')
  
  $(function() {
    var data = $json
    
    \$table.bootstrapTable({data: data})
  })
  
END;
  $d["table"]["js"] = ob_get_contents();
  ob_end_clean(); // Don't send output to client
  
  $d["table"]["jsScripts"] = '
<script src="https://unpkg.com/bootstrap-table@1.19.1/dist/bootstrap-table.min.js"></script> 
';  
  $d["table"]["cssScripts"] = '
<link href="https://unpkg.com/bootstrap-table@1.19.1/dist/bootstrap-table.min.css" rel="stylesheet"> 
';

  return ($d);
  }

function array2list ($a)
  {  
  $last = array_pop ($a);
  $list = implode (", ", $a);
  
  if ($list)
    {$list = "$list and $last";}
  else
    {$list = $last;}
    
  return ($list);
  }
  
function formatIIIFCard ($url, $link)
  {
  $dets = getsslJSONfile ($url, true);

  $card = array(
    "thumb" => "<h2 class=\"display-6\"><i class=\"bi bi-card-image\" style=\"font-size: 3rem; color: gray;\";></i></h2>",
    "logo" => "<i class=\"bi bi-card-image\" style=\"font-size: 3rem; color: gray;\";></i>",    
    "label" => false,   
    "provider" => false,
    "items" => array(
      "collection" => 0,
      "manifest" => 0,
      "image" => 0,
      "other item" => 0,
      "total" => 0)
    );
    
  if (isset($dets["thumbnail"][0]["id"]))
    {$card["thumb"] = "<img src=\"".$dets["thumbnail"][0]["id"].
      "\" class=\"img-thumbnail img-fluid\" alt=\"...\" style=\"max-width:auto;max-height:75px;\">";}
  else if (isset($dets["items"][0]["thumbnail"][0]["id"]))
    {$card["thumb"] = "<img src=\"".$dets["items"][0]["thumbnail"][0]["id"].
      "\" class=\"img-thumbnail img-fluid\" alt=\"...\" style=\"max-width:auto;max-height:75px;\">";}
  
  if (isset($dets["logo"]))
    {$card["logo"] = "<img src=\"".$dets["logo"].
      "\" class=\"img-thumbnail m-2\" alt=\"...\" style=\"max-width:64px;max-height:48px;\">";;}
    
  if (isset($dets["label"]))
    {$card["label"] = getLangValue ($dets["label"], "en");}
    
  if (isset($dets["provider"]["label"]))
    {$card["provider"] = getLangValue ($dets["provider"]["label"], "en");}
    
  foreach ($dets["items"] as $k => $a)
    {
    if ($a["type"] == "Collection")
      {$card["items"]["collection"]++;}
    else if ($a["type"] == "Manifest")
      {$card["items"]["manifest"]++;}
    else if ($a["type"] == "Canvas")
      {$card["items"]["image"]++;}
    else 
      {$card["items"]["other item"]++;}
      
    $card["items"]["total"]++;
    }
  
  $items = array();
  
  foreach ($card["items"] as $k => $a)
    {
    if ($k != "total" and $a) {
      if ($a > 1) {$s = "s";}
      else {$s = "";}
      $items[] = $a. " $k$s";}
    }
    
  $items = array2list ($items);
  //$card["items"]["total"] . " items";

/*
 
 Array
(
    [thumb] => https://research.ng-london.org.uk/ng/graphics/ngtb_logo_tr_140.png
    [logo] => https://research.ng-london.org.uk/ng/graphics/ng_logo_tr_125.png
    [label] => Technical Bulletin
    [provider] => 
    [items] => Array
        (
            [collections] => 6
            [manifests] => 0
            [canvases] => 0
            [other] => 0
        )

)

 */

if ($card["provider"])
  {$items = "<p class=\"p-3 pb-0 m-0\">$card[provider]</p>
      <p class=\"p-3 pt-0 m-0\">$items</p>";}
else
 {$items = "<p class=\"p-3 m-0\">$items</p>";}
 
ob_start();
echo <<<END
<style>
.rcard:hover{
    background: #F4F6F7;
}
</style>
<div class="card rcard mb-1 p-1" style="width:100%;">
  <div class="row g-0">
    <div class="col-lg-1 col-md-2 col-sm-3">
      <a href="$link" class="stretched-link">
	<div class="d-flex align-items-center justify-content-center" style="width: 100%; height: 100%">
	  $card[thumb]
	</div>
      </a>
    </div>
    <div class="col-lg-8 col-md-7 col-sm-4">
      <p class="p-3"><strong>$card[label]</strong></p>
    </div>
    <div class="col-lg-2 col-md-2 col-sm-3">
      $items
    </div>
    <div class="col-lg-1 col-md-1 col-sm-2">
    <div
      class="d-flex align-items-start justify-content-center float-end"
      style="">
      $card[logo]
      </div>
    </div>
  </div>
</div>

    
END;
  $html = ob_get_contents();
  ob_end_clean(); // Don't send output to client
    
  return ($html);
  
    
  }
  
?>
