<?php
/*
 * Zrník.eu | MkSQL
 * User: Programátor
 * Date: 04.08.2020 9:42
 */

namespace Zrnik\MkSQL\Tracy;

use Exception;
use Nette\Utils\Html;
use Tracy\Debugger;
use Tracy\IBarPanel;
use Zrnik\MkSQL\Column;
use Zrnik\MkSQL\Enum\DriverType;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Table;

/**
 * This is still a mess...
 *
 * @package Zrnik\MkSQL\Nette
 */
class Panel implements IBarPanel
{

    //region Styles
    const RESULT_UP2DATE = 0;
    const RESULT_CHANGES = 1;
    const RESULT_ERROR = 2;
    const RESULT_UNINITIALIZED = 3;

    const ICON = 0;
    const COLOR = 1;

    /**
     * @param int $style
     * @return array<mixed>
     */
    private function getIconStyle(int $style): array
    {
        $_IconStyle = [
            self::RESULT_UP2DATE => [
                self::ICON => $this->loadSvg("database-checkmark.svg"),
                self::COLOR => 'darkgreen'
            ],

            self::RESULT_CHANGES => [
                self::ICON => $this->loadSvg("database-error.svg"),
                self::COLOR => 'orange'
            ],

            self::RESULT_ERROR => [
                self::ICON => $this->loadSvg("database-error.svg"),
                self::COLOR => 'red'
            ],

            self::RESULT_UNINITIALIZED => [
                self::ICON => $this->loadSvg("database-checkmark.svg"),
                self::COLOR => 'navy'
            ],
        ];

        if (!isset($_IconStyle[$style]))
            throw new InvalidArgumentException("Invalid icon style!");

        return $_IconStyle[$style];
    }

    /**
     * @var array<mixed>
     */
    private static array $_svgCache = [];

    /**
     * @param string $svgName
     * @return string
     * @throws Exception
     */
    private function loadSvg(string $svgName): string
    {
        if (isset(static::$_svgCache[$svgName]))
            return static::$_svgCache[$svgName];

        $assetsFolder = __DIR__ . '/../../assets/';

        $imageContent = @file_get_contents($assetsFolder . $svgName);

        if($imageContent === false)
            throw new Exception(
                sprintf(
                    "Icon file '%s' was not found, if you are using 'vendor'".
                    " directory cleaner, please add an exception to MkSQL package.",
                    $assetsFolder . $svgName
                )
            );

        static::$_svgCache[$svgName] = $imageContent;

        return static::$_svgCache[$svgName];
    }

    //endregion

    //region DataDig
    const HAS_ERROR = 0;
    const HAS_CHANGES = 1;

    /**
     * @return array<mixed>
     */
    public function getResult(): array
    {
        $queries = Measure::getQueryModification();

        $hasChanges = count($queries) > 0;
        $hasError = false;

        foreach ($queries as $query)
            if ($query->errorText !== null)
                $hasError = true;

        return [
            self::HAS_ERROR => $hasError,
            self::HAS_CHANGES => $hasChanges
        ];
    }
    //endregion

    /**
     * @inheritDoc
     */
    function getTab(): ?string
    {
        $data = $this->getResult();

        $imgStyle = self::RESULT_UP2DATE;

        if ($data[self::HAS_CHANGES])
            $imgStyle = self::RESULT_CHANGES;

        if ($data[self::HAS_ERROR])
            $imgStyle = self::RESULT_ERROR;

        if (Measure::$Driver === null)
            $imgStyle = self::RESULT_UNINITIALIZED;

        $style = $this->getIconStyle($imgStyle);

        $PanelElement = Html::el("span", [
            "title" => "MkSQL Panel",
            "style" => "fill: " . $style[self::COLOR],
        ]);

        $PanelElement->addHtml($style[self::ICON]);
        $ContentElement = $PanelElement->addHtml(
            Html::el("span", ["class" => "tracy-label"])
        );

        if (Measure::$Driver !== null) {
            $ContentElement->addText(static::convertToMs(Measure::getTotalSpeed()));
            $ContentElement->addText(" ms");
            $ContentElement->addText(" / ");
            $ContentElement->addText(Measure::queryCountModification());
        }

        return $PanelElement->render();
    }

