<?php

use Zrnik\MkSQL\Updater;
use Zrnik\MkSQL\Utilities\Installable;

class ExampleFactoryOrRepository extends Installable
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * This method is now required by `Installable` class
     *
     * DO NOT CALL $updater->install(); as the Installable
     * parent will handle it for you.
     */
    public function install(Updater $updater)
    {

        // ... Here we define tables with columns

    }

// ... Here is the rest of your class

}