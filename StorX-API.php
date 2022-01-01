<?php
/*
StorX API Remote
by @aaviator42

API Remote version: 3.6
StorX.php version: 3.6

StorX DB file format version: 3.1

2021-12-31

*/

namespace StorX\Remote;
use Exception;

const THROW_EXCEPTIONS = 1; //0: return error codes, 1: throw exceptions
const CURL_PEM_FILE = NULL; //path to certificate file for SSL requests

class Rx{
	private $DBfile;
	private $password;
	private $URL;
	private $fileStatus = 0;

	private $lockMode = 0;
	
	function sendRequest($method = NULL, $URL = NULL, $params = NULL, $payload = NULL){
		if(empty($method) || empty($URL)){
			throw new Exception("StorX Remote: URL not specified");
		}
		
		if(!empty($params)){
			rtrim($params, '?');
			$URL .= "?";
			foreach($params as $key => $value){
				$URL = $URL . $key . "=" . $value . "&";
			}
		}
		
		$ch = curl_init();
		$options = array(
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_URL => $URL,
			CURLOPT_USERAGENT => "StorX Remote v3.6",
			CURLOPT_TIMEOUT => 120,
			CURLOPT_RETURNTRANSFER => true);
		
		if(!empty($payload)){
			$payload["version"] = "3.6";
			$payload  = json_encode($payload);
			$headers = array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($payload));
			$options[CURLOPT_POSTFIELDS] = $payload;
			$options[CURLOPT_HTTPHEADER] = $headers;
		}
		
		if(!empty(CURL_PEM_FILE)){
			$options[CURLOPT_CAINFO] = CURL_PEM_FILE;
		}
		
