<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewWiRocPython2Release", type="object", required={"releaseName", "versionNumber", "releaseStatusId", "minHWVersion", "minHWRevision", "maxHWVersion", "maxHWRevision", "releaseNote", "md5HashOfReleaseFile"})
 */
class WiRocPython2Release
{
	public static $tableName = 'WiRocPython2Releases';
	
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $releaseName;
    /**
     * @SWG\Property()
     * @var int
     */
    public $versionNumber;
    /**
     * @SWG\Property()
     * @var int
     */
    public $releaseStatusId;
    /**
     * @SWG\Property()
     * @var int
     */
    public $minHWVersion;
    /**
     * @SWG\Property()
     * @var int
     */
    public $minHWRevision;
    /**
     * @SWG\Property()
     * @var int
     */
    public $maxHWVersion;
    /**
     * @SWG\Property()
     * @var int
     */
    public $maxHWRevision;
    /**
     * @SWG\Property()
     * @var string
     */
    public $releaseNote;
    /**
     * @SWG\Property()
     * @var string
     */
    public $md5HashOfReleaseFile;
    

	public $updateTime;
    public $createdTime;
    public $releaseStatusDisplayName;
}

/**
 *  @SWG\Definition(
 *   definition="WiRocPython2Release",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewWiRocPython2Release"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="releaseStatusDisplayName", type="string")
 *       )
 *   }
 * )
 */

