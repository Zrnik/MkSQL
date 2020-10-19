<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 02.09.2020 15:16
 */

namespace Hooks;


use PHPUnit\Runner\AfterLastTestHook;
use Zrnik\MkSQL\Tracy\Measure;

final class IntegrationErrorReport implements AfterLastTestHook
{
    public function executeAfterLastTest(): void
    {
        $isError = false;

        foreach (Measure::getQueryModification() as $query) {
            if ($query->errorText !== null) {
                $isError = true;
                break;
            }
        }

        if (count(Measure::getQueryModification()) > 0) {
            echo PHP_EOL.PHP_EOL;
            if(!$isError)
            {
                echo 'MkSQL Report: Executed '.count(Measure::getQueryModification())." queries without error.";
            }
            else
            {
                echo 'MkSQL Report: ERRORS! See details below:';
                echo PHP_EOL.PHP_EOL;
                foreach (Measure::getQueryModification() as $query) {
                    if($query->errorText === null)
                        continue;

                    echo "Query: " . $query->getQuery();
                    echo PHP_EOL;
                    echo " - " . $query->getReason();
                    echo PHP_EOL;
                    echo "ERROR: " . $query->errorText;
                    echo PHP_EOL;
                    echo PHP_EOL;
                }
            }
        }
    }
}