		curl_setopt_array($ch, $options);
		$content = curl_exec($ch);	
		if($content === false){
			return false;
		} else {
			return json_decode($content, 1);
		}
	}
	
	function initChecks($writeCheck = 0){
		if(!isset($this->URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] URL not specified.");
			} else {
				return 0;  
			}
		}
		
		if(!isset($this->DBfile)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] No DB file open.");
			} else {
				return 0;  
			}
		}
		
		
		if($writeCheck){
			if($this->lockMode === 0){
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote] DB file not opened for writing.");
				} else {
					return 0;  
				}
			}
		}
		
		return 1;
	}
	
	function serverErrorCheck($code){
		switch($code){
			case -2:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: Server Error] Unable to open DB file.");
				} else {
					return 0;
				}
				break;
			case -3:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: Server Error] Unable to commit changes to DB file.");
				} else {
					return 0;
				}
				break;
			case -666:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: Server Error] Invalid request, you broke something.");
				} else {
					return 0;
				}
				break;
			case -777:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: Server Error] Authentication failed.");
				} else {
					return 0;
				}
				break;
			
		}
		return 1;
	}
	
	function doofusError(){
		throw new Exception("Ya broke something, doofus.");
	}
	
	public function setURL($URL){
		$URL = rtrim($URL, '/\\');
		
		if(empty($URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: setURL()] URL does not point to StorX Receiver of matching version.");
			} else {
				return 0;
			}
		}
		
		$testURL = $URL . "/ping";
		$payload = array("version" => "3.6");
		$response = $this->sendRequest("GET", $testURL, NULL, $payload);
		
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: setURL()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		
		if(isset($response["pong"]) && $response["pong"] === "OK"){
			$this->URL = $URL;
			return 1;
		} else {
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: setURL()] URL does not point to StorX Receiver of matching version.");
			} else {
				return 0;
			}
		}
	}
	
	public function setPassword($password){
		if(!isset($this->URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] URL not specified.");
			} else {
				return 0;  
			}
		}
		
		if(!empty($password)){
			$this->password = strval($password);
		}		
	}
	
	public function openFile($filename, $mode = 0){
		if(!isset($this->URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] URL not specified.");
			} else {
				return 0;  
			}
		}
		
		if(empty($filename)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: openFile()] No filename specified.");
			} else {
				return 0;
			}
		}
		
		$this->DBfile = $filename;
		if($mode !== 0){
			$this->lockMode = 1;
		}
		return 1;		
	}
	
	public function closeFile(){
		unset($this->DBfile);
		$this->fileStatus = 0;
		$this->lockMode = 0;
		return 1;		
	}
	
	public function commitFile(){
		return 1;
	}
	
	public function readKey($keyName, &$store){
		if($this->initChecks() !== 1){
			return 0;
		}
		
		$URL = $this->URL . "/readKey";
		$payload = array(
			"filename" => $this->DBfile,
			"keyName" => $keyName
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
			
		$response = $this->sendRequest("GET", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: readKey()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: readKey() - Server Error] Key not found in DB file.");
				} else {
					return 0;
				}
				break;
		}
			
		$keyValue = unserialize($response["keyValue"]);
		$store = $keyValue;
		return 1;		
	}	
	
	public function returnKey($keyName){
		if($this->initChecks() !== 1){
			return 0;
		}
		
		$URL = $this->URL . "/readKey";
		$payload = array(
			"filename" => $this->DBfile,
			"keyName" => $keyName
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("GET", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: returnKey()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case "STORX_ERROR":
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: returnKey() - Server Error] Key not found in DB file.");
				} else {
					return "STORX_ERROR";
				}
				break;
		}
			
		$keyValue = unserialize(base64_decode($response["keyValue"]));
		return $keyValue;		
	}
	
	public function writeKey($keyName, $keyValue){
		if($this->initChecks(1) !== 1){
			return 0;
		}
		
		$URL = $this->URL . "/writeKey";
		$payload = array(
			"filename" => $this->DBfile,
			"keyName" => $keyName,
			"keyValue" => $keyValue
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("PUT", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: writeKey()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: writeKey() - Server Error] Key already exists in DB file or unable to write key to DB file.");
				} else {
					return 0;
				}
				break;
			case 1:
				return 1;	
				break;
		}
		
	}	

	public function modifyKey($keyName, $keyValue){
		if($this->initChecks(1) !== 1){
			return 0;
		}
		
		$URL = $this->URL . "/modifyKey";
		$payload = array(
			"filename" => $this->DBfile,
			"keyName" => $keyName,
			"keyValue" => $keyValue
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("PUT", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: modifyKey()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: modifyKey() - Server Error] Unable to modify key in DB file.");
				} else {
					return 0;
				}
				break;
			case 1:
				return 1;
				break;				
		}
		
	}
	
	public function deleteKey($keyName){
		if($this->initChecks() !== 1){
			return 0;
		}
		
		$URL = $this->URL . "/deleteKey";
		$payload = array(
			"filename" => $this->DBfile,
			"keyName" => $keyName
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("DELETE", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: deleteKey()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: deleteKey() - Server Error] Unable to delete key from DB file.");
				} else {
					return 0;
				}
				break;
			case 1:
				return 1;
				break;				
		}
		
	}
	
	public function deleteFile($filename){
		if(!isset($this->URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] URL not specified.");
			} else {
				return 0;  
			}
		}
		
		$URL = $this->URL . "/deleteFile";
		$payload = array(
			"filename" => $filename
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("DELETE", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: deleteFile()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: deleteFile() - Server Error] Unable to delete DB file.");
				} else {
					return 0;
				}
				break;
			case 1:
				return 1;
				break;				
		}
		
	}
	
	public function createFile($filename){
		if(!isset($this->URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] URL not specified.");
			} else {
				return 0;  
			}
		}
		
		$URL = $this->URL . "/createFile";
		$payload = array(
			"filename" => $filename
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("PUT", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: createFile()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: createFile() - Server Error] Unable to create DB file.");
				} else {
					return 0;
				}
				break;
			case 1:
				return 1;
				break;
			case 2:
			case 3:
			case 4:
			case 5:
				if(THROW_EXCEPTIONS){
					throw new Exception("[StorX Remote: createFile() - Server Error] Unable to create DB file, file already exists.");
				} else {
					return 0;
				}
				break;
			default:
				if(THROW_EXCEPTIONS){
					doofusError();
				} else {
					return 0;
				}
				break;
		}
		
	}
		
	public function checkKey($keyName){
		if($this->initChecks() !== 1){
			return 0;
		}
		
		$URL = $this->URL . "/checkKey";
		$payload = array(
			"filename" => $this->DBfile,
			"keyName" => $keyName
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("GET", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: checkKey()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				return 0;
				break;
			case 1:
				return 1;
				break;
			default:
				if(THROW_EXCEPTIONS){
					doofusError();
				} else {
					return "STORX_REMOTE_YOU_BROKE_SOMETHING";
				}
				break;
		}	
	}	
	
	public function checkFile($filename){
		if(!isset($this->URL)){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote] URL not specified.");
			} else {
				return 0;  
			}
		}
		
		$URL = $this->URL . "/checkFile";
		$payload = array(
			"filename" => $filename,
			);
		if(!empty($this->password)){
			$payload["password"] = $this->password;
		}
		
		$response = $this->sendRequest("GET", $URL, NULL, $payload);
			
		if($response === false){
			if(THROW_EXCEPTIONS){
				throw new Exception("[StorX Remote: checkFile()] Unable to connect to StorX Receiver.");
			} else {
				return 0;
			}
		}
		
		if($this->serverErrorCheck($response["returnCode"]) === 0){
			return 0;
		}
		
		switch($response["returnCode"]){
			case 0:
				return 0;
				break;
			case 1:
				return 1;
				break;
			case 3:
				return 3;
				break;
			case 4:
				return 4;
				break;
			case 5:
				return 5;
				break;
			default:
				if(THROW_EXCEPTIONS){
					doofusError();
				} else {
					return "STORX_REMOTE_YOU_BROKE_SOMETHING";
				}
				break;
		}
	}
	

	
}
	