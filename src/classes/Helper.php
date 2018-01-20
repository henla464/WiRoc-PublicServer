<?php
use Doctrine\Common\Annotations\AnnotationReader;
require_once('CommandResponse.php');

class Helper
{
	public $container;
	
	function __construct($c) {
       $this->container = $c;
    }
    
    private function GetSetAndValues($objectName, $objectArray) {
		$annotationReader = new AnnotationReader();
		#$reflectionClass = new ReflectionClass($objectName);
		#$classAnnotations = $annotationReader->getClassAnnotations($reflectionClass);
		#var_dump($classAnnotations);
		
		$obj = new $objectName();
		$reflectionObject = new ReflectionObject($obj);
		$allPropertiesOfObject = $reflectionObject->getProperties();
		
		$values = [];
		$set = "";
		foreach ($allPropertiesOfObject AS $prop) {
			$propertyAnnotations = $annotationReader->getPropertyAnnotations($prop);
			if (sizeof($propertyAnnotations) > 0) {
				$set.="`".str_replace("`", "``", $prop->name)."`". "=:$prop->name, ";
				$values[$prop->name] = $objectArray[$prop->name];
			}
		}
		#var_dump($set);
		#foreach ($classAnnotations[0]->required AS $columnName) {
			#$set.="`".str_replace("`", "``", $columnName)."`". "=:$columnName, ";
			#$values[$columnName] = $objectArray[$columnName];
		#}
		return array("set"=>$set, "values"=>$values);
	}
	
	public function getSort($objectName, $request) {
		$returnSortString = '';
		$sort = $request->getQueryParam('sort');
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
		$limit = $request->getQueryParam('limit');
		if (trim($limit) != '' && ctype_digit($limit)) {
			return "LIMIT " . $limit;
		}
		return '';
	}
	
    
    public function GetAll($objectName, $tableName, $request) {
		$sort = $this->getSort($objectName, $request);
		$limit = $this->getLimit($request);
		$stmt = $this->container->db->prepare("SELECT * FROM $tableName$sort $limit");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_CLASS, $objectName);
	}
	
	public function GetAllBySql($objectName, $sql, $values) {
		$stmt = $this->container->db->prepare($sql);
		$stmt->execute($values);
		return $stmt->fetchAll(PDO::FETCH_CLASS, $objectName);
	}
    
    public function Get($objectName, $tableName, $id) {
		$stmt = $this->container->db->prepare("SELECT * FROM  $tableName WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$stmt->setFetchMode(PDO::FETCH_CLASS, $objectName);
		$obj = $stmt->fetch();
		return $obj;
	}
	
	public function GetBySql($objectName, $sql, $values) {
		$stmt = $this->container->db->prepare($sql);
		$stmt->execute($values);
		$stmt->setFetchMode(PDO::FETCH_CLASS, $objectName);
		$obj = $stmt->fetch();
		return $obj;
	}
	
	public function Insert($objectName, $objectArray, $tableName) {
		$setAndValues = $this->GetSetAndValues($objectName, $objectArray);
		$set = $setAndValues["set"];
		$values = $setAndValues["values"];
		$stmt = $this->container->db->prepare("INSERT INTO $tableName SET $set `createdTime` = NOW()");
		$stmt->execute($values);
		return $this->container->db->lastInsertId();
	}
    
    public function Update($objectName, $objectArray, $tableName, $id) {
		$setAndValues = $this->GetSetAndValues($objectName, $objectArray);
		$set = $setAndValues["set"];
		$values = $setAndValues["values"];
		$values['id'] = $id;
		$stmt = $this->container->db->prepare("UPDATE $tableName SET $set `updateTime` = NOW() WHERE id = :id");
		$stmt->execute($values);
	}
	
	public function Delete($id, $tableName) {
		$stmt = $this->container->db->prepare("DELETE FROM $tableName WHERE id = :id");
		$stmt->execute(['id'=>$id]);
	}
	
	public function DeleteBySql($sql, $values) {
		$stmt = $this->container->db->prepare($sql);
		$stmt->execute($values);
	}
	
	public function CreateTables() {
		
		$sql = 'CREATE TABLE IF NOT EXISTS Devices (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), name varchar(50), description varchar(255), createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->container->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS SubDevices (id int NOT NULL AUTO_INCREMENT, headBTAddress varchar(50), distanceToHead int, createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->container->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS SubDeviceStatuses (id int NOT NULL AUTO_INCREMENT, subDeviceId int, distanceToHead int, batteryLevel int, batteryLevelprecision int, createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->container->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS MessageStats (id int NOT NULL AUTO_INCREMENT, deviceId int, adapterInstance varchar(50), ';
		$sql .= 'messageType varchar(50), status varchar(50), noOfMessages int, createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->container->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS Users (id int NOT NULL AUTO_INCREMENT, oauthProvider varchar(255), oauthUserId varchar(255), email varchar(255), createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->container->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS UserDevices (id int NOT NULL AUTO_INCREMENT, userId int, deviceId int, createdTime datetime, PRIMARY KEY (id))';
		$stmt = $this->container->db->query($sql);
		
		$res = new CommandResponse();
		$res->code = 0;
		$res->message = "Tables created";
		return $res;
	}
	
}
