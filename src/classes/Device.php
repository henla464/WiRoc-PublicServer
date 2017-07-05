<?php

/**
 * @SWG\Definition(definition="NewDevice", type="object", required={"name", "BTAddress"})
 */
class Device
{
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
}

/**
 *  @SWG\Definition(
 *   definition="Device",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewDevice"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="int64", type="integer")
 *       )
 *   }
 * )
 */

