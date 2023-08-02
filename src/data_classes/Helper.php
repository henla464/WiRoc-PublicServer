<?php
use Doctrine\Common\Annotations\AnnotationReader;
require_once('CommandResponse.php');

class Helper
{
	public $container;
	private $db;
	
    function __construct($c) {
       $this->container = $c;
       $this->db = $c->get('db');
    }
    
    private function GetSetAndValues($objectName, $objectArray) {
		$annotationReader = new AnnotationReader();
		
		$obj = new $objectName();
		$reflectionObject = new ReflectionObject($obj);
		$allPropertiesOfObject = $reflectionObject->getProperties();
		
		$values = [];
		$set = "";
		foreach ($allPropertiesOfObject AS $prop) {
			$propertyAnnotations = $annotationReader->getPropertyAnnotations($prop);
			if (sizeof($propertyAnnotations) > 0) {
				if (array_key_exists($prop->name, $objectArray)) {
					$set.="`".str_replace("`", "``", $prop->name)."`". "=:$prop->name, ";
					$values[$prop->name] = $objectArray[$prop->name];
				}
			}
		}
		return array("set"=>$set, "values"=>$values);
	}
	
	public function getSort($objectName, $request) {
		$returnSortString = '';
		$queryParams = $request->getQueryParams();
		$sort = $queryParams['sort'];
		$columns = explode(",", trim($sort));
		$properties = get_object_vars(new $objectName);
		
		foreach ($columns AS $column) {
			$columnAndSort = explode(" ", trim($column));
			$columnOnly = trim($columnAndSort[0]);
			$sortOnly = ' asc';
			if (sizeof($columnAndSort) > 1 and strtolower(trim($columnAndSort[1])) == 'desc') {
				$sortOnly = ' desc';
			}
			if (array_key_exists($columnOnly,$properties)) {
				$returnSortString .= "$columnOnly$sortOnly, ";
			}
		}
		if ($returnSortString == '') {
			return '';
		}
		return substr(" order by $returnSortString", 0, -2);
	}
	
	public function getLimit($request) {
		$limit = $request->getQueryParams()['limit'] ?? '';
		if (trim($limit) != '' && ctype_digit($limit)) {
			return "LIMIT " . $limit;
		}
		return '';
	}

	public function getCreatedTimeLimit($request) {
		$limitSeconds = $request->getQueryParams()['limitToCreatedTimeWithinSeconds'] ?? '';
		if (trim($limitSeconds) != '' && ctype_digit($limitSeconds)) {
			return "createdTime >= NOW() - INTERVAL " . $limitSeconds . " SECOND";
		}
		return '';
	}
	
	
    
