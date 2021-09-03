<?php declare(strict_types=1);

/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 02.09.2020 15:16
 */

namespace Hooks;


use PHPUnit\Runner\AfterLastTestHook;
use RuntimeException;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Tracy\Measure;

final class IntegrationErrorReport implements AfterLastTestHook
{
    public static string $reportFile = __DIR__ . '/../../temp/mksqlErrorReport.txt';

    private static function prepareDirectory(string $dirname): void
    {
        if (!file_exists($dirname)) {
            if (!mkdir($dirname, 0777, true) && !is_dir($dirname)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dirname));
            }
        }
    }

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
            echo PHP_EOL . PHP_EOL;
            if (!$isError) {
                echo 'MkSQL Report: Executed ' . count(Measure::getQueryModification()) . " queries without error.";
            } else {
                echo 'MkSQL Report: ERRORS! See details below:';
                echo PHP_EOL . PHP_EOL;
                foreach (Measure::getQueryModification() as $query) {

                    if ($query->errorText === null) {
                        continue;
                    }

                    echo "Query: " . $query->getQuery();
                    echo PHP_EOL;
                    echo " - " . $query->getReason();
                    echo PHP_EOL;
                    echo "ERROR: " . $query->errorText;
                    echo PHP_EOL;
                    echo PHP_EOL;
                }


                $fullReportContent = '';


                foreach (Measure::getQueryModification() as $query) {
                    $fullReportContent .= "------------------------------\n";
                    $fullReportContent .= "Query[".DriverType::getName($query->getDriver())."]: " . $query->getQuery() . "\n";
                    $fullReportContent .= "Reason: " . $query->getReason() . "\n";
                    $fullReportContent .= "Executed: " . ($query->executed ? "yes" : "no") . "\n";
                    $fullReportContent .= "ErrorText: " . ($query->errorText ?? 'null') . "\n";
                }

                self::prepareDirectory(dirname(self::$reportFile));
                file_put_contents(self::$reportFile, $fullReportContent);
                echo 'Full report in: ' . realpath(self::$reportFile);

            }
        }
    }
}
