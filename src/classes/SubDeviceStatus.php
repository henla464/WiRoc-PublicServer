<?php

/**
 * @SWG\Definition(definition="NewSubDeviceStatus", type="object", required={"subDeviceId", "distanceToHead", "batteryLevel", "batteryLevelprecision"})
 */
class SubDeviceStatus
{
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
    public $distanceToHead;
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

    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="SubDeviceStatus",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewSubDeviceStatus"),
 *       @SWG\Schema(
 *           required={"id", "subDeviceId", "distanceToHead", "batteryLevel", "batteryLevelprecision", "createdTime"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 *           @SWG\Property(property="createdTime", type="dateTime")
 *       )
 *   }
 * )
 */