    /**
     * @param bool $success
     * @return Html<Html>
     */
    private function headerElement(bool $success = true): Html
    {
        $elem = Html::el("h1")
            ->style("width", "100%");
        $elem->addText("MkSQL");

        $topRight =  Html::el("span")
            ->style("float", "right")
            ->style("font-size", "12px")
            ->style("margin-right", "8px")
        ;

        if ($success) {
            $topRight
                ->addText(static::convertToMs(Measure::getTotalSpeed()))
                ->addText(" ms")
                ->addText(" / ")
                ->addText(Measure::queryCountModification());
        } else {
            $topRight->addText("uninitialized");
        }

        $elem->addHtml(
            $topRight
        );
        return $elem;
    }

    /**
     * @return Html<Html>
     */
    private function subPanelSpeed(): Html
    {

        return Html::el("table")
            /**
             * Row: Driver
             */
            ->addHtml(

                Html::el('tr')
                    ->addHtml(
                        Html::el('th')
                            ->setText("Driver:")
                    )
                    ->addHtml(
                        Html::el('td')
                            ->style("font-weight", "bold")
                            ->setText(
                                DriverType::getName(Measure::$Driver)
                            )
                    )
            )
            /**
             * Row: Speed
             */
            ->addHtml(

                Html::el('tr')
                    ->addHtml(
                        Html::el('th')
                            ->setText("Speed:")
                    )
                    ->addHtml(
                        Html::el('td')
                            ->style("font-weight", "bold")
                            ->setText(
                                static::convertToMs(Measure::getTotalSpeed(), 3) . ' ms'
                            )
                    )
            )
            /**
             * Row: Queries
             */
            ->addHtml(

                Html::el('tr')
                    ->addHtml(
                        Html::el('th')
                            ->setText("Queries:")
                    )
                    ->addHtml(
                        Html::el('td')
                            ->addHtml(
                                Html::el("b")->setText(Measure::queryCountDescription())
                            )
                            ->addText(" + ")
                            ->addHtml(
                                Html::el("b")->setText(Measure::queryCountModification())
                            )
                    )
            );
    }


