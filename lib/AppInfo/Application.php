<?php

namespace Molnia\Nextcloud\AppInfo;


use OCP\AppFramework\App;
use OCP\EventDispatcher\Event;
use OCP\SabrePluginEvent;

class Application extends App
{
    public function __construct()
    {
        parent::__construct('restricted_trashbin');
    }

    public function register()
    {
        $dispatcher = $this->getContainer()->getServer()->getEventDispatcher();
        $dispatcher->addListener('OCA\DAV\Connector\Sabre::addPlugin', function (Event $ev) {
            if (!($ev instanceof SabrePluginEvent)) {
                return;
            }

            $this->getContainer()->getServer()->getLogger()->warning(__METHOD__ . ' called');
        });
    }
}
