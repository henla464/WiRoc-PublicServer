<?php

/**
 * @SWG\Definition(definition="NewOutputMessageStat", type="object", required={"deviceId", "adapterInstance", "messageType", "status", "noOfMessages"})
 */
class InputMessageStat
{
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
     * @var string
     * @SWG\Property()
     */
    public $status;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $noOfMessages;
    /**
     * @var dateTime
     * @SWG\Property()
     */
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="OutputMessageStat",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewOutputMessageStat"),
 *       @SWG\Schema(
 *           required={"id", "deviceId", "adapterInstance", "messageType", "status", "noOfMessages", "createdTime"},
 *           @SWG\Property(property="id", format="int64", type="integer")
 *       )
 *   }
 * )
 */