    /**
     * @return Html<Html>
     */
    private function subPanelStructure(): Html
    {
        $tables = Measure::structureTableCount();
        $columns = Measure::structureColumnCount();

        $table = Html::el("table")
            ->addHtml(
                Html::el("tr")
                    ->addHtml(
                        Html::el("th")
                            ->setText("Table Name")
                    )
                    ->addHtml(
                        Html::el("th", ["style" => ["width" => "5%"]])
                            ->setText("Prim.")
                    )
                    ->addHtml(
                        Html::el("th", ["style" => ["width" => "5%"]])
                            ->setText("Cols")
                    )
                    ->addHtml(
                        Html::el("th", ["style" => ["width" => "5%"]])
                            ->setText("Calls")
                    )
                    ->addHtml(
                        Html::el("th")
                            ->setText("Speed")
                    )
                    ->addHtml(
                        Html::el("th")
                            ->setText("Details")
                    )

            );

        foreach (Measure::structureTableList() as $_tableDefinition) {
            /**
             * @var Table $tableObject
             */
            $tableObject = $_tableDefinition["objects"][0];

            $totalCalls = $_tableDefinition["calls"];
            $totalColumns = count(Measure::structureColumnList($tableObject->getName()??'unknown table'));

            $table->addHtml(Html::el("tr")
                ->addHtml(
                    Html::el("td")
                        ->setHtml(
                        //Security: Replace the tag for already escaped html element!
                            strval(
                                str_replace(
                                    "_",
                                    "&ZeroWidthSpace;_",
                                    Html::el()->setText($tableObject->getName()??'unknown table')
                                )
                            )
                        )
                )
                ->addHtml(
                    Html::el("td")
                        ->setText($tableObject->getPrimaryKeyName())
                )
                ->addHtml(
                    Html::el("td")
                        ->setText($totalColumns)
                )
                ->addHtml(
                    Html::el("td")
                        ->style("color", ($totalCalls > 1 ? "maroon" : "black"))
                        ->setText($totalCalls)
                        ->addText("x")
                )
                ->addHtml(
                    Html::el("td")
                        ->style("font-weight", "bold")
                        ->setText(
                            static::convertToMs(
                                Measure::getTableTotalSpeed($tableObject->getName()),
                                3
                            )
                        )
                        ->addText(" ms")
                )
                ->addHtml(
                    Html::el("td")
                        ->addHtml(
                            $this->createToggleHandle("table-detail-" . $tableObject->getName(), "Detail")
                        )
                )
            );

            $elementDetail = Html::el();


            //Speeds Detail:
            $elementDetail->addHtml(
                Html::el("table")
                    ->addHtml(
                        Html::el("tr")
                            ->addHtml(
                                Html::el("th")
                                    ->setText("Describe:")
                            )
                            ->addHtml(
                                Html::el("td")
                                    ->setText(
                                        static::convertToMs(
                                            Measure::getTableSpeed(
                                                $tableObject->getName()??'unknown table',
                                                Measure::TABLE_SPEED_DESCRIBE
                                            ),
                                            3
                                        )
                                    )
                                    ->addText(" ms")
                            )
                    )
                    ->addHtml(
                        Html::el("tr")
                            ->addHtml(
                                Html::el("th")
                                    ->setText("Generate:")
                            )
                            ->addHtml(
                                Html::el("td")
                                    ->setText(
                                        static::convertToMs(
                                            Measure::getTableSpeed(
                                                $tableObject->getName()??'unknown table',
                                                Measure::TABLE_SPEED_GENERATE
                                            ),
                                            3
                                        )
                                    )
                                    ->addText(" ms")
                            )
                    )
                    ->addHtml(
                        Html::el("tr")
                            ->addHtml(
                                Html::el("th")
                                    ->setText("Execute:")
                            )
                            ->addHtml(
                                Html::el("td")
                                    ->setText(
                                        static::convertToMs(
                                            Measure::getTableSpeed(
                                                $tableObject->getName()??'unknown table',
                                                Measure::TABLE_SPEED_EXECUTE
                                            ),
                                            3
                                        )
                                    )
                                    ->addText(" ms")
                            )
                    )
                    ->addHtml(
                        Html::el("tr")
                            ->addHtml(
                                Html::el("th")
                                    ->setText("Total:")
                            )
                            ->addHtml(
                                Html::el("td")
                                    ->setText(
                                        static::convertToMs(
                                            Measure::getTableTotalSpeed(
                                                $tableObject->getName()
                                            ),
                                            3
                                        )
                                    )
                                    ->addText(" ms")
                            )
                    )


            );

            //TODO: Make detail more readable...
            $detailDataTable = Html::el("table");

            $detailDataTable->addHtml(
                Html::el("tr")
                    ->addHtml(
                        Html::el("th", ["style" => "width: 25%;"])
                            ->setText("Col. Name")
                    )
                    ->addHtml(
                        Html::el("th", ["style" => "width: 15%;"])
                            ->setText("Type")
                    )
                    ->addHtml(
                        Html::el("th", ["style" => "width: 60%;"])
                    )
            );


            /** @var Column $columnObject */
            foreach (Measure::structureColumnList($tableObject->getName()??'unknown table') as $columnObject) {
                $detailDataTable->addHtml(
                    Html::el("tr", ["style" => ["background-color" => "rgba(0,200,255,0.1);"]])
                        ->addHtml(
                            Html::el("td")
                                ->setText($columnObject->getName())
                        )
                        ->addHtml(
                            Html::el("td")
                                ->setText($columnObject->getType())
                        )
                        ->addHtml(
                            Html::el("td")
                                ->addHtml(
                                    Html::el("table")
                                        ->addHtml(
                                            Html::el("tr")
                                                ->addHtml(
                                                    Html::el("th", ["style" => "width: 30%;"])
                                                        ->setText("Not Null")
                                                )
                                                ->addHtml(
                                                    Html::el("td", ["style" => "width: 70%;"])
                                                        ->addHtml(
                                                            static::yesNo($columnObject->getNotNull())
                                                        )
                                                )

                                        )
                                        ->addHtml(
                                            Html::el("tr")
                                                ->addHtml(
                                                    Html::el("th")
                                                        ->setText("Is Unique")
                                                )
                                                ->addHtml(
                                                    Html::el("td")
                                                        ->addHtml(
                                                            static::yesNo($columnObject->getUnique())
                                                        )
                                                )

                                        )
                                        ->addHtml(
                                            Html::el("tr")
                                                ->addHtml(
                                                    Html::el("th")
                                                        ->setText("Default Value")
                                                )
                                                ->addHtml(
                                                    Html::el("td")
                                                        ->addHtml(
                                                            (($columnObject->getDefault() === null) ? "Unset" : Html::el("b")->addText("\"")->addText($columnObject->getDefault())->addText("\""))
                                                        )
                                                )

                                        )
                                        ->addHtml(
                                            Html::el("tr")
                                                ->addHtml(
                                                    Html::el("th")
                                                        ->setText("Comment")
                                                )
                                                ->addHtml(
                                                    Html::el("td")
                                                        ->addHtml(
                                                            (($columnObject->getComment() === null) ? "Unset" : Html::el("b")->addText("\"")->addText($columnObject->getComment())->addText("\""))
                                                        )
                                                )

                                        )
                                        ->addHtml(
                                            Html::el("tr")
                                                ->addHtml(
                                                    Html::el("th")
                                                        ->setText("Foreign Keys")
                                                )
                                                ->addHtml(
                                                    Html::el("td")
                                                        ->addHtml(
                                                            (count($columnObject->getForeignKeys()) === 0 ? "None" : Debugger::dump($columnObject->getForeignKeys(), true))
                                                        )
                                                )

                                        )


                                )
                        )


                );

            }


            /*





                $PanelHtml .= '<tr>';
                $PanelHtml .= '<th>Foreign Keys</th>';
                $PanelHtml .= '<td>' .  . '</td>';
                $PanelHtml .= '</tr>';

*/


            $elementDetail->addHtml($detailDataTable);

            //Additional TR for the table stripes!
            $table->addHtml(Html::el("tr"));
            $table->addHtml(
                $this->createToggleContainer(
                    "table-detail-" . $tableObject->getName(),
                    "tr",
                    Html::el("td", ["colspan" => 6])
                        ->addHtml($elementDetail)
                )
            );

        }

        $table->addHtml(
            Html::el("tr")
                ->addHtml(
                    Html::el("td", ["colspan" => 4, "style" => ["text-align" => "right"]])
                        ->setText("Total:")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText(
                            static::convertToMs(
                                Measure::getTableTotalSpeed(),
                                3
                            )
                        )
                        ->addText(" ms")
                )
                ->addHtml(
                    Html::el("td")
                )
        );


        return $this->createToggle(
            "structure",
            Html::el("")
                ->addText("Structure")
                ->addText(" ")
                ->addText("(")
                ->addText($tables)
                ->addText(" ")
                ->addText("table" . static::s($tables))
                ->addText(", ")
                ->addText($columns)
                ->addText(" ")
                ->addText("column" . static::s($columns))
                ->addText(")")
            ,
            $table
        );
    }


