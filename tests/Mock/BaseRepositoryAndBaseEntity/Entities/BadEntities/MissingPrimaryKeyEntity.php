<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities;

use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('MissingPrimaryKeyEntity')]
class MissingPrimaryKeyEntity extends BaseEntity
{

}
