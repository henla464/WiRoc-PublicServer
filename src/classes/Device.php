<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDevice", type="object", required={"name", "BTAddress", "description"})
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
     * @var string
     * @SWG\Property()
     */
    public $name;
    /**
     * @var string
     * @SWG\Property()
     */
    public $description;
    
    public $connectedToUser;
    public $batteryIsLow;
    public $batteryIsLowTime;
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
 * 			 @SWG\Property(property="connectedToUser", type="boolean"),
 * 			 @SWG\Property(property="batteryIsLow", type="boolean"),
* 			 @SWG\Property(property="batteryIsLowTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

