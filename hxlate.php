<?php 
header("Access-Control-Allow-Origin: *"); 

include_once('functions.php');

getHead("index.php"); 

//  5MB maximum file size 
$MAXIMUM_FILESIZE = 5 * 1024 * 1024; 
//  Valid file extensions: 
$rEFileTypes = "/^\.(csv|txt|xls|xlsx|ods|fods){1}$/i"; 
$dir_base = getcwd().'/uploads/';
$uploadfile = ""; // we'll set this later

$isFile = is_uploaded_file($_FILES['userfile']['tmp_name']); 
$isMove = false;

if ($isFile) {//  sanitize file name 
    //     - remove extra spaces/convert to _, 
    //     - remove non 0-9a-Z._- characters, 
    //     - remove leading/trailing spaces 
    //  check if under 5MB, 
    //  check file extension for legal file types 
    $safe_filename = preg_replace( 
                     array("/\s+/", "/[^-\.\w]+/"), 
                     array("_", ""), 
                     trim($_FILES['userfile']['name'])); 
                     
    $uploadfile = $dir_base.$safe_filename; 
    
    if ($_FILES['userfile']['size'] <= $MAXIMUM_FILESIZE){ 
        if(preg_match($rEFileTypes, strrchr($safe_filename, '.'))) {
        	$isMove = move_uploaded_file ( $_FILES['userfile']['tmp_name'], $uploadfile);
        } else {
        	showError('<strong>'.$_FILES['userfile']['name'].'</strong> does not seem to be a spreadsheet (<code>.xls</code>, <code>.xlsx</code>, <code>.ods</code>, <code>.csv</code>, and the like). Please <a href="index.php" class="btn">go back</a> and try a different file.</p><p>If you are sure it is a spreadsheet, there is something wrong with the HXLator; in that case, please <a class="btn" href="contact.php">get in touch with us</a>, so we can fix it.');
        }	
    } else {
    	showError("<strong>".$_FILES['userfile']['name']."</strong> is too large. The limit for uploaded files is <strong>5MB</strong>.");
    }
    
} else {

	showError('You\'ll need to <a class="btn" href="index.php">upload a spreadsheet</a> to HXLate.');

}



// get going if the file upload has worked:

if($isMove === true) {

	$_SESSION['file'] = $_FILES["userfile"]["name"];

	/** Include path **/
	set_include_path(get_include_path() . PATH_SEPARATOR . './Classes/');
	
	/** PHPExcel_IOFactory */
	include 'PHPExcel/IOFactory.php';
	
	
	// using IOFactory to identify the format
	$workbook = load($uploadfile);

	loadHXLPreviewModal();
	loadMappingModal();

	echo '
			<div class="row">
			<div class="span12">
			<p class="lead"><strong>Build translator for  <em>'.$_SESSION['file'].'</em></strong> <a class="btn btn-info pull-right" id="btn-instr" href="#" rel="tooltip" title="Click to show the instructions" data-placement="right">Instructions</a> <a class="btn btn-info pull-right" id="showPreview" href="#" rel="tooltip" title="Check your output here" data-placement="left">Preview HXL</a></p>
			</div>
			</div>
			</div>
			<div class="shortguide container">';
		
	echo '
		</div> <!-- shortguide -->
		';
	
	// Let's show the spreadsheet"
	// iterate once for the tabs (i.e., one tab per sheet in the workbook)
	echo '<div class="container tabbable" style="margin-bottom: 18px;">
	          <ul class="nav nav-tabs">';
	
	$tabno = 1; 
	foreach ($workbook->getWorksheetIterator() as $worksheet) {    
		if($tabno === 1){  // make the first tab active
	   		echo '   <li class="active"><a href="#tab1" data-toggle="tab">'.$worksheet->getTitle().'</a></li>';
	    }else{
		   	echo '   <li><a href="#tab'.$tabno.'" data-toggle="tab">'.$worksheet->getTitle().'</a></li>';		   
	    }
	    $tabno++;
	}            
			            
	    echo'      </ul>
	          <div class="tab-content" style="padding-bottom: 9px; border-bottom: 1px solid #ddd; overflow: hidden">';
	            
	
	$tabno = 1;
	// iterate through all sheets in this file
	foreach ($workbook->getWorksheetIterator() as $worksheet) {
		//$sheetData = $workbook->getActiveSheet()->toArray(null,true,true,true); 
		
		$sheetData = $worksheet->toArray(null,true,true,true); 
	
		if ($tabno === 1){
			echo '<div class="tab-pane active" id="tab1">';
		}else{
			echo '<div class="tab-pane" id="tab'.$tabno.'">';
		}
		echo "
			<div class='fixed-table'>
			<div class='table-content'>
			<table class='table table-bordered table-condensed table-fixed-header'>";
	
		renderTable($sheetData, $tabno);				
	
		echo"
			  </table>
			  </div>
			  </div>
			</div>";
			
		$tabno++;
	}
	
	echo   ' </div>
	</div>';
	
	// we're done - delete the uploaded file
	unlink($uploadfile);
			
} 



