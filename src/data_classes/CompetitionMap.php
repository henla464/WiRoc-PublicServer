<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewCompetitionMap", type="object", required={"competitionId"})
 */
class CompetitionMap
{
    public static $tableName = 'CompetitionMaps';

    public $id;
    /**
     * @SWG\Property()
     * @var int
     */
    public $competitionId;
    /**
     * @SWG\Property()
     * @var string
     */
    public $originalFileName;
    /**
     * @SWG\Property()
     * @var string
     */
    public $storedFileName;
    /**
     * @SWG\Property()
     * @var string
     */
    public $fileType;
    /**
     * @SWG\Property()
     * @var int
     */
    public $defaultZoom;
    /**
     * @SWG\Property()
     * @var float
     */
    public $defaultCenterX;
    /**
     * @SWG\Property()
     * @var float
     */
    public $defaultCenterY;
    /**
     * @SWG\Property()
     * @var float
     */
    public $mapScale;
    /**
     * @SWG\Property()
     * @var int
     */
    public $mapScaleRatio;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP1X;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP1Y;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP1Lat;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP1Lng;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP2X;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP2Y;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP2Lat;
    /**
     * @SWG\Property()
     * @var float
     */
    public $georefP2Lng;

    public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="CompetitionMap",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewCompetitionMap"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 *           @SWG\Property(property="originalFileName", type="string"),
 *           @SWG\Property(property="fileType", type="string"),
 *           @SWG\Property(property="defaultZoom", type="integer"),
 *           @SWG\Property(property="defaultCenterX", type="number"),
 *           @SWG\Property(property="defaultCenterY", type="number"),
 *           @SWG\Property(property="updateTime", format="date-time", type="string"),
 *           @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="CompetitionMapViewState",
 *   type="object",
 *   required={"zoom", "centerX", "centerY"},
 *   @SWG\Property(property="zoom", type="integer"),
 *   @SWG\Property(property="centerX", type="number"),
 *   @SWG\Property(property="centerY", type="number")
 * )
 */