    /**
     * @return Html<Html>
     */
    private function subPanelDescription(): Html
    {
        $descriptionQueryList = Measure::getQueryDescription();

        $table = Html::el("table");

        $table->addHtml(
            Html::el("tr")
                ->addHtml(
                    Html::el("th")
                        ->setText("#")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText("Query")
                )
                ->addHtml(
                    Html::el("th style=\"width: 15%;\"")
                        ->setText("Speed")
                )
        );

        $idx = 0;
        $execSpeedTotal = 0;
        foreach ($descriptionQueryList as $queryInfo) {
            $idx++; //It's intentional to start from 1

            $table->addHtml(
                Html::el("tr", ["style" => ["background-color" => ($queryInfo->isSuccess ? "rgba(0,255,0,0.1)" : "rgba(255,0,0,0.1)")]])
                    ->addHtml(
                        Html::el("td")
                            ->setText($idx)
                    )
                    ->addHtml(
                        Html::el("td")
                            ->setText($queryInfo->querySql)
                    )
                    ->addHtml(
                        Html::el("td")
                            ->setText(static::convertToMs($queryInfo->executionSpeed, 3))
                            ->addText(" ms")
                    )
            );

            $execSpeedTotal += $queryInfo->executionSpeed;

        }

        if (count($descriptionQueryList) === 0) {
            $table->addHtml(
                Html::el("tr", ["style" => ["background-color" => "rgba(0,150,0,0.1)"]])
                    ->addHtml(
                        Html::el("td", [
                            "colspan" => 3,
                            "style" => [
                                "color" => "darkgreen",
                                "font-weight" => "bold",
                                "text-align" => "center"
                            ]
                        ])
                            ->setText("No queries found!")
                    )

            );
        }


        $table->addHtml(
            Html::el("tr")
                ->addHtml(
                    Html::el("td colspan=2")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText(static::convertToMs($execSpeedTotal, 3))
                        ->addText(" ms")
                )
        );

        return $this->createToggle(
            "query-panel-description",
            Html::el("")
                ->addText("Description queries")
                ->addText(" ")
                ->addText("(")
                ->addText(
                    static::convertToMs(
                        Measure::querySpeedDescription()
                    )
                )
                ->addText(" ms")
                ->addText(" / ")
                ->addText(Measure::queryCountDescription())
                ->addText(")")
            ,
            $table
        );
    }