    public function GetAll($objectName, $tableName, $request) {
		$sort = $this->getSort($objectName, $request);
		$limit = $this->getLimit($request);
		$stmt = $this->db->prepare("SELECT * FROM $tableName$sort $limit");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_CLASS, $objectName);
	}
	
	public function GetAllBySql($objectName, $sql, $values) {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($values);
		return $stmt->fetchAll(PDO::FETCH_CLASS, $objectName);
	}
    
    public function Get($objectName, $tableName, $id) {
		$stmt = $this->db->prepare("SELECT * FROM $tableName WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$stmt->setFetchMode(PDO::FETCH_CLASS, $objectName);
		$obj = $stmt->fetch();
		return $obj;
	}
	
	public function GetBySql($objectName, $sql, $values) {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($values);
		$stmt->setFetchMode(PDO::FETCH_CLASS, $objectName);
		$obj = $stmt->fetch();
		return $obj;
	}
	
	public function Insert($objectName, $objectArray, $tableName) {
		$setAndValues = $this->GetSetAndValues($objectName, $objectArray);
		$set = $setAndValues["set"];
		$values = $setAndValues["values"];
		$stmt = $this->db->prepare("INSERT INTO $tableName SET $set `createdTime` = NOW()");
		$stmt->execute($values);
		return $this->db->lastInsertId();
	}

	public function InsertOrUpdate($objectName, $objectArray, $tableName, $selectSQL, $objectArrayForSelect) {
		$device = $this->GetBySql($objectName, $selectSQL, $objectArrayForSelect);
		if ($device) {
			$id = $device->id;
			$this->Update($objectName, $objectArray, $tableName, $id);
			return $id;
		} else {
			return $this->Insert($objectName, $objectArray, $tableName);
		}
	}
    
    public function Update($objectName, $objectArray, $tableName, $id) {
		$setAndValues = $this->GetSetAndValues($objectName, $objectArray);
		$set = $setAndValues["set"];
		$values = $setAndValues["values"];
		$values['id'] = $id;
		$stmt = $this->db->prepare("UPDATE $tableName SET $set `updateTime` = NOW() WHERE id = :id");
		$stmt->execute($values);
	}
	
	public function UpdateByBTAddress($objectName, $objectArray, $tableName, $btAddress) {
		$setAndValues = $this->GetSetAndValues($objectName, $objectArray);
		$set = $setAndValues["set"];
		$values = $setAndValues["values"];
		$values['btAddress'] = $btAddress;
		$stmt = $this->db->prepare("UPDATE $tableName SET $set `updateTime` = NOW() WHERE BTAddress = :btAddress");
		$stmt->execute($values);
	}

	
	public function RunSql($sql, $values) {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($values);
	}
	
	
	public function Delete($id, $tableName) {
		$stmt = $this->db->prepare("DELETE FROM $tableName WHERE id = :id");
		$stmt->execute(['id'=>$id]);
	}
	
	public function DeleteBySql($sql, $values) {
		$stmt = $this->db->prepare($sql);
		$stmt->execute($values);
	}
	
	public function CreateTables() {
		
		/*$sql = 'DROP TABLE WiRocBLEAPIReleaseUpgradeScripts';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE WiRocPython2ReleaseUpgradeScripts';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE WiRocPython2Releases';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE WiRocBLEAPIReleases';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE ReleaseStatuses';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE DeviceStatuses';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE MessageStats';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE Devices';
		$stmt = $this->db->query($sql);
		$sql = 'DROP TABLE Competitions';
		$stmt = $this->db->query($sql);
		*/
		//$sql = 'DROP TABLE Users';
		//$stmt = $this->db->query($sql);
		
		

		$sql = 'CREATE TABLE IF NOT EXISTS Competitions (id int NOT NULL AUTO_INCREMENT, name varchar(50), createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		
		$sql = 'CREATE TABLE IF NOT EXISTS Devices (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), headBTAddress varchar(50), description varchar(255), 
				name varchar(50), nameUpdateTime datetime, relayPathNo int, competitionId int, competitionIdSetByUserId int,
				batteryIsLow boolean, batteryIsLowTime datetime, batteryIsLowReceived boolean, batteryIsLowReceivedTime datetime,
				wirocPythonVersion varchar(20), wirocBLEAPIVersion varchar(20), reportTime datetime, connectedToInternetTime datetime, updateTime datetime, 
				createdTime datetime, FOREIGN KEY(competitionId) REFERENCES Competitions(id), PRIMARY KEY (id))';

		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS DeviceStatuses (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), batteryLevel int, siStationNumber int, updateTime datetime, createdTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS MessageStats (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), adapterInstance varchar(50), 
				messageType varchar(50), status varchar(50), noOfMessages int, createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);

	
		$sql = 'CREATE TABLE IF NOT EXISTS Users (id int NOT NULL AUTO_INCREMENT, email varchar(255) UNIQUE NOT NULL, hashedPassword varchar(255), createdTime datetime, updateTime datetime, isAdmin boolean, recoveryGuid varchar(50), recoveryTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'UPDATE Users SET isAdmin = 1 WHERE email="laselase@gmail.com"';
		$stmt = $this->db->query($sql);
		

		$sql = 'CREATE TABLE IF NOT EXISTS ReleaseStatuses (id int NOT NULL AUTO_INCREMENT, displayName varchar(50), keyName varchar(50), sortOrder int, createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);

		/*
		$sql = 'INSERT INTO ReleaseStatuses VALUES(NULL, "Development", "DEV", 0, NOW(), NULL)';
		$stmt = $this->db->query($sql);
		$sql = 'INSERT INTO ReleaseStatuses VALUES(NULL, "Beta", "BETA", 5, NOW(), NULL)';
		$stmt = $this->db->query($sql);
		$sql = 'INSERT INTO ReleaseStatuses VALUES(NULL, "Production", "PROD", 10, NOW(), NULL)';
		$stmt = $this->db->query($sql);
		*/

		$sql = 'CREATE TABLE IF NOT EXISTS WiRocPython2Releases (id int NOT NULL AUTO_INCREMENT, releaseName varchar(50), versionNumber nvarchar(10), releaseStatusId int, minHWVersion int, minHWRevision int, maxHWVersion int, 
				maxHWRevision int, releaseNote varchar(500), md5HashOfReleaseFile varchar(32), createdTime datetime, updateTime datetime, FOREIGN KEY(releaseStatusId) REFERENCES ReleaseStatuses(id), PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS WiRocBLEAPIReleases (id int NOT NULL AUTO_INCREMENT, releaseName varchar(50), versionNumber nvarchar(10), releaseStatusId int, minHWVersion int, minHWRevision int, maxHWVersion int, 
				maxHWRevision int, releaseNote varchar(500), md5HashOfReleaseFile varchar(32), createdTime datetime, updateTime datetime, FOREIGN KEY(releaseStatusId) REFERENCES ReleaseStatuses(id), PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS WiRocBLEAPIReleaseUpgradeScripts (id int NOT NULL AUTO_INCREMENT, releaseId int,  scriptText varchar(5000), scriptNote varchar(255), createdTime datetime, 
				updateTime datetime, FOREIGN KEY(releaseId) REFERENCES WiRocBLEAPIReleases(id), PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS WiRocPython2ReleaseUpgradeScripts (id int NOT NULL AUTO_INCREMENT, releaseId int,  scriptText varchar(5000), scriptNote varchar(255), createdTime datetime, 
				updateTime datetime, FOREIGN KEY(releaseId) REFERENCES WiRocPython2Releases(id), PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		
		$res = new CommandResponse();
		$res->code = 0;
		$res->message = "Tables created";
		return $res;
	}
}
