<?php declare(strict_types=1);

namespace Tests\Mock\Bugs\TreeSave;

use Zrnik\MkSQL\Repository\Attributes\ColumnType;
use Zrnik\MkSQL\Repository\Attributes\ForeignKey;
use Zrnik\MkSQL\Repository\Attributes\PrimaryKey;
use Zrnik\MkSQL\Repository\Attributes\TableName;
use Zrnik\MkSQL\Repository\BaseEntity;

#[TableName('TreeSave_RepeatingNode')]
class RepeatingNode extends BaseEntity
{
    #[PrimaryKey]
    public ?int $id = null;

    #[ColumnType('varchar(255)')]
    public string $name = '';

    #[ForeignKey(RepeatingNode::class)]
    public ?RepeatingNode $parent = null;
}
