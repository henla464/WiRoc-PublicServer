<?php

/**
 * @SWG\Definition(definition="NewSubDevice", type="object", required={"distanceToHead", "headBTAddress"})
 */
class SubDevice
{
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
}

/**
 *  @SWG\Definition(
 *   definition="SubDevice",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewSubDevice"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="int64", type="integer")
 *       )
 *   }
 * )
 */

