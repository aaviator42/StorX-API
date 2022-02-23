<?php
/*
StorX API Receiver
by @aaviator42

StorX API Receiver version: 4.1
StorX.php version: 4.1

StorX DB file format version: 3.1

2022-02-22

*/

//---CONFIGURATION---
const DATA_DIR = "./"; //Include trailing slash!

const USE_AUTH = TRUE; //TRUE = Require password; FALSE = Open
const PASSWORD_HASH = '$2y$10$0kYYaXEpk2WDsz6mHVwHvuzsvUGcnSsV37MZE90K3v4hGd/jr4iJO'; //Use password_hash()

const KEY_OUTPUT_SERIALIZATION = "JSON"; 	//For readKey() and readAllKeys()
											//"PHP" = serialize(); "JSON" = json_encode()

const JSON_SERIALIZATION_FLAGS = JSON_PRETTY_PRINT; //See https://www.php.net/manual/en/json.constants.php


//---END CONFIGURATION---
require('StorX.php');

//Store the endpoint in $endpoint, segmented endpoint in $endpointArray
$endpoint = rtrim(substr(@$_SERVER['PATH_INFO'], 1), '/\\');
$endpointArray = explode("/", $endpoint);

$method = $_SERVER['REQUEST_METHOD'];

if (!empty(file_get_contents('php://input'))){
	$input = json_decode(file_get_contents('php://input'), true);
} else {
	$input = array();
}

$output = array(
	"error" => 0,			//bool: 0 = all ok, 1 = error occured
	"errorMessage" => NULL,	//if error occurs, store message here
	"returnCode" => NULL	//return codes through this
	);

//If API is being accessed by StorX Remote, 
//force use PHP's serialize() for key read to maximize compatibility
if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], "StorX Remote") !== false){
	$output["keyOutputSerialization"] = "PHP";
} else {
	$output["keyOutputSerialization"] = KEY_OUTPUT_SERIALIZATION;
	if(!($output["keyOutputSerialization"] === "PHP" || $output["keyOutputSerialization"] === "JSON")){
		$output["keyOutputSerialization"] = "JSON";
	}
}

if(!(($method === 'GET') && ($endpointArray[0] === 'ping'))){
	if(USE_AUTH){
		if(!isset($input["password"]) || !password_verify($input["password"], PASSWORD_HASH)){
			errorAuthFailed();
		}
	}
}


switch($method){
	case 'PUT':
		switch($endpointArray[0]){
			case 'createFile':
				createFile();
			break;
			case 'writeKey':
				writeKey();
			break;
			case 'modifyKey':
				modifyKey();
			break;
			case 'modifyMultipleKeys':
				modifyMultipleKeys();
			break;
			case 'multiCommands':
				multiCommands();
			default:
				errorInvalidRequest();
			break;			
		}
	break;
	
	case 'GET':
		switch($endpointArray[0]){
			case 'ping':
				pong();
			break;
			case 'checkFile':
				checkFile();
			break;
			case 'checkKey':
				checkKey();
			break;
			case 'readKey':
				readKey();
			break;
			case 'readAllKeys':
				readAllKeys();
			break;
			default:
				errorInvalidRequest();
			break;
		}
		break;
	
	case 'DELETE':
		switch($endpointArray[0]){
			case 'deleteFile':
				deleteFile();
			break;
			case 'deleteKey':
				deleteKey();
			break;
			default:
				errorInvalidRequest();
			break;
		}
		break;
	default:
		errorInvalidRequest();
	break;
}


function errorAuthFailed(){
	global $output;
	
	$output["error"] = 1;
	$output["errorMessage"] = "StorX API: Authentication failed.";
	$output["returnCode"] = -777;
	
	printOutput(401);
	exit(0);
}

function errorInvalidRequest(){
	global $output;
	
	$output["error"] = 1;
	$output["errorMessage"] = "StorX API: Invalid request.";
	$output["returnCode"] = -666;
	
	printOutput(400);
	exit(0);
}

function printOutput($code = 200){
	global $output, $endpointArray;
	if(!($endpointArray[0] === 'ping' || $endpointArray[0] === 'readKey')){
		unset($output["keyOutputSerialization"]);
	}
	if($endpointArray[0] === 'ping'){
		unset($output["error"]);
		unset($output["errorMessage"]);
		unset($output["returnCode"]);
	}
	
	header('Content-Type: application/json');
	http_response_code($code);
	echo json_encode($output, JSON_SERIALIZATION_FLAGS);
}


