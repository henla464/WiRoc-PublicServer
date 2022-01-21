<?php
use Swagger\Annotations as SWG;
/**
 * @SWG\Definition(required={"code", "message"}, type="object")
 */
class CommandResponse
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
}
