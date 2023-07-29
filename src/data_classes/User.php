<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewUser", type="object", required={"email", "hashedPassword"})
 */
class User
{
	public static $tableName = 'Users';
	
    public $id;
    /**
     * @var string
     * @SWG\Property()
     */
    public $email;
    /**
     * @var string
     * @SWG\Property()
     */
    public $hashedPassword;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public $isAdmin;
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
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

