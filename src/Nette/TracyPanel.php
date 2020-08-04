<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 04.08.2020 9:42
 */


namespace Zrny\MkSQL\Nette;

use Nette\Database\Helpers;
use React\Promise\Util;
use Tracy\Debugger;
use Tracy\IBarPanel;
use Zrny\MkSQL\Column;
use Zrny\MkSQL\Updater;
use Zrny\MkSQL\Utils;

class TracyPanel implements IBarPanel
{

    /**
     * @inheritDoc
     */
    function getTab()
    {
        $TotalTables = 0;
        $TotalColumns = 0;

        foreach(Updater::$_UpdateLog as $Table => $Columns)
        {
            $TotalTables++;
            foreach($Columns as $Column => $Log)
            {
                $TotalColumns++;
            }
        }

        $SelectedImg = ($TotalTables === 0 && $TotalColumns === 0) ? self::$_ImageData_Success : self::$_ImageData_Warning;
        $imgDimension = 16;

        $TabHtml = '';

        $TabHtml .= '<span class="tracy-label"><img style="max-width: '.$imgDimension.'px; max-height: '.$imgDimension.'px;"; src="'.$SelectedImg.'"> 
        MkSQL '.($TotalTables === 0 && $TotalColumns === 0 ? '' : '('.$TotalTables.','.$TotalColumns.')').'</span>';


        return $TabHtml;
    }

