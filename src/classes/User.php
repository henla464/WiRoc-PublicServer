<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewUser", type="object", required={"oauthProvider", "oauthUserId", "email"})
 */
class User
{
	public static $tableName = 'Users';
	
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $oauthProvider;
    /**
     * @var string
     * @SWG\Property()
     */
    public $oauthUserId;
    /**
     * @var string
     * @SWG\Property()
     */
    public $email;
    
	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="User",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewUser"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

