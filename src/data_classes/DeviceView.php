<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDeviceView", type="object", required={"BTAddress", "headBTAddress", "description", "relayPathNo"})
 */
class DeviceView
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
    public $competitionId;
    public $competitionName;
    public $batteryIsLow;
    public $batteryIsLowTime;
    public $wirocPythonVersion;
    public $wirocBLEAPIVersion;
    public $recentlyReported;
    public $reportTime;
	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="DeviceView",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewDeviceView"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="name", type="string"),
 * 			 @SWG\Property(property="nameUpdateTime", format="date-time", type="string"),
 *           @SWG\Property(property="competitionId", type="integer"),
 *           @SWG\Property(property="competitionName", type="string"),
 * 			 @SWG\Property(property="batteryIsLow", type="boolean"),
 * 			 @SWG\Property(property="batteryIsLowTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="wirocPythonVersion", type="string"),
 * 			 @SWG\Property(property="wirocBLEAPIVersion", type="string"),
 * 			 @SWG\Property(property="recentlyReported", type="boolean"),
 * 			 @SWG\Property(property="reportTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

