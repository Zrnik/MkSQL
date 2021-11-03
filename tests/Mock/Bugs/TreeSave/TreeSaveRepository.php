<?php declare(strict_types=1);

namespace Tests\Mock\Bugs\TreeSave;

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;
use function count;
use function in_array;

class TreeSaveRepository extends Installable
{


    protected function install(Updater $updater): void
    {
        $updater->use(RepeatingNode::class);
    }

    public function dropAll(): void
    {

        while (count($this->getAll(RepeatingNode::class)) > 0) {

            $dropIds = [];

            $distinctPrimary = $this->distinctValues(RepeatingNode::class, 'id');
            $distinctParent = $this->distinctValues(RepeatingNode::class, 'parent');

            foreach ($distinctPrimary as $key) {
                if (!in_array($key, $distinctParent)) {
                    $dropIds[] = $key;
                }
            }

            if (count($dropIds) > 0) {
                $statement = $this->pdo->query(
                    sprintf(
                        'DELETE FROM ' . RepeatingNode::getTableName() .
                        ' WHERE id IN (%s)',
                        implode(', ', $dropIds)
                    )

                );

                if ($statement !== false) {
                    $statement->execute();
                }
            }
        }

        //


    }
}
