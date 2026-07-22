<?php

namespace html2img\ogimages\records;

use craft\db\ActiveRecord;

/**
 * One generated image per entry per site.
 *
 * @property int $id
 * @property int $elementId
 * @property int $siteId
 * @property string|null $url
 * @property int|null $assetId
 * @property string|null $inputHash
 * @property int|null $width
 * @property int|null $height
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ImageRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ogimages}}';
    }
}