    /**
     * @return Html<Html>
     */
    private function subPanelModification(): Html
    {
        $modificationQueryList = Measure::getQueryModification();

        $table = Html::el("table");

        $table->addHtml(
            Html::el("tr")
                ->addHtml(
                    Html::el("th")
                        ->setText("Table")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText("Column")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText("Executed")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText("Error")
                )
                ->addHtml(
                    Html::el("th style=\"width: 20%;\"")
                        ->setText("Speed")
                )
        );

        $idx = 0;
        $execSpeedTotal = 0;
        foreach ($modificationQueryList as $query) {

            $textColor = 'black';
            $backgroundColor = 'rgba(0,0,0,0)';

            if ($query->executed) {

                if ($query->errorText !== null) {
                    //is Error
                    $textColor = 'maroon';
                    $backgroundColor = 'rgba(255,100,0,0.35)';
                } else {
                    //$textColor = 'darkgreen';
                    $backgroundColor = 'rgba(0,255,0,0.1)';
                }

            } else {
                $textColor = 'darkgray';
                $backgroundColor = 'rgba(0,0,0,0.6)';
            }


            $table->addHtml(
                Html::el("tr", [
                    "style" => [
                        "color" => $textColor,
                        "background-color" => $backgroundColor
                    ]
                ])
                    ->addHtml(
                        Html::el("td")
                            ->setText($query->getTable()->getName()??'unknown table')
                    )
                    ->addHtml(
                        Html::el("td")
                            ->setText(($query->getColumn() === null ? "-" : $query->getColumn()->getName()))
                    )
                    ->addHtml(
                        Html::el("td")
                            ->setHtml(
                                static::yesNo($query->executed, $query->executed)
                            )
                    )
                    ->addHtml(
                        Html::el("td")
                            ->setHtml(
                                static::yesNoInverted($query->errorText !== null, $query->executed)
                            )
                    )
                    ->addHtml(
                        Html::el("td")
                            ->setText(static::convertToMs($query->speed, 3))
                            ->addText(" ms")
                    )
            );


            $table->addHtml(
                Html::el("tr", [
                    "style" => [
                        "color" => $textColor,
                        "background-color" => $backgroundColor
                    ]
                ])
                    ->addHtml(
                        Html::el("td", ["colspan" => 5])
                            ->addHtml(
                                Html::el("b")
                                    ->setText($query->getQuery()
                                    )
                            )
                    )

            );


            $table->addHtml(
                Html::el("tr", [
                    "style" => [
                        "color" => $textColor,
                        "background-color" => $backgroundColor
                    ]
                ])
                    ->addHtml(
                        Html::el("td", ["colspan" => 5])
                            ->setText($query->getReason())
                    )

            );


            if ($query->errorText !== null) {

                $table->addHtml(
                    Html::el("tr", [
                        "style" => [
                            "color" => $textColor,
                            "background-color" => $backgroundColor
                        ]
                    ])
                        ->addHtml(
                            Html::el("td", ["colspan" => 5])
                                ->setText($query->errorText)
                        )

                );
            }


            $execSpeedTotal += $query->speed;

        }

        if (count($modificationQueryList) === 0) {
            $table->addHtml(
                Html::el("tr", ["style" => ["background-color" => "rgba(0,150,0,0.1)"]])
                    ->addHtml(
                        Html::el("td", [
                            "colspan" => 5,
                            "style" => [
                                "color" => "darkgreen",
                                "font-weight" => "bold",
                                "text-align" => "center"
                            ]
                        ])
                            ->setText("No queries found!")
                    )

            );
        }


        $table->addHtml(
            Html::el("tr")
                ->addHtml(
                    Html::el("td colspan=4")
                )
                ->addHtml(
                    Html::el("th")
                        ->setText(static::convertToMs($execSpeedTotal, 3))
                        ->addText(" ms")
                )
        );

        return $this->createToggle(
            "query-panel-modification",
            Html::el("")
                ->addText("Modification queries")
                ->addText(" ")
                ->addText("(")
                ->addText(
                    static::convertToMs(
                        Measure::querySpeedModification()
                    )
                )
                ->addText(" ms")
                ->addText(" / ")
                ->addText(Measure::queryCountModification())
                ->addText(")")
            ,
            $table, false
        );
    }

