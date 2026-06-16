<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDeviceAccess", type="object", required={"BTAddress", "UserId"})
 */
class DeviceAccess
{
	public static $tableName = 'DeviceAccesses';

    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $BTAddress;
    /**
     * @SWG\Property()
     * @var int
     */
    public $UserId;
    public $GrantedAt;
    /**
     * @SWG\Property()
     * @var int
     */
    public $GrantedByUserId;

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="DeviceAccess",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewDeviceAccess"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 *           @SWG\Property(property="GrantedAt", format="date-time", type="string"),
 *           @SWG\Property(property="GrantedByUserId", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="DeviceAccessGrantRequest",
 *   type="object",
 *   required={"UserEmail", "UserPassword", "BTAddress"},
 *   @SWG\Property(property="UserEmail", type="string"),
 *   @SWG\Property(property="UserPassword", type="string"),
 *   @SWG\Property(property="BTAddress", type="string")
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="DeviceAccessRevokeRequest",
 *   type="object",
 *   required={"BTAddress", "UserId"},
 *   @SWG\Property(property="BTAddress", type="string"),
 *   @SWG\Property(property="UserId", type="integer")
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="DeviceAccessGrantWebRequest",
 *   type="object",
 *   required={"UserEmail", "BTAddress"},
 *   @SWG\Property(property="UserEmail", type="string"),
 *   @SWG\Property(property="BTAddress", type="string")
 * )
 */
