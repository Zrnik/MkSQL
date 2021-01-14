<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 06.08.2020 7:45
 */


namespace Zrnik\MkSQL\Queries\Makers;

use Nette\Utils\Strings;
use PDO;
use PDOException;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Queries\Query;
use Zrnik\MkSQL\Queries\QueryInfo;
use Zrnik\MkSQL\Queries\Tables\ColumnDescription;
use Zrnik\MkSQL\Queries\Tables\TableDescription;
use Zrnik\MkSQL\Table;
use Zrnik\MkSQL\Tracy\Measure;
use Zrnik\MkSQL\Utils;

class QueryMakerMySQL implements IQueryMaker
{
    /**
     * @inheritDoc
     */
    public static function describeTable(PDO $pdo, Table $table): ?TableDescription
    {
        $QueryInfo = new QueryInfo;
        $QueryInfo->executionSpeed = microtime(true);
        $QueryInfo->referencedTable = $table;

        $Desc = new TableDescription();
        $Desc->queryMakerClass = __CLASS__;
        $Desc->table = $table;

        try {
            $QueryInfo->querySql = "SHOW CREATE TABLE " . $table->getName();
            $Statement = $pdo->prepare($QueryInfo->querySql);

            $QueryInfo->isExecuted = true;
            $Statement->execute();

            $result = $Statement->fetch(PDO::FETCH_ASSOC)["Create Table"];
            $QueryInfo->isSuccess = true;

            $Desc->tableExists = true;

            $DescriptionData = explode("\n", $result);

            //Find Primary Key Name:
            foreach ($DescriptionData as $DescriptionRow) {
                if (Strings::contains($DescriptionRow, "PRIMARY KEY")) {
                    [$trash, $keyPart] = explode("(", $DescriptionRow);
                    unset($trash); // Make PhpStorm stop screaming...
                    [$keyPart] = explode(")", $keyPart);
                    $keyPart = Strings::normalize(str_replace("`", "", $keyPart));
                    $Desc->primaryKeyName = $keyPart;
                }
            }

            foreach ($table->columnList() as $column) {
                $ColDesc = new ColumnDescription();
                $ColDesc->table = $table;
                $ColDesc->column = $column;

                /** @var string|null $col_desc */
                $col_desc = null;
                $unique_index_key = null;
                $foreign_keys = [];
                //region Find Column Descriptions
                foreach ($DescriptionData as $DescriptionLine) {
                    $DescriptionLine = trim($DescriptionLine);

                    if (Strings::startsWith($DescriptionLine, "`" . $column->getName() . "`"))
                        $col_desc = $DescriptionLine;

                    if (
                        Strings::startsWith($DescriptionLine, "UNIQUE KEY") &&
                        (
                            Strings::endsWith($DescriptionLine, "(`" . $column->getName() . "`)")
                            ||
                            Strings::endsWith($DescriptionLine, "(`" . $column->getName() . "`),")
                        )
                    ) {
                        $unique_index_key = explode("`", $DescriptionLine)[1];
                    }

                    if (
                        Strings::startsWith($DescriptionLine, "CONSTRAINT ") &&
                        Strings::contains($DescriptionLine, "FOREIGN KEY")
                    ) {
                        $fkData = explode("`", $DescriptionLine);
                        if ($fkData[3] === $column->getName())
                            $foreign_keys[$fkData[5] . "." . $fkData[7]] = $fkData[1];
                    }
                }
                //endregion

                if ($col_desc === null) {
                    $ColDesc->columnExists = false;
                } else {
                    $ColDesc->columnExists = true;

                    //Need:
                    // - Type
                    $ColDesc->type = Strings::trim(explode(" ", $col_desc)[1],",");

                    // - NOT NULL
                    $ColDesc->notNull = strpos($col_desc, "NOT NULL") !== false;
                    // - Unique
                    $ColDesc->uniqueIndex = $unique_index_key;

                    // - Default Value
                    if (Strings::contains($col_desc, "DEFAULT '")) {
                        list($_trash, $default_part) = explode("DEFAULT '", $col_desc);
                        unset($_trash);
                        $RestOfDefault = explode("'", $default_part);

                        $Index = 0;
                        $DefaultValue = $RestOfDefault[$Index];

                        //it ends with backslash, that means it was real apostrophe that was escaped!
                        while (Strings::endsWith($DefaultValue, "\\") && isset($RestOfDefault[$Index + 1])) {
                            $Index++;
                            $DefaultValue .= "'" . $RestOfDefault[$Index];
                        }

                        $ColDesc->default = $DefaultValue;
                    } else {
                        $ColDesc->default = null;
                    }

                    // - Foreign Keys
                    $ColDesc->foreignKeys = $foreign_keys;

                    // - Comment
                    if (Strings::contains($col_desc, "COMMENT '")) {
                        list($_throwAway, $comment_part) = explode("COMMENT '", $col_desc);
                        $RestOfComments = explode("'", $comment_part);

                        $Index = 0;
                        $Comment = $RestOfComments[$Index];

                        unset($_throwAway);

                        //it ends with backslash, that means it was real apostrophe that was escaped!
                        while (Strings::endsWith($Comment, "\\") && isset($RestOfComments[$Index + 1])) {
                            $Index++;
                            $Comment .= "'" . $RestOfComments[$Index];
                        }

                        $ColDesc->comment = $Comment;
                    } else {
                        $ColDesc->comment = null;
                    }
                }
                $Desc->columns[] = $ColDesc;
            }


        } catch (PDOException $pdoEx) {
            $QueryInfo->isSuccess = false;
            $Desc->tableExists = false;
        }

        $QueryInfo->executionSpeed = microtime(true) - $QueryInfo->executionSpeed;
        Measure::reportQueryDescription($QueryInfo);

        return $Desc;
    }