//-------------

//ping function

function pong(){
	global $input, $output;
	
	$output["version"] = "4.0";
	
	if($input["version"] === "4.0"){
		$output["pong"] = "OK";
	} else {
		$output["pong"] = "ERR";
	}
	printOutput(200);
	exit(0);
	
}


//basic functions --> create, check and delete files

function createFile(){
	global $input, $output;
	
	$sx = new \StorX\Sx;
	$filename = DATA_DIR . $input["filename"];
	$output["returnCode"] = $sx->createFile($filename);
	
	if($output["returnCode"] === 1){
		printOutput(201);
	} else {
		$output["error"] = 1;
		$output["errorMessage"] = 'Unable to create DB file';
		printOutput(409);
	}
	exit(0);
}

function checkFile(){
	global $input, $output;
	
	$sx = new \StorX\Sx;
	$filename = DATA_DIR . $input["filename"];
	$output["returnCode"] = $sx->checkFile($filename);
	printOutput(200);
	exit(0);
	
}

function deleteFile(){
	global $input, $output;
	
	$sx = new \StorX\Sx;
	$filename = DATA_DIR . $input["filename"];
	$output["returnCode"] = $sx->deleteFile($filename);
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		$output["error"] = 1;
		$output["errorMessage"] = 'Unable to delete DB file';
		printOutput(409);
	}
	exit(0);
}



//Sx object functions

function readKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 0) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
		
	} else {
		$keyValue;
		$output["returnCode"] = $sx->readKey($keyName, $keyValue);
		
		if($output["returnCode"] === 1){
			$output["keyName"] = $keyName;
			if($output["keyOutputSerialization"] !== "PHP"){
				$output["keyValue"] = $keyValue;
			} else {
				$output["keyValue"] = serialize($keyValue);
			}
		} else {
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to read key.";
		}
	}	
	
	$sx->closeFile();
	
	printOutput(200);
	exit(0);
}


function readAllKeys(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 0) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
		
	} else {
		$keyArray;
		$output["returnCode"] = $sx->readAllKeys($keyArray);
		
		if($output["returnCode"] === 1){
			if($output["keyOutputSerialization"] !== "PHP"){
				$output["keyArray"] = $keyValue;
			} else {
				$output["keyArray"] = serialize($keyArray);
			}
		} else {
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to read keys.";
		}
	}	
	
	$sx->closeFile();
	
	printOutput(200);
	exit(0);
}

function writeKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	$keyValue = $input["keyValue"];
	
	if($input["keyInputSerialization"] === "PHP"){
		$keyValue = unserialize($keyValue);
	}
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
	} else {
		$keyValue;
		$output["returnCode"] = $sx->writeKey($keyName, $keyValue);
		
		if($output["returnCode"] !== 1){
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to write key.";
		}
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to write changes to DB file.";
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function modifyKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	$keyValue = $input["keyValue"];
	
	if($input["keyInputSerialization"] === "PHP"){
		$keyValue = unserialize($keyValue);
	}
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
	} else {
		$keyValue;
		$output["returnCode"] = $sx->modifyKey($keyName, $keyValue);
		
		if($output["returnCode"] !== 1){
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to modify key.";
		}
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to write changes to DB file.";
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function modifyMultipleKeys(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyArray = $input["keyArray"];
	
	if($input["keyInputSerialization"] === "PHP"){
		$keyArray = unserialize($keyArray);
	}
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
	} else {
		$keyValue;
		$output["returnCode"] = $sx->modifyMultipleKeys($keyArray);
		if($output["returnCode"] !== 1){
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to modify keys.";
		}
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to write changes to DB file.";
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function deleteKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
	} else {
		$keyValue;
		$output["returnCode"] = $sx->deleteKey($keyName);
		
		if($output["returnCode"] !== 1){
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to delete key.";
		}
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
			$output["errorMessage"] = "Unable to write changes to DB file.";
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function checkKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
		$output["errorMessage"] = "Unable to open DB file.";
	} else {
		$keyValue;
		$output["returnCode"] = $sx->checkKey($keyName);
		$sx->closeFile();
	}
	
	printOutput(200);
	exit(0);
}