    /**
     * @inheritDoc
     */
    function getPanel(): ?string
    {

        if (Measure::$Driver === null) {

            $UninitializedHTML = Html::el("div", ["class" => "tracy-inner"]);
            $Inner = $UninitializedHTML->addHtml(Html::el("div", ["class" => "tracy-inner-container"]));

            $Inner->addHtml(
                $this->headerElement(false)
            );

            return $UninitializedHTML;
        }

        $panelSpeed = microtime(true);

        $panelContent = Html::el("");

        //Header:
        $panelContent->addHtml(
            $this->headerElement()
        );

        //Tracy Inner
        $panelContent->addHtml(Html::el("div", ["class" => "tracy-inner"])
            ->addHtml(
                Html::el("div", ["class" => "tracy-inner-container"])
                    ->addHtml(
                        $this->subPanelSpeed()
                    )
                    ->addHtml(Html::el("br"))
                    ->addHtml(
                        $this->subPanelStructure()
                    )
                    ->addHtml(Html::el("br"))
                    ->addHtml(
                        $this->subPanelDescription()
                    )
                    ->addHtml(Html::el("br"))
                    ->addHtml(
                        $this->subPanelModification()
                    )
            )
        );


        return $panelContent->render();

    }

    /**
     * @param string $id
     * @param string $header
     * @param bool $defaultHidden
     * @return Html<Html>
     */
    private function createToggleHandle(string $id, string $header, bool $defaultHidden = true): Html
    {
        $id = static::createRealId($id);

        return Html::el("a")
            ->href("#" . $id)
            ->class("tracy-toggle", true)
            ->class("tracy-collapsed", $defaultHidden)
            ->addHtml($header);
    }

    /**
     * @param string $id
     * @param string $elem
     * @param Html<Html> $content
     * @param bool $defaultHidden
     * @return Html<Html>
     */
    private function createToggleContainer(string $id, string $elem, Html $content, bool $defaultHidden = true): Html
    {
        $id = static::createRealId($id);

        return Html::el($elem, ["id" => $id])
            ->class("tracy-collapsed", $defaultHidden)
            ->addHtml($content);
    }


    /**
     * @param string $id
     * @param string $header
     * @param Html<Html> $content
     * @param bool $defaultHidden
     * @return Html<Html>
     */
    private function createToggle(string $id, string $header, Html $content, bool $defaultHidden = true): Html
    {
        return Html::el("")

            // Handle:
            ->addHtml(
                $this->createToggleHandle($id, $header, $defaultHidden)
            )

            // Content:
            ->addHtml(
                $this->createToggleContainer($id, "div", $content, $defaultHidden)
            )
            ->addHtml(
                Html::el("br")
            );
    }

    /**
     * @param string $identifier
     * @return string
     */
    private static function createRealId(string $identifier): string
    {
        return 'tracy-addons-MkSQL-' . $identifier;
    }


    /**
     * @param float $seconds
     * @param int $precision
     * @return string
     */
    private static function convertToMs(float $seconds, int $precision = 1): string
    {
        return number_format(round(1000 * $seconds, $precision), $precision, '.', '');
    }

    /**
     * Table[] / Table[s]
     * @param int $count
     * @return string
     */
    private static function s(int $count): string
    {
        if ($count != 1)
            return 's';
        return '';
    }

    /**
     * Quer[y]/ Quer[ies]
     * @param int $count
     * @return string
     */
    private static function ies(int $count): string
    {
        if ($count != 1)
            return 'ies';
        return 'y';
    }

    /**
     * @param bool $yesNo
     * @param bool $colors
     * @return Html<Html>
     */
    private static function yesNoInverted(bool $yesNo, bool $colors = true): Html
    {
        $text = $yesNo ? "Yes" : "No";
        $color = $yesNo ? "maroon" : "darkgreen";

        $element = Html::el("b");

        if ($colors)
            $element->style("color", $color);

        $element->setText($text);
        return $element;
    }

    /**
     * @param bool $yesNo
     * @param bool $colors
     * @return Html<Html>
     */
    private static function yesNo(bool $yesNo, bool $colors = true): Html
    {
        $text = $yesNo ? "Yes" : "No";
        $color = $yesNo ? "darkgreen" : "maroon";

        $element = Html::el("b");

        if ($colors)
            $element->style("color", $color);

        $element->setText($text);
        return $element;
    }


}
