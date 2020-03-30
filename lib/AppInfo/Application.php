<?php

namespace OCA\Molnia\AppInfo;


use OCA\Molnia\Plugin\DeleteServerPlugin;
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
        $dispatcher->addListener('OCA\DAV\Connector\Sabre::addPlugin', static function (Event $ev) {
            /** @var SabrePluginEvent $ev */
            if (!($ev instanceof SabrePluginEvent)) {
                return;
            }

            if ($server = $ev->getServer()) {
                $server->addPlugin(new DeleteServerPlugin());
            }
        });
    }
}
