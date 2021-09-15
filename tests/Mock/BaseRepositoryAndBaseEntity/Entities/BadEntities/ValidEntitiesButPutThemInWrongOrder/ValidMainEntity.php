<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\ValidEntitiesButPutThemInWrongOrder;

use Zrnik\MkSQL\Repository\Attributes\FetchArray;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('ValidEntitiesButPutThemInWrongOrder_ValidMainEntity')]
class ValidMainEntity extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    /**
     * @var ValidSubEntity[]
     */
    #[FetchArray(ValidSubEntity::class)]
    public array $subEntities;


}
