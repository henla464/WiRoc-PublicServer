<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDevice", type="object", required={"BTAddress", "headBTAddress"})
 */
class Device
{
	public static $tableName = 'Devices';
	
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $BTAddress;
    /**
     * @SWG\Property()
     * @var string
     */
    public $headBTAddress;
    /**
     * @var string
     * @SWG\Property()
     */
    public $description;
    /**
     * @var string
     * @SWG\Property()
     */
    public $name;
    public $nameUpdateTime;
    /**
     * @var int
     * @SWG\Property()
     */
    public $relayPathNo;
    public $competitionId;
    public $competitionIdSetByUserId;
    public $batteryIsLow;
    public $batteryIsLowTime;
    public $batteryIsLowReceived;
    public $batteryIsLowReceivedTime;
    /**
     * @var string
     * @SWG\Property()
     */
    public $wirocPythonVersion;
    /**
     * @var string
     * @SWG\Property()
     */
    public $wirocBLEAPIVersion;
    /**
     * @var string
     * @SWG\Property()
     */
    public $hardwareVersion;
    public $reportTime;
    public $connectedToInternetTime;
    public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="Device",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewDevice"),
 *       @SWG\Schema(
 *           required={"id"},
 * 		@SWG\Property(property="id", format="integer", type="integer"),
 * 		@SWG\Property(property="nameUpdateTime", format="date-time", type="string"),
 * 		@SWG\Property(property="competitionId", type="integer"),
 * 		@SWG\Property(property="competitionIdSetByUserId", type="integer"),
 * 		@SWG\Property(property="batteryIsLow", type="boolean"),
 * 		@SWG\Property(property="batteryIsLowTime", format="date-time", type="string"),
 * 		@SWG\Property(property="batteryIsLowReceived", type="boolean"),
 * 		@SWG\Property(property="batteryIsLowReceivedTime", format="date-time", type="string"),
 * 		@SWG\Property(property="reportTime", format="date-time", type="string"),
 * 		@SWG\Property(property="connectedToInternetTime", format="date-time", type="string"),
 * 		@SWG\Property(property="updateTime", format="date-time", type="string"),
 * 		@SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */


/**
 *  @SWG\Definition(
 *   definition="DeviceAddToCompetition",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(
 *           required={"competitionId"},
 *           @SWG\Property(property="competitionId", type="integer")
 *       )
 *   }
 * )
 */

