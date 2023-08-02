<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDeviceStatus", type="object", required={"BTAddress", "batteryLevel", "siStationNumber"})
 */
class DeviceStatus
{
	public static $tableName = 'DeviceStatuses';
		
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $BTAddress;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $batteryLevel;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $siStationNumber;

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="DeviceStatus",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewDeviceStatus"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

