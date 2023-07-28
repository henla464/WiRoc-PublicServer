<?php
use Swagger\Annotations as SWG;
/**
 * @SWG\Definition(required={"code", "message", "isLoggedIn", "isAdmin"}, type="object")
 */
class LoginResponse
{
    /**
     * @SWG\Property(format="int32");
     * @var int
     */
    public $code;
    /**
     * @SWG\Property();
     * @var string
     */
    public $message;
    /**
     * @SWG\Property();
     * @var boolean
     */
    public $isLoggedIn;
    /**
     * @SWG\Property();
     * @var boolean
     */
    public $isAdmin;
}
