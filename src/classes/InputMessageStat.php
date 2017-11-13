<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewInputMessageStat", type="object", required={"deviceId", "adapterInstance", "messageType", "noOfMessages"})
 */
class InputMessageStat
{
	public static $tableName = 'InputMessageStat';
	
    public $id;
    /**
     * @SWG\Property()
     * @var integer
     */
    public $deviceId;
    /**
     * @var string
     * @SWG\Property()
     */
    public $adapterInstance;
    /**
     * @var string
     * @SWG\Property()
     */
    public $messageType;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $noOfMessages;

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="InputMessageStat",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewInputMessageStat"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

