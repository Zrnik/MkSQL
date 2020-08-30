<?php declare(strict_types=1);
/*
 * Zrník.eu | ZrnyWeb  
 * User: Programátor
 * Date: 29.08.2020 13:09
 */


namespace Zrny\MkSQL\Enum;


class KeyType extends \Zrny\Base\Enum
{
    const PrimaryKey = 'prim';
    const ForeignKey = 'fore';
    const UniqueIndexKey = 'uniq';
}