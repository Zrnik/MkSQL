<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Mock\BaseRepositoryAndBaseEntity\Entities\AutoHydrateAndCircularReference;

use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('AutoHydrateAndCircularReference_test_ReferencingEntityTwo')]
class ReferencingEntityTwo extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ForeignKey(ReferencingEntityOne::class)]
    public ?ReferencingEntityOne $referencingEntityOne;

}
