<?php

/**
 * @SWG\Definition(definition="NewInputMessageStat", type="object", required={"deviceId", "adapterInstance", "messageType", "noOfMessages"})
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
 *   definition="InputMessageStat",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewInputMessageStat"),
 *       @SWG\Schema(
 *           required={"id", "deviceId", "adapterInstance", "messageType", "noOfMessages", "createdTime"},
 *           @SWG\Property(property="id", format="int64", type="integer")
 *       )
 *   }
 * )
 */

