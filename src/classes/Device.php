<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDevice", type="object", required={"BTAddress", "headBTAddress", "description", "relayPathNo"})
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

    public $name;
	public $nameUpdateTime;
    /**
     * @var int
     * @SWG\Property()
     */
    public $relayPathNo;
    public $connectedToUser;
    public $batteryIsLow;
    public $batteryIsLowTime;
    public $wirocPythonVersion;
    public $wirocBLEAPIVersion;
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
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="name", type="string"),
 * 			 @SWG\Property(property="nameUpdateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="connectedToUser", type="boolean"),
 * 			 @SWG\Property(property="batteryIsLow", type="boolean"),
 * 			 @SWG\Property(property="batteryIsLowTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="wirocPythonVersion", type="string"),
 * 			 @SWG\Property(property="wirocBLEAPIVersion", type="string"),
 * 			 @SWG\Property(property="reportTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="connectedToInternetTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

