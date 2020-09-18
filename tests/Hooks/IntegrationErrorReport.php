<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 02.09.2020 15:16
 */

namespace Hooks;


use PHPUnit\Runner\AfterLastTestHook;
use Zrnik\MkSQL\Tracy\Metrics;

final class IntegrationErrorReport implements AfterLastTestHook
{
    public function executeAfterLastTest(): void
    {
        $isError = false;

        $errors = [];

        foreach (Metrics::getQueries() as $query) {
            $errors[] = $query;
            if ($query->errorText !== null) {
                $isError = true;
                break;
            }
        }

        if (count(Metrics::getQueries()) > 0) {

            if ($isError) {
                echo PHP_EOL . "-----------------------------------" . PHP_EOL;
                echo "--- Errors in QUERY EXECUTING!  ---" . PHP_EOL;
                echo "-----------------------------------" . PHP_EOL;
                echo PHP_EOL;
                foreach ($errors as $error) {
                    echo "Query: " . $error->getQuery() . PHP_EOL;
                    echo "Reason: " . $error->getReason() . PHP_EOL;
                    if ($error->errorText !== null) {
                        echo PHP_EOL;
                        echo " - " . $error->errorText;
                    }
                    echo PHP_EOL;
                    //echo PHP_EOL;
                }
                echo "-----------------------------------" . PHP_EOL;
                echo "-----------------------------------" . PHP_EOL;
                echo "-----------------------------------" . PHP_EOL;
            } else {
                echo PHP_EOL . "-----------------------------------" . PHP_EOL;
                echo "-------- EXECUTED QUERIES !  " . PHP_EOL;
                foreach (Metrics::getQueries() as $query) {
                    echo "Query: " . $query->getQuery();
                    echo PHP_EOL;
                    echo " - " . $query->getReason();
                    echo PHP_EOL;
                    echo PHP_EOL;
                }
                echo "-----------------------------------" . PHP_EOL;
                echo "-----------------------------------" . PHP_EOL;
            }
        }
    }
}