// some functions from IOFactory.php, slightly adopted for our use case:
function load($pFilename) {
	$reader = createReaderForFile($pFilename);
	return $reader->load($pFilename);
}

function createReaderForFile($pFilename) {

		// First, lucky guess by inspecting file extension
		$pathinfo = pathinfo($pFilename);

		if (isset($pathinfo['extension'])) {
			switch (strtolower($pathinfo['extension'])) {
				case 'xlsx':
					$extensionType = 'Excel2007';
					break;
				case 'xls':
				case 'xlsm':
					$extensionType = 'Excel5';
					break;
				case 'ods':
					$extensionType = 'OOCalc';
					break;
				case 'slk':
					$extensionType = 'SYLK';
					break;
				case 'xml':
					$extensionType = 'Excel2003XML';
					break;
				case 'gnumeric':
					$extensionType = 'Gnumeric';
					break;
				case 'htm':
				case 'html':
					$extensionType = 'HTML';
					break;
				case 'csv':
					// Do nothing
					// We must not try to use CSV reader since it loads
					// all files including Excel files etc.
					break;
				default:
					showError('This file does not seem to be a spreadsheet.</p><p>If you are sure it is, there is something wrong with the HXLator; in that case, please <a class="btn" href="contact.php">get in touch with us</a>, so we can fix it.'); 
					break;
			}

			$reader = PHPExcel_IOFactory::createReader($extensionType);
			// Let's see if we are lucky
			if (isset($reader) && $reader->canRead($pFilename)) {
				//$reader->setReadDataOnly(true); // we only need the data
				return $reader;
			}

		}

		// If we reach here then "lucky guess" didn't give any result
		// Try walking through all the options in PHPExcel_IOFactory::$_autoResolveClasses
		foreach (PHPExcel_IOFactory::$_autoResolveClasses as $autoResolveClass) {
			//	Ignore our original guess, we know that won't work
		    if ($reader !== $extensionType) {
				$reader = PHPExcel_IOFactory::createReader($autoResolveClass);
				if ($reader->canRead($pFilename)) {
					//$reader->setReadDataOnly(true); // we only need the data
					return $reader;
				}
			}
		}

    	showError('This file does not seem to be a spreadsheet.</p><p>If you are sure it is, there is something wrong with the HXLator; in that case, please <a class="btn" href="contact.php">get in touch with us</a>, so we can fix it.'); 
    	 
	}	//	function createReaderForFile()

$orgPost = null;
if(isset($_POST["user_organisation"])){
	$orgPost = array("org" => $_POST["user_organisation"]);
} 

$containerURI = makeURI("hxl:DataContainer", $orgPost);

$im = '$initMapping = {
	"datacontainers": [
	  	{ 
  		    "<'.$containerURI.'>": {
		      	"triples": [
			        {
			          "predicate": "a",
			          "object": "<http://hxl.humanitarianresponse.info/ns/#DataContainer>",          
			        },
			        {
			          "predicate": "hxl:aboutEmergency",
			          "object": "<'.$_POST["emergency"].'>",          
			        },
			        {
			          "predicate": "hxl:reportCateogry",
			          "object": "<'.$_POST["report_category"].'>",          
			        },
			        {
			          "predicate": "hxl:reportedBy",
			          "object": "<'.$_SESSION["user_uri"].'>",          
			        },
			        {
			          "predicate": "hxl:reportedBy",
			          "object": "<'.$_SESSION["user_organisation_uri"].'>",          
			        },
			        {
			          "predicate": "hxl:validOn",
			          "object": "'.$_POST["date"].'",
			          "datatype": "xsd:date"          
			        }
			    ]			    
			}
		}
	],	  
	"lookup": {}
	};
	';

