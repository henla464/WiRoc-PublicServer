<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewUserDevice", type="object", required={"deviceId"})
 */
class UserDevice
{
	public static $tableName = 'UserDevices';
	
    public $id;
    /**
     * @SWG\Property()
     * @var int
     */
    public $userId;
    /**
     * @var int
     * @SWG\Property()
     */
    public $deviceId;
    
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="UserDevice",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewUserDevice"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