    /**
     * @param string $oldKey
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     */
    public static function changePrimaryKeyQuery(string $oldKey, Table $table, ?TableDescription $oldTableDescription): ?array
    {
        return [
            (new Query($table, null))
                ->setQuery("ALTER TABLE " . $table->getName() . " CHANGE " . $oldKey . " " . $table->getPrimaryKeyName() . " int NOT NULL AUTO_INCREMENT;")
                ->setReason("Primary key '" . $table->getPrimaryKeyName() . "' required but '" . $oldKey . "' found.")
        ];
    }

    /**
     * @param Table $table
     * @param TableDescription|null $oldTableDescription
     * @return Query[]|null
     */
    public static function createTableQuery(Table $table, ?TableDescription $oldTableDescription): ?array
    {
        $CreateTableQuery = (new Query($table, null))
            ->setQuery(
                "CREATE TABLE " .
                $table->getName()
                . " (" . $table->getPrimaryKeyName() . " int NOT NULL AUTO_INCREMENT PRIMARY KEY)"
            )
            ->setReason("Table '" . $table->getName() . "' not found.");

        return [
            $CreateTableQuery
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function alterTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        $alterTableQuery = new Query($table, $column);

        $ModifySQL = 'ALTER TABLE ' . $table->getName() . ' MODIFY ' . $column->getName() . ' ' . $column->getType();

        if ($column->getDefault() !== null)
            $ModifySQL .= " DEFAULT '" . $column->getDefault() . "'";

        $ModifySQL .= ($column->getNotNull() ? ' NOT ' : ' ') . 'NULL';

        if ($column->getComment() !== null && $column->getComment() !== "")
            $ModifySQL .= " COMMENT '" . $column->getComment() . "'";

        $alterTableQuery->setReason("to-be-determined");

        $alterTableQuery->setQuery(trim($ModifySQL));

        return [
            $alterTableQuery
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createTableColumnQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        $CreateSQL = 'ALTER TABLE ' . $table->getName() . ' ';
        $CreateSQL .= 'ADD ' . $column->getName() . ' ' . $column->getType() . ' ';

        //Default Value
        if ($column->getDefault() !== null)
            $CreateSQL .= 'DEFAULT \'' . $column->getDefault() . '\' ';

        //Null/NotNull
        $CreateSQL .= ($column->getNotNull() ? "NOT " : '') . 'NULL ';

        //Comment
        if ($column->getComment() !== null)
            $CreateSQL .= "COMMENT '" . $column->getComment() . "' ";

        return [
            (new Query($table, $column))
                ->setQuery(trim($CreateSQL))
                ->setReason("Column '" . $table->getName() . "." . $column->getName() . "' not found.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]|null
     */
    public static function createUniqueIndexQuery(Table $table, Column $column, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        $MySQL_Key_MaxLength = 64;

        $newKey = Utils::confirmKeyName(
            "unique_index_" . $table->getName() . "_" . $column->getName(),
            $MySQL_Key_MaxLength
        );

        return [
            (new Query($table, $column))
                ->setQuery('CREATE UNIQUE INDEX ' . $newKey . ' on ' . $table->getName() . ' (' . $column->getName() . ');')
                ->setReason("Unique index for '" . $table->getName() . "." . $column->getName() . "' not found.")

        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $uniqueIndex
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return array<mixed>|null
     */
    public static function removeUniqueIndexQuery(Table $table, Column $column, string $uniqueIndex, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): ?array
    {
        //Remove Foreign Keys and then add them back after the index was removed!
        $Queries = [];

        //var_dump($columnDescription->foreignKeys);
        foreach ($columnDescription?->foreignKeys as $foreignKeyTarget => $foreignKeyName) {
            $DropQueries = static::removeForeignKey($table, $column, $foreignKeyName, $oldTableDescription, $columnDescription);
            foreach ($DropQueries as $DropQuery)
                $DropQuery->setReason("Invoked by 'removeUniqueIndexQuery[" . $uniqueIndex . "]'" . PHP_EOL . $DropQuery->getReason());

            $Queries = array_merge($Queries, $DropQueries);
        }

        $Queries[] = (new Query($table, $column))
            ->setQuery('DROP INDEX ' . $uniqueIndex . ' ON ' . $table->getName() . ';')
            ->setReason("There is unexpected unique index '" . $uniqueIndex . "' on '"
                . $table->getName() . "." . $column->getName() . "'.");

        foreach ($columnDescription?->foreignKeys as $foreignKeyTarget => $foreignKeyName) {
            $CreateQueries = static::createForeignKey($table, $column, $foreignKeyTarget, $oldTableDescription, $columnDescription);

            foreach ($CreateQueries as $CreateQuery)
                $CreateQuery->setReason("Invoked by 'removeUniqueIndexQuery[" . $uniqueIndex . "]'" . PHP_EOL . $CreateQuery->getReason());

            $Queries = array_merge($Queries, $CreateQueries??[]);
        }

        return $Queries;
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $ForeignKeyName
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]
     */
    public static function removeForeignKey(Table $table, Column $column, string $ForeignKeyName, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): array
    {
        return [
            (new Query($table, $column))
                ->setQuery("ALTER TABLE " . $table->getName() . " DROP FOREIGN KEY " . $ForeignKeyName . ";")
                ->setReason("Unexpected foreign key '" . $ForeignKeyName . "' found for column '" . $column->getName() . "'.")
        ];
    }

    /**
     * @param Table $table
     * @param Column $column
     * @param string $RefPointerString
     * @param TableDescription|null $oldTableDescription
     * @param ColumnDescription|null $columnDescription
     * @return Query[]
     */
    public static function createForeignKey(Table $table, Column $column, string $RefPointerString, ?TableDescription $oldTableDescription, ?ColumnDescription $columnDescription): array
    {
        $pointerString = Utils::confirmForeignKeyTarget($RefPointerString);
        // $pointerString has exactly one dot or the exception was already thrown!

        $foreignKeyParts = explode(".", $pointerString);
        $targetTable = $foreignKeyParts[0];
        $targetColumn = $foreignKeyParts[1];

        $foreignKeyName = Utils::confirmKeyName("f_key_" . $table->getName() . '_' . $targetTable . '_' . $column->getName() . '_' . $targetColumn);

        return [
            (new Query($table, $column))
                ->setQuery('ALTER TABLE ' . $table->getName() . ' ADD CONSTRAINT ' . $foreignKeyName . '
                             FOREIGN KEY (' . $column->getName() . ') REFERENCES ' . $targetTable . ' (' . $targetColumn . ');')
                ->setReason("Foreign key on '" . $table->getName() . "." . $column->getName() . "' referencing '" . $RefPointerString . "' not found.")
        ];
    }

    /**
     * @param string $type1
     * @param string $type2
     * @return bool
     */
    public static function compareType(string $type1, string $type2): bool
    {
        $type1 = Utils::confirmType(strtolower($type1));
        $type2 = Utils::confirmType(strtolower($type2));

        $Exceptions = [
            "tinyint", "mediumint", "int", "bigint",
        ];

        foreach ($Exceptions as $Exception)
            if (Strings::startsWith($type1, $Exception) && Strings::startsWith($type2, $Exception))
                return true;

        return $type1 === $type2;
    }

    /**
     * @param string|null $type1
     * @param string|null $type2
     * @return bool
     */
    public static function compareComment(?string $type1, ?string $type2): bool
    {
        return $type1 === $type2;
    }
}
