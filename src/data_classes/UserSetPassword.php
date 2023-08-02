<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="UserSetPassword", type="object", required={"recoveryGuid", "password"})
 */
class UserSetPassword
{
	public static $tableName = 'Users';
	
    /**
     * @var string
     * @SWG\Property()
     */
    public $recoveryGuid;
    /**
     * @var string
     * @SWG\Property()
     */
    public $password;
}
