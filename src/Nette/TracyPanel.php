<?php
/*
 * Zrník.eu | MkSQL  
 * User: Programátor
 * Date: 04.08.2020 9:42
 */


namespace Zrny\MkSQL\Nette;

use Tracy\IBarPanel;

class TracyPanel implements IBarPanel
{

    /**
     * @inheritDoc
     */
    function getTab()
    {
        // TODO: Implement getTab() method.
        return 'mksql';
    }

    /**
     * @inheritDoc
     */
    function getPanel()
    {
        return 'mksql';
        //
    }
}