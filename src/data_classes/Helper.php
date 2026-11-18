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
		
		$sql = 'ALTER TABLE Devices ADD COLUMN IF NOT EXISTS releaseStatusKeyName varchar(50)';
		$stmt = $this->db->query($sql);

		$sql = 'CREATE TABLE IF NOT EXISTS Competitions (id int NOT NULL AUTO_INCREMENT, name varchar(50), createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		
		$sql = 'CREATE TABLE IF NOT EXISTS Devices (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), headBTAddress varchar(50), description varchar(255), 
				name varchar(50), nameUpdateTime datetime, relayPathNo int, competitionId int, competitionIdSetByUserId int,
				batteryIsLow boolean, batteryIsLowTime datetime, batteryIsLowReceived boolean, batteryIsLowReceivedTime datetime,
				wirocPythonVersion varchar(20), wirocBLEAPIVersion varchar(20), hardwareVersion varchar(20), releaseStatusKeyName varchar(50), reportTime datetime, connectedToInternetTime datetime, updateTime datetime, 
				createdTime datetime, FOREIGN KEY(competitionId) REFERENCES Competitions(id), PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS DeviceStatuses (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), batteryLevel int, siStationNumber int, noOfLoraMsgSentNotAcked int, allLoraPunchesSentOK boolean, internalSRRREDEnabled boolean,
            internalSRRREDAckEnabled boolean, internalSRRBLUEEnabled boolean, internalSRRBLUEAckEnabled boolean, SRRDongleREDFound boolean, SRRDongleREDAckEnabled boolean, SRRDongleBLUEFound boolean, SRRDongleBLUEAckEnabled boolean, internalSRRREDDirection boolean,
            internalSRRBLUEDirection boolean, tcpIPSirapEnabled boolean, serialPortBaudRate boolean, serialPortDirection boolean, siMasterConnectedOnUSB1 boolean, siMasterConnectedOnUSB2 boolean, updateTime datetime, createdTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);
		$sql = 'CREATE TABLE IF NOT EXISTS MessageStats (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50), adapterInstance varchar(50), 
				messageType varchar(50), status varchar(50), noOfMessages int, createdTime datetime, updateTime datetime, PRIMARY KEY (id))';
		$stmt = $this->db->query($sql);

	
		$sql = 'CREATE TABLE IF NOT EXISTS Users (id int NOT NULL AUTO_INCREMENT, email varchar(255) UNIQUE NOT NULL, hashedPassword varchar(255), createdTime datetime, updateTime datetime, isAdmin boolean, recoveryGuid varchar(50), recoveryTime datetime, deletionGuid varchar(50), deletionTime datetime, PRIMARY KEY (id))';
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

		$sql = 'CREATE TABLE IF NOT EXISTS DeviceAccesses (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50) NOT NULL, UserId int NOT NULL,
				GrantedAt datetime, GrantedByUserId int, updateTime datetime, createdTime datetime, PRIMARY KEY (id),
				UNIQUE INDEX idx_deviceaccess_btaddress_userid (BTAddress, UserId))';
		$stmt = $this->db->query($sql);
		

			$sql = 'CREATE TABLE IF NOT EXISTS McpTokens (id int NOT NULL AUTO_INCREMENT, UserId int NOT NULL, name varchar(50), tokenHash char(64) NOT NULL,
					lastUsedTime datetime, updateTime datetime, createdTime datetime, PRIMARY KEY (id),
					UNIQUE INDEX idx_mcptokens_tokenhash (tokenHash), INDEX idx_mcptokens_userid (UserId))';
			$stmt = $this->db->query($sql);

			$sql = 'CREATE TABLE IF NOT EXISTS CompetitionAccesses (id int NOT NULL AUTO_INCREMENT, competitionId int NOT NULL, UserId int NOT NULL,
					GrantedAt datetime, GrantedByUserId int, updateTime datetime, createdTime datetime, PRIMARY KEY (id),
					UNIQUE INDEX idx_competitionaccess_competition_userid (competitionId, UserId))';
			$stmt = $this->db->query($sql);

			$sql = 'CREATE TABLE IF NOT EXISTS Controls (id int NOT NULL AUTO_INCREMENT, competitionId int NOT NULL, controlNumber int NOT NULL,
					name varchar(50), description varchar(255), updateTime datetime, createdTime datetime,
					FOREIGN KEY(competitionId) REFERENCES Competitions(id), PRIMARY KEY (id),
					UNIQUE INDEX idx_controls_competition_controlnumber (competitionId, controlNumber))';
			$stmt = $this->db->query($sql);

			$sql = 'ALTER TABLE Competitions ADD COLUMN IF NOT EXISTS createdByUserId int';
			$stmt = $this->db->query($sql);

			$sql = 'ALTER TABLE Devices ADD COLUMN IF NOT EXISTS controlId int';
			$stmt = $this->db->query($sql);

			$sql = 'ALTER TABLE Controls ADD COLUMN IF NOT EXISTS mapX double';
			$stmt = $this->db->query($sql);
			$sql = 'ALTER TABLE Controls ADD COLUMN IF NOT EXISTS mapY double';
			$stmt = $this->db->query($sql);

			$sql = "ALTER TABLE Controls ADD COLUMN IF NOT EXISTS controlType varchar(20) DEFAULT 'Control'";
			$stmt = $this->db->query($sql);

			try { $this->db->exec("DROP INDEX idx_controls_competition_controlnumber ON Controls"); } catch (\Exception $e) {}
			try { $this->db->exec("DROP INDEX idx_controls_competition_controlnumber_v2 ON Controls"); } catch (\Exception $e) {}
			try { $this->db->exec("CREATE UNIQUE INDEX idx_controls_competition_controlnumber_v2 ON Controls (competitionId, controlType, controlNumber)"); } catch (\Exception $e) {}
			try { $this->db->exec("UPDATE Controls SET controlType = 'Control' WHERE controlType IS NULL OR controlType = ''"); } catch (\Exception $e) {}


			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS mapScale double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS mapScaleRatio int";
			$stmt = $this->db->query($sql);

			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1X double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1Y double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1Lat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1Lng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2X double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2Y double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2Lat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2Lng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP1Lat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP1Lng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP2Lat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP2Lng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dGridSize int";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTheta double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitPhi double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitDist double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTargetX double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTargetY double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTargetZ double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS satLat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS satLng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS satZoom int";
			$stmt = $this->db->query($sql);
			$sql = 'CREATE TABLE IF NOT EXISTS CompetitionMaps (id int NOT NULL AUTO_INCREMENT, competitionId int NOT NULL,
					originalFileName varchar(255), storedFileName varchar(255), fileType varchar(10),
					defaultZoom int, defaultCenterX double, defaultCenterY double,
					updateTime datetime, createdTime datetime,
					FOREIGN KEY(competitionId) REFERENCES Competitions(id), PRIMARY KEY (id),
					UNIQUE INDEX idx_competitionmaps_competitionid (competitionId))';
			$stmt = $this->db->query($sql);

		$res = new CommandResponse();
		$res->code = 0;
		$res->message = "Tables created";
		return $res;
	}

	public function UpdateDatabaseSchema() {
        $columns = [
	    "hardwareVersion varchar(20)",
	    "publicIPAddress varchar(45)",
	    "localIPAddress varchar(45)",
	    "ipAddressTime datetime"
        ];
        foreach ($columns as $col) {
            $sql = "ALTER TABLE Devices ADD COLUMN IF NOT EXISTS $col";
            $stmt = $this->db->query($sql);
        }

        $sql = "ALTER TABLE Users ADD COLUMN IF NOT EXISTS deletionGuid varchar(50)";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE Users ADD COLUMN IF NOT EXISTS deletionTime datetime";
        $stmt = $this->db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS DeviceAccesses (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50) NOT NULL, UserId int NOT NULL,
                GrantedAt datetime, GrantedByUserId int, updateTime datetime, createdTime datetime, PRIMARY KEY (id),
                UNIQUE INDEX idx_deviceaccess_btaddress_userid (BTAddress, UserId))";
        $stmt = $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS CompetitionAccesses (id int NOT NULL AUTO_INCREMENT, competitionId int NOT NULL, UserId int NOT NULL,
                GrantedAt datetime, GrantedByUserId int, updateTime datetime, createdTime datetime, PRIMARY KEY (id),
                UNIQUE INDEX idx_competitionaccess_competition_userid (competitionId, UserId))';
        $stmt = $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS McpTokens (id int NOT NULL AUTO_INCREMENT, UserId int NOT NULL, name varchar(50), tokenHash char(64) NOT NULL,
                lastUsedTime datetime, updateTime datetime, createdTime datetime, PRIMARY KEY (id),
                UNIQUE INDEX idx_mcptokens_tokenhash (tokenHash), INDEX idx_mcptokens_userid (UserId))';
        $stmt = $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS Controls (id int NOT NULL AUTO_INCREMENT, competitionId int NOT NULL, controlNumber int NOT NULL,
                name varchar(50), description varchar(255), updateTime datetime, createdTime datetime,
                FOREIGN KEY(competitionId) REFERENCES Competitions(id), PRIMARY KEY (id),
                UNIQUE INDEX idx_controls_competition_controlnumber (competitionId, controlNumber))';
        $stmt = $this->db->query($sql);

        $columns2 = [
            "createdByUserId int",
            "controlId int"
        ];
        foreach ($columns2 as $col) {
            $sql = "ALTER TABLE Devices ADD COLUMN IF NOT EXISTS $col";
            $stmt = $this->db->query($sql);
        }
        $sql = "ALTER TABLE Competitions ADD COLUMN IF NOT EXISTS createdByUserId int";
        $stmt = $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS CompetitionMaps (id int NOT NULL AUTO_INCREMENT, competitionId int NOT NULL,
                originalFileName varchar(255), storedFileName varchar(255), fileType varchar(10),
                defaultZoom int, defaultCenterX double, defaultCenterY double,
                updateTime datetime, createdTime datetime,
                FOREIGN KEY(competitionId) REFERENCES Competitions(id), PRIMARY KEY (id),
                UNIQUE INDEX idx_competitionmaps_competitionid (competitionId))';
        $stmt = $this->db->query($sql);

        $sql = "ALTER TABLE Controls ADD COLUMN IF NOT EXISTS mapX double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE Controls ADD COLUMN IF NOT EXISTS mapY double";
        $stmt = $this->db->query($sql);

        $sql = "ALTER TABLE Controls ADD COLUMN IF NOT EXISTS controlType varchar(20) DEFAULT 'Control'";
        $stmt = $this->db->query($sql);

        // Recreate unique index to include controlType
        try { $this->db->exec("DROP INDEX idx_controls_competition_controlnumber ON Controls"); } catch (\Exception $e) {}
        try { $this->db->exec("DROP INDEX idx_controls_competition_controlnumber_v2 ON Controls"); } catch (\Exception $e) {}
        try { $this->db->exec("CREATE UNIQUE INDEX idx_controls_competition_controlnumber_v2 ON Controls (competitionId, controlType, controlNumber)"); } catch (\Exception $e) {}
        try { $this->db->exec("UPDATE Controls SET controlType = 'Control' WHERE controlType IS NULL OR controlType = ''"); } catch (\Exception $e) {}

        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS mapScale double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS mapScaleRatio int";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1X double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1Y double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1Lat double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP1Lng double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2X double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2Y double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2Lat double";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS georefP2Lng double";
        $stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP1Lat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP1Lng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP2Lat double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dP2Lng double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dGridSize int";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTheta double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitPhi double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitDist double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTargetX double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTargetY double";
			$stmt = $this->db->query($sql);
			$sql = "ALTER TABLE CompetitionMaps ADD COLUMN IF NOT EXISTS terrain3dOrbitTargetZ double";
			$stmt = $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS MeshTopologies (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50) NOT NULL,
                snapshotTime bigint, isRoot tinyint NOT NULL DEFAULT 0, nodes longtext,
                updateTime datetime, createdTime datetime, PRIMARY KEY (id),
                UNIQUE INDEX idx_meshtopologies_btaddress (BTAddress))';
        $stmt = $this->db->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS MeshStates (id int NOT NULL AUTO_INCREMENT, BTAddress varchar(50) NOT NULL,
                snapshotTime bigint, isRoot tinyint NOT NULL DEFAULT 0, addr int, state int, parentAddr int,
                hopCount int, pathCost int, neighbourCount int, transmitQueue int, txOk int, txBusy int,
                allocatedAddresses int,
                epoch int, overheadPercent double, ackTimeoutMs int, standby tinyint NOT NULL DEFAULT 0,
                updateTime datetime, createdTime datetime, PRIMARY KEY (id),
                UNIQUE INDEX idx_meshstates_btaddress (BTAddress))';
        $stmt = $this->db->query($sql);

        $sql = "ALTER TABLE MeshStates ADD COLUMN IF NOT EXISTS txOk int";
        $stmt = $this->db->query($sql);
        $sql = "ALTER TABLE MeshStates ADD COLUMN IF NOT EXISTS txBusy int";
        $stmt = $this->db->query($sql);

        $res = new CommandResponse();
        $res->code = 0;
        $res->message = "Devices columns added";
        return $res;
    }

    public function QueryAll($sql, $values = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function GetDevicesViewRows($headBTAddress = '', $competitionId = '', $sortSql = '', $limitSql = '', $visibleToUserId = null) {
        $cls = DeviceView::class;
        $sql = 'SELECT CASE WHEN Devices.reportTime > DATE_ADD(SYSDATE(), INTERVAL -6 MINUTE) THEN true ELSE false END recentlyReported,
        CASE WHEN Devices.connectedToInternetTime > DATE_ADD(SYSDATE(), INTERVAL -1 MINUTE) THEN true ELSE false END connectedToInternet,
        Devices.*, Competitions.name as competitionName, Controls.controlNumber as controlNumber, Controls.name as controlName, Controls.controlType as controlType, IFNULL(DeviceStatuses.batteryLevel, 0) as batteryLevel, IFNULL(DeviceStatuses.createdTime, "1980-01-01T00:00:08.000Z") as batteryLevelTime
        FROM Devices LEFT JOIN Competitions ON Devices.competitionId = Competitions.id
        LEFT JOIN Controls ON Devices.controlId = Controls.id
        LEFT JOIN DeviceStatuses ON (DeviceStatuses.Id = (SELECT Id FROM DeviceStatuses WHERE BTAddress = Devices.BTAddress ORDER BY createdTime DESC LIMIT 1))';

        $sqlParam = [];
        $conditions = [];
        if (trim($headBTAddress) != '') {
            $conditions[] = 'Devices.headBTAddress = :headBTAddress';
            $sqlParam['headBTAddress'] = $headBTAddress;
        }
        if (trim($competitionId) != '') {
            $conditions[] = 'Devices.competitionId = :competitionId';
            $sqlParam['competitionId'] = $competitionId;
        }
        if ($visibleToUserId !== null) {
            // same rule as UserCanMonitorDevice: competition the user can view, or an explicit device grant
            $conditions[] = '(Devices.competitionId IN (SELECT c.id FROM Competitions c WHERE c.createdByUserId = :visUserId1
                    OR c.id IN (SELECT ca.competitionId FROM CompetitionAccesses ca WHERE ca.UserId = :visUserId2))
                OR Devices.BTAddress IN (SELECT da.BTAddress FROM DeviceAccesses da WHERE da.UserId = :visUserId3))';
            $sqlParam['visUserId1'] = $visibleToUserId;
            $sqlParam['visUserId2'] = $visibleToUserId;
            $sqlParam['visUserId3'] = $visibleToUserId;
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= $sortSql . ' ' . $limitSql;

        return $this->GetAllBySql($cls, $sql, $sqlParam);
    }

    public function BuildCompetitionMeshTopology($competitionId) {
        $deviceSql = "SELECT Devices.BTAddress, Devices.name, Devices.controlId,
            UPPER(REPLACE(Devices.BTAddress, ':', '')) AS deviceId,
            Controls.controlNumber, Controls.controlType, Controls.mapX, Controls.mapY,
            CASE WHEN Devices.reportTime > DATE_ADD(SYSDATE(), INTERVAL -6 MINUTE) THEN true ELSE false END recentlyReported,
            CASE WHEN Devices.connectedToInternetTime > DATE_ADD(SYSDATE(), INTERVAL -1 MINUTE) THEN true ELSE false END connectedToInternet
        FROM Devices LEFT JOIN Controls ON Devices.controlId = Controls.id
        WHERE Devices.competitionId = :competitionId";
        $devices = $this->GetAllBySql(MeshTopologyNode::class, $deviceSql, ['competitionId' => $competitionId]);

        $deviceByDeviceId = [];
        foreach ($devices as $device) {
            $deviceByDeviceId[strtoupper($device->deviceId)] = $device;
        }

        $stateSql = "SELECT ms.* FROM MeshStates ms JOIN Devices d ON d.BTAddress = ms.BTAddress
        WHERE d.competitionId = :competitionId
          AND ms.updateTime > DATE_ADD(SYSDATE(), INTERVAL -10 MINUTE)";
        $states = $this->GetAllBySql(MeshState::class, $stateSql, ['competitionId' => $competitionId]);
        $stateByBTAddress = [];
        foreach ($states as $state) {
            $stateByBTAddress[$state->BTAddress] = $state;
        }

        // Root-only uploads: the newest fresh root snapshot is the tree.
        $mtCls = MeshTopology::class;
        $snapshotSql = "SELECT m.* FROM {$mtCls::$tableName} m JOIN Devices d ON d.BTAddress = m.BTAddress
        WHERE d.competitionId = :competitionId AND m.isRoot = 1
          AND m.updateTime > DATE_ADD(SYSDATE(), INTERVAL -10 MINUTE)
        ORDER BY m.snapshotTime DESC LIMIT 1";
        $snapshot = $this->GetBySql($mtCls, $snapshotSql, ['competitionId' => $competitionId]);

        $res = ['updatedTime' => null, 'health' => null, 'nodes' => [], 'links' => []];
        if (!$snapshot) {
            return $res;
        }

        $snapshotNodes = json_decode($snapshot->nodes, true);
        if (!is_array($snapshotNodes)) $snapshotNodes = [];

        $addrToDeviceId = [];
        foreach ($snapshotNodes as $node) {
            $addrToDeviceId[(int)$node['addr']] = strtoupper($node['deviceId']);
        }

        $isDrawable = function ($device) {
            return $device !== null && $device->mapX !== null && $device->mapY !== null;
        };

        $nodes = [];
        $drawableDeviceIds = [];
        foreach ($snapshotNodes as $node) {
            $deviceId = strtoupper($node['deviceId']);
            $device = $deviceByDeviceId[$deviceId] ?? null;
            if (!$isDrawable($device)) continue;

            $parentDeviceId = null;
            $parentAddr = (int)($node['parent'] ?? 0);
            if ($parentAddr > 0 && isset($addrToDeviceId[$parentAddr]) && $addrToDeviceId[$parentAddr] !== $deviceId) {
                $parentDeviceId = $addrToDeviceId[$parentAddr];
            }
            $parentDevice = $parentDeviceId !== null ? ($deviceByDeviceId[$parentDeviceId] ?? null) : null;

            $state = $stateByBTAddress[$device->BTAddress] ?? null;
            $topologyHopCount = isset($node['hopCount']) ? (int)$node['hopCount'] : null;
            $nodes[] = [
                'BTAddress' => $device->BTAddress,
                'deviceId' => $deviceId,
                'addr' => (int)$node['addr'],
                'isRoot' => $device->BTAddress === $snapshot->BTAddress,
                'parentBTAddress' => $isDrawable($parentDevice) ? $parentDevice->BTAddress : null,
                'hopCount' => $state && $state->hopCount !== null ? (int)$state->hopCount : $topologyHopCount,
                'state' => $state ? (int)$state->state : null,
                'pathCost' => $state && $state->pathCost !== null ? (int)$state->pathCost : null,
                'transmitQueue' => $state && $state->transmitQueue !== null ? (int)$state->transmitQueue : null,
                'txOk' => $state && $state->txOk !== null ? (int)$state->txOk : null,
                'txBusy' => $state && $state->txBusy !== null ? (int)$state->txBusy : null,
                'standby' => $state ? (bool)$state->standby : false,
                'snapshotTime' => (int)$snapshot->snapshotTime,
                'controlId' => $device->controlId,
                'controlNumber' => $device->controlNumber,
                'controlType' => $device->controlType,
                'mapX' => $device->mapX,
                'mapY' => $device->mapY,
                'deviceName' => $device->name,
                'recentlyReported' => (bool)$device->recentlyReported,
                'connectedToInternet' => (bool)$device->connectedToInternet,
            ];
            $drawableDeviceIds[$deviceId] = true;
        }

        // Neighbour links: resolve snapshot-local addr -> deviceId, keep only links
        // whose both ends are drawable, dedupe A-B/B-A, keep the best (lowest) cost.
        $links = [];
        foreach ($snapshotNodes as $node) {
            $fromDeviceId = strtoupper($node['deviceId']);
            if (!isset($drawableDeviceIds[$fromDeviceId])) continue;
            if (empty($node['neighbours']) || !is_array($node['neighbours'])) continue;
            foreach ($node['neighbours'] as $neighbour) {
                if (!isset($neighbour['addr'])) continue;
                $toDeviceId = $addrToDeviceId[(int)$neighbour['addr']] ?? null;
                if ($toDeviceId === null || $toDeviceId === $fromDeviceId) continue;
                if (!isset($drawableDeviceIds[$toDeviceId])) continue;
                $key = $fromDeviceId < $toDeviceId ? "$fromDeviceId|$toDeviceId" : "$toDeviceId|$fromDeviceId";
                $cost = isset($neighbour['cost']) ? (int)$neighbour['cost'] : null;
                if (!isset($links[$key]) || ($cost !== null && ($links[$key]['cost'] === null || $cost < $links[$key]['cost']))) {
                    $links[$key] = [
                        'fromBTAddress' => $deviceByDeviceId[$fromDeviceId]->BTAddress,
                        'toBTAddress' => $deviceByDeviceId[$toDeviceId]->BTAddress,
                        'cost' => $cost,
                    ];
                }
            }
        }

        $rootState = $stateByBTAddress[$snapshot->BTAddress] ?? null;
        if ($rootState) {
            $res['health'] = [
                'epoch' => $rootState->epoch !== null ? (int)$rootState->epoch : null,
                'allocatedAddresses' => $rootState->allocatedAddresses !== null ? (int)$rootState->allocatedAddresses : null,
                'overheadPercent' => $rootState->overheadPercent !== null ? (float)$rootState->overheadPercent : null,
                'ackTimeoutMs' => $rootState->ackTimeoutMs !== null ? (int)$rootState->ackTimeoutMs : null,
                'snapshotTime' => (int)$rootState->snapshotTime,
            ];
        }

        usort($nodes, function ($a, $b) {
            $ah = $a['hopCount'] === null ? PHP_INT_MAX : $a['hopCount'];
            $bh = $b['hopCount'] === null ? PHP_INT_MAX : $b['hopCount'];
            if ($ah !== $bh) return $ah <=> $bh;
            return strcmp($a['BTAddress'], $b['BTAddress']);
        });

        $res['updatedTime'] = (int)$snapshot->snapshotTime;
        $res['nodes'] = $nodes;
        $res['links'] = array_values($links);
        return $res;
    }

    /**
     * Competition access rules without $_SESSION / PSR-7, so the MCP endpoint can apply the same rules.
     * Admins and creators always pass; others need a CompetitionAccesses row.
     */
    public function UserHasCompetitionAccess($userId, $isAdmin, $competitionId, $requireEdit = false) {
        if ($isAdmin) return true;
        $compCls = Competition::class;
        $competition = $this->Get($compCls, $compCls::$tableName, $competitionId);
        if (!$competition) return false;
        if ($competition->createdByUserId == $userId) return true;
        try {
            $compAccessCls = CompetitionAccess::class;
            $sql = "SELECT accessRole FROM {$compAccessCls::$tableName} WHERE competitionId = :competitionId AND UserId = :UserId";
            if ($requireEdit) $sql .= " AND accessRole = 'edit'";
            return $this->GetBySql($compAccessCls, $sql, ['competitionId' => $competitionId, 'UserId' => $userId]) !== null;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Competitions the user may see, with the user's accessRole ('edit' for admins and creators).
     */
    public function GetCompetitionsForUser($userId, $isAdmin) {
        $comps = $this->QueryAll("SELECT id, name, createdTime, createdByUserId FROM Competitions ORDER BY id LIMIT 200");
        if ($isAdmin) {
            foreach ($comps as &$c) { $c['accessRole'] = 'edit'; }
            unset($c);
            return $comps;
        }
        $grants = [];
        foreach ($this->QueryAll("SELECT competitionId, accessRole FROM CompetitionAccesses WHERE UserId = :userId", ['userId' => $userId]) as $grant) {
            $grants[(int)$grant['competitionId']] = $grant['accessRole'];
        }
        $visible = [];
        foreach ($comps as $c) {
            if ($c['createdByUserId'] == $userId) {
                $c['accessRole'] = 'edit';
            } elseif (isset($grants[(int)$c['id']])) {
                $c['accessRole'] = $grants[(int)$c['id']];
            } else {
                continue;
            }
            $visible[] = $c;
        }
        return $visible;
    }

    /**
     * Device monitoring rule without $_SESSION: device in a competition the user can view,
     * or an explicit DeviceAccesses grant. Mirrors the filter in GetDevicesViewRows.
     */
    public function UserCanMonitorDevice($userId, $BTAddress) {
        $sql = "SELECT 1 FROM Devices d WHERE d.BTAddress = :BTAddress AND (
                d.competitionId IN (SELECT c.id FROM Competitions c WHERE c.createdByUserId = :userId1
                    OR c.id IN (SELECT ca.competitionId FROM CompetitionAccesses ca WHERE ca.UserId = :userId2))
                OR d.BTAddress IN (SELECT da.BTAddress FROM DeviceAccesses da WHERE da.UserId = :userId3)) LIMIT 1";
        return $this->QueryAll($sql, ['BTAddress' => $BTAddress, 'userId1' => $userId, 'userId2' => $userId, 'userId3' => $userId]) !== [];
    }

    /**
     * Check if the current session user has competition view access (any role including 'view').
     * Admins and creators always pass.
     */
    public function requireCompetitionViewAccess($response, $competitionId) {
        if (!empty($_SESSION['userIsAdmin'])) return null;
        $compCls = Competition::class;
        $competition = $this->Get($compCls, $compCls::$tableName, $competitionId);
        if (!$competition) {
            $res = new CommandResponse(); $res->code = 1; $res->message = "Competition not found";
            $response->getBody()->write(json_encode($res));
            return $response->withStatus(404);
        }
        if ($competition->createdByUserId == $_SESSION['userId']) return null;
        try {
            $compAccessCls = CompetitionAccess::class;
            $sql = "SELECT * FROM {$compAccessCls::$tableName} WHERE competitionId = :competitionId AND UserId = :UserId";
            $access = $this->GetBySql($compAccessCls, $sql, ['competitionId' => $competitionId, 'UserId' => $_SESSION['userId']]);
            if ($access) return null;
        } catch (\PDOException $e) {}
        $res = new CommandResponse(); $res->code = 3; $res->message = "You do not have access to this competition";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(403);
    }

    /**
     * Check if the current session user has competition edit access ('edit' role only).
     * Admins and creators always pass. Others must have an 'edit' role grant.
     */
    public function requireCompetitionEditAccess($response, $competitionId) {
        if (!empty($_SESSION['userIsAdmin'])) return null;
        $compCls = Competition::class;
        $competition = $this->Get($compCls, $compCls::$tableName, $competitionId);
        if (!$competition) {
            $res = new CommandResponse(); $res->code = 1; $res->message = "Competition not found";
            $response->getBody()->write(json_encode($res));
            return $response->withStatus(404);
        }
        if ($competition->createdByUserId == $_SESSION['userId']) return null;
        try {
            $compAccessCls = CompetitionAccess::class;
            $sql = "SELECT * FROM {$compAccessCls::$tableName} WHERE competitionId = :competitionId AND UserId = :UserId AND accessRole = 'edit'";
            $access = $this->GetBySql($compAccessCls, $sql, ['competitionId' => $competitionId, 'UserId' => $_SESSION['userId']]);
            if ($access) return null;
        } catch (\PDOException $e) {}
        $res = new CommandResponse(); $res->code = 3; $res->message = "You need edit access for this action";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(403);
    }

    /**
     * Check if the current session user has device access.
     * Admins always pass. Others must have a DeviceAccesses row.
     *
     * @param object $response The PSR-7 response object
     * @param string $BTAddress
     * @return object|null Returns response with error status if denied, null if authorized
     */
    public function requireDeviceAccess($response, $BTAddress) {
        if (!empty($_SESSION['userIsAdmin'])) return null;

        $deviceAccessCls = DeviceAccess::class;
        $checkSql = "SELECT * FROM {$deviceAccessCls::$tableName} WHERE BTAddress = :BTAddress AND UserId = :UserId";
        $access = $this->GetBySql($deviceAccessCls, $checkSql, [
            'BTAddress' => $BTAddress,
            'UserId' => $_SESSION['userId']
        ]);
        if ($access) return null;

        $res = new CommandResponse();
        $res->code = 4;
        $res->message = "You do not have access to this device";
        $response->getBody()->write(json_encode($res));
        return $response->withStatus(403);
    }

}