    /**
     * @inheritDoc
     */
    function getPanel()
    {
        $TotalTables = 0;
        $TotalColumns = 0;
        foreach(Updater::$_UpdateLog as $Table => $Columns)
        {
            $TotalTables++;
            foreach($Columns as $Column => $Log)
            {
                $TotalColumns++;
            }
        }

        $hasChanges = ($TotalTables !== 0 || $TotalColumns !== 0);
        $SelectedImg = !$hasChanges? self::$_ImageData_Success : self::$_ImageData_Warning;
        $imgDimension = 22;

        $PanelHtml = '';
        $PanelHtml .= '<h1 style="color: '.($hasChanges?"orange":"black").';"><img style="max-width: '.$imgDimension.'px; max-height: '.$imgDimension.'px;"; src="'.$SelectedImg.'"> MkSQL ';
        if($hasChanges)
        {
            $PanelHtml .= 'changes: '.$TotalTables.' table(s) and '.$TotalColumns.' column(s)';
        }
        else
        {
            $PanelHtml .= 'up to date!';
        }
        $PanelHtml .= '</h1>';
        $PanelHtml .= '<div class="tracy-inner"><div class="tracy-inner-container">';

        $PanelHtml .= '<table>';
        $PanelHtml .= '<tr><th colspan="2">Speed</th></tr>';


        $PanelHtml .= '<tr><th colspan="2"></th></tr>';


        $PanelHtml .= '<tr><th>SQL Generating</th><td style="text-align: right;"><pre>'.Utils::convertToMs(Updater::$_SecondsSpentGeneratingCommands).' ms</pre></td></tr>';
        $PanelHtml .= '<tr><th>Query Executing</th><td style="text-align: right;"><pre>'.Utils::convertToMs(Updater::$_SecondsSpentExecutingQueries).' ms</pre></td></tr>';


        $PanelHtml .= '<tr><th colspan="2"></th></tr>';


        $PanelHtml .= '<tr><th>Total</th><td style="text-align: right;"><pre><b>'.Utils::convertToMs(Updater::$_SecondsSpentInstalling).' ms</b></pre></td></tr>';

        $PanelHtml .= '</table>';

//Speed: <b>'.(round(1000*Updater::$_SecondsSpentInstalling,3)).'</b> ms
        // Structure Info:

        $structureHtml = '';
        $structureTables = 0;
        $structureColumns = 0;

        foreach(Updater::$_StructureLog as $TableName => $Columns)
        {
            $timesCalled = isset(Updater::$_InstallCall[$TableName]) ? Updater::$_InstallCall[$TableName] : 0;

            $structureTables++;
            $structureHtml .= '<tr>';
            $structureHtml .= '<th>Table: '.$TableName.'</th>';
            $structureHtml .= '<th>'.count($Columns).' columns</th>';
            $structureHtml .= '<th>called '.$timesCalled.'x</th>';
            $structureHtml .= '<th>'.Utils::convertToMs(Updater::$_InstallSpeed[$TableName]).' ms</th>';
            $structureHtml .= '<th><a href="#tracy-table-container-'.$TableName.'" class="tracy-toggle tracy-collapsed">show</a></th>';


            $structureHtml .= '</tr><tr>';
            $structureHtml .= '<td colspan="5"><table id="tracy-table-container-'.$TableName.'" class="tracy-collapsed">';


            /**
             * @var $Column Column
             */
            foreach($Columns as $Column)
            {
                $structureColumns++;
                $structureHtml .= '<tr>';
                $structureHtml .= '<td style="width: 15%">'.$Column->getName().'</td>';
                $structureHtml .= '<td style="width: 10%">'.$Column->getType().'</td>';
                $structureHtml .= '<td style="width: 75%">';

                $structureHtml .= '<table>';


                $structureHtml .= '<tr>';
                $structureHtml .= '<th style="width: 50%;">Not Null</th>';
                $structureHtml .= '<td style="width: 50%;"><b style="color:'.(($Column->getNotNull())?"darkgreen":"blue").';">'.(($Column->getNotNull())?"Yes":"No").'</b></td>';
                $structureHtml .= '</tr>';

                $structureHtml .= '<tr>';
                $structureHtml .= '<th style="width: 50%;">Is Unique</th>';
                $structureHtml .= '<td style="width: 50%;"><b style="color:'.(($Column->getUnique())?"darkgreen":"blue").';">'.(($Column->getUnique())?"Yes":"No").'</b></td>';
                $structureHtml .= '</tr>';


                $structureHtml .= '<tr>';
                $structureHtml .= '<th>Default Value</th>';
                $structureHtml .= '<td>'.(($Column->getDefault() === null)?"Unset":"<b>".$Column->getDefault()."</b>").'</b></td>';
                $structureHtml .= '</tr>';

                $structureHtml .= '<tr>';
                $structureHtml .= '<th>Comment</th>';
                $structureHtml .= '<td>'.(($Column->getComment() === null)?"Unset":"<b>".$Column->getComment()."</b>").'</b></td>';
                $structureHtml .= '</tr>';

                $structureHtml .= '<tr>';
                $structureHtml .= '<th>Foreign Keys</th>';
                $structureHtml .= '<td>'.( count($Column->getForeignKeys()) === 0  ? "None" : Debugger::dump($Column->getForeignKeys(),true) ).'</td>';
                $structureHtml .= '</tr>';




                $structureHtml .= '</table>';



                /*.Debugger::dump([
                        "NotNull" => $Column->getNotNull(),
                        "DefaultValue" => $Column->getDefault(),
                        "IsUnique" => $Column->getUnique(),
                        "Comment" => $Column->getComment(),
                        "ForeignKeys" => (count($Column->getForeignKeys()) > 0) ? $Column->getForeignKeys() : false,
                ],true).*/
                $structureHtml .= '</td>';
                $structureHtml .= '</tr>';
            }


            $structureHtml .= '</table></td>';
            $structureHtml .= '</tr>';
        }


        $PanelHtml .= '<br><a href="#tracy-addons-mksql-structure-overview" class="tracy-toggle">Structure ('.$structureTables.' tables and '.$structureColumns.' columns)</a>';
        $PanelHtml .= '<div id="tracy-addons-mksql-structure-overview">
            <table>      
                '.$structureHtml.'                        
            </table>
        </div>';







        if($hasChanges)
        {
            $PanelHtml .= '<br><a href="#tracy-addons-mksql-structure-changes" class="tracy-toggle">Changes ('.$TotalTables.' tables and '.$TotalColumns.' columns)</a>';


            $changesHtml = '';
            foreach(Updater::$_UpdateLog as $Table => $Columns)
            {
                foreach($Columns as $Column => $Logs)
                {
                    foreach($Logs as $Log)
                    {
                        $changesHtml .= '<tr>';
                        $changesHtml .= '<td><b style="color:darkgreen;">'.$Table.'</b></td>';
                        $changesHtml .= '<td><b style="color:blue;">'.$Column.'</b></td>';
                        $changesHtml .= '<td><b>'.$Log["reason"].'</b></td>';
                        $changesHtml .= '</tr>';

                        $changesHtml .= '<tr>';
                        $changesHtml .= '<td colspan="3">'.Helpers::dumpSql($Log["sql"]).'</td>';
                        $changesHtml .= '</tr>';


                        $changesHtml .= '<tr>';
                        $changesHtml .= '<th colspan="3"></th>';
                        $changesHtml .= '</tr>';
                    }

                }
            }


            $PanelHtml .= '<div id="tracy-addons-mksql-structure-changes">
                <table>
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Column</th>
                            <th>Reason</th>
                        </tr>
                    </thead>                    
                    <tbody>
                        '.$changesHtml.'
                    </tbody>
                </table>
            </div>';
        }



        $PanelHtml .= '</div></div>';
        return $PanelHtml;

    }



    private static $_ImageData_Success = 'data:image/png;base64,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';
    private static $_ImageData_Warning = 'data:image/png;base64,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';


}