<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewMessageStat", type="object", required={"BTAddress", "adapterInstance", "messageType", "status", "noOfMessages"})
 */
class MessageStat
{
	public static $tableName = 'MessageStats';
		
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
    public $adapterInstance;
    /**
     * @var string
     * @SWG\Property()
     */
    public $messageType;
    /**
     * @var string
     * @SWG\Property()
     */
    public $status;
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
 *   definition="MessageStat",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewMessageStat"),
 *       @SWG\Schema(
 *           required={"id", "BTAddress", "adapterInstance", "messageType", "status", "noOfMessages", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

