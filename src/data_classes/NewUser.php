<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewNewUser", type="object", required={"email", "salt", "hashedPassword"})
 */
class NewUser
{
	public static $tableName = 'Users';
	
    /**
     * @var string
     * @SWG\Property()
     */
    public $email;
    /**
     * @var string
     * @SWG\Property()
     */
    public $password;
}

