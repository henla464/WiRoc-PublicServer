<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewSubDeviceStatus", type="object", required={"subDeviceId", "batteryLevel", "batteryLevelprecision"})
 */
class SubDeviceStatus
{
	public static $tableName = 'SubDeviceStatuses';
		
    public $id;
    /**
     * @SWG\Property()
     * @var integer
     */
    public $subDeviceId;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $batteryLevel;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $batteryLevelPrecision;

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="SubDeviceStatus",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewSubDeviceStatus"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

