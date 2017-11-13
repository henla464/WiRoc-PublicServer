<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(
 * 	definition="NewSubDevice", 
 * 	type="object", 
 * 	required={"distanceToHead", "headBTAddress"}
 * )
 */
class SubDevice
{
	public static $tableName = 'SubDevices';
	
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $headBTAddress;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $distanceToHead;
    
    public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="SubDevice",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewSubDevice"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */
 
 
 /**
 *  @SWG\Definition(
 *   definition="NewSubDevice2",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(
 *           required={"distanceToHead"},
 *           @SWG\Property(property="distanceToHead", format="int64", type="integer")
 *       )
 *   }
 * )
 */