if($_POST["translator"] == "new"){

	// start with an empty translator:

	$inlineScript = $im.'

	var $recycle = false;
	$hxlHistory.pushState($initMapping);
	';
}else{

	// read the existing translator and start from there:
	$translator = file_get_contents(getcwd().'/mappings/'.$_SESSION['user_shortname'].'/'.$_POST["translator"]
		);

	// Convert to PHP object:
	$trans = json_decode($translator, true);

	// replace data container metadata:
	$i = 0;
	foreach ($trans['datacontainers'] as $container){

		foreach ($container as $uri => $template) { 
			
			error_log($uri);

			// remove metadata from this container -
			// the hxlator.js will create 'fresh' metadata
			if(strpos($uri, '/datacontainers/') !== false)
				unset($trans['datacontainers'][$i][$uri]);

			// remove all mappings outside of the samplerow:
			if(strpos($uri, '@uri') === 0){
				$parts = explode(" ", $uri);
				$thiscell = explode("-", $parts[1]);

				$thisrow = $thiscell[0].'-'.$thiscell[2];
				
				// check if this cell is outside of the sample row:
				if($thisrow != $trans['samplerow'])
					unset($trans['datacontainers'][$i][$uri]);
									
			}

		}

		$i++;
	}

	$inlineScript = $im.'
	$loadedMapping = eval('.json_encode($trans).');
	var $recycle = true;
	$debug = true;
	// merge the existing mapping with the new metadata
	$hxlHistory.pushState($.extend(true, $initMapping, $loadedMapping));

	';	
}

$inlineScript = $inlineScript.'
	// fix table header:
	 $(".table-fixed-header").fixedHeader();

	 var $validOn = "'.$_POST["date"].'";
';


if(!isset($_SESSION['loggedin'])) {   // remove the loading spinner if the user is not logged in - we're showing the login form then:
	$inlineScript .= '
	$("#loading").hide(); ';
}


// load the footer, along with the extra JS required for this page
getFoot(array("bootstrap-tab.js", "bootstrap-tooltip.js", "bootstrap-popover.js", "bootstrap-dropdown.js", "bootstrap-modal.js", "bootstrap-transition.js", "bootstrap-alert.js", "jquery-ui-1.8.21.custom.min.js", "jquery.dataTables.min.js", "table-fixed-header.js", "bootstrap-datepicker.js", "hxlator.js?".time() ), $inlineScript);

// renders the given $sheetData as an HTML table
// $sheetIndex is the index of the given sheet in the containing workbook
function renderTable($sheetData, $sheetIndex){
	$bodyOpen = false;
	foreach ($sheetData as $rownumber => $rowcontents) {

		// show the header (A B ...) above the first row
		if($rownumber == 1){ // header row
			echo '
				<thead class="header">
					<tr class="hxlatorrow">
						<th class="hxlatorcell"> </th>';		
			foreach ($rowcontents as $cellid => $cellvalue) {
				echo '
						<th class="hxlatorcell">'.$cellid.'</th>';	
			}		
			
			echo '
					</tr>
				</head>';					
		}

		// show the row contents
		if(!$bodyOpen){
			echo '
			<tbody>';
			$bodyOpen = true;
		}

		echo '
				<tr class="hxlatorrow" data-rowid="'.$sheetIndex.'-'.$rownumber.'">
					<th class="hxlatorcell">'.$rownumber.'</th>';		
		foreach ($rowcontents as $cellid => $cellvalue) {
			echo '
					<td class="hxlatorcell" data-cellid="'.$sheetIndex.'-'.$cellid.'-'.$rownumber.'"'.getDataType($cellvalue).'>'.$cellvalue.'</td>';	
		}
		
		echo '
				</tr>';	
	
	}
	
	echo '
			</tbody>';	
		
}

// determines a value's xsd datatype and return the correspoding html snippet to annotate the cells in the html table
// this will allow us to check whether the datatype conforms to the HXL property's range at the point where a users maps a cell to a property
function getDataType($val = null){
	if ($val){
		$date = date_parse($val);
		if ( is_numeric($val) && !strpos($val, ".") ) {
			return ' data-type="xsd:int"'; 
		} else if ( is_numeric($val) && strpos($val, ".") >= 0 ){ // if it has decimals, it's a double
			return ' data-type="xsd:double"'; 		
		} else if ( $date["year"] && $date["month"]){			
			if ( $date["hour"] == 0 && $date["minute"] == 0){ // this is just a date, no time info
				return ' data-type="xsd:date" data-date="'.$date["year"].'-'.$date["month"].'-'.$date["day"].'"';
			} else { // date with time:
				return ' data-type="xsd:dateTime" data-date="'.$date["year"].'-'.$date["month"].'-'.$date["day"].'T'.$date["hour"].':'.$date["minute"].':'.$date["second"].'"';
			}
		} elseif (is_string($val)) {
			return ' data-type="xsd:string"';
		}else{
			//error_log("Unknown datatype, value: ".$val);
		}
	}
	// if no value given or no matching datatype found:
	return '';
	
}


?>






