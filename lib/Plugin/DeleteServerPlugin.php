<?php


namespace OCA\Molnia\Plugin;


use OCP\AppFramework\App;
use OCP\ILogger;
use OCP\IServerContainer;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAVACL\IACL;

class DeleteServerPlugin extends ServerPlugin
{
    public const TRASHBIN = 'trashbin';

    /** @var Server */
    private static $server;

    /** @var App */
    private static $app;

    /** @var ILogger */
    private static $logger;

    /** @var IServerContainer */
    private static $ocaServer;

    /**
     * @inheritDoc
     */
    public function initialize(Server $server): void
    {
        $this->setServer($server);
        $this->getServer()->on('beforeUnbind', [$this, 'beforeUnbind']);
    }

    /**
     * @param string $fullUri
     * @return bool
     */
    public function beforeUnbind(string $fullUri): bool
    {
        $this->getLogger()->warning(__METHOD__ . ": path={$fullUri}");
        $baseUri = $this->getServer()->getBaseUri();
        $userAndFilePath = preg_replace("#{$baseUri}#", '', $fullUri);
        $parts = explode('/', $userAndFilePath);
        $where = array_shift($parts);
        if ($where !== self::TRASHBIN) {
            return true;
        }

        $uid = array_shift($parts);
        $path = implode('/', $parts);

        try {
            $node = $this->getServer()->tree->getNodeForPath($fullUri);
        } catch (NotFound $e) {
            $this->getLogger()->warning(__METHOD__ . ": getNodeForPath returns 404; path={$fullUri}");
            return true;
        }

        if (!($node instanceof IACL)) {
            $this->getLogger()->warning(__METHOD__ . ': "$node instanceof IACL" fails');
            return true;
        }

        /** @var IACL $node */
        $owner = $node->getOwner();

        if (!$user = $this->getOcaServer()->getUserManager()->get($uid)) {
            $this->getLogger()->warning(__METHOD__ . ': get user by uid fails');
            return true;
        }

        if (!$currentUser = $this->getOcaServer()->getUserSession()->getUser()) {
            $this->getLogger()->warning(__METHOD__ . ': get current user fails');
            return true;
        }

        $this->getLogger()->warning(__METHOD__ . "; where={$where}; user={$user->getDisplayName()}; currentUser={$currentUser->getDisplayName()}; owner={$owner}; path={$path}");

        return true;
    }

    /**
     * @return IServerContainer
     */
    public function getOcaServer(): IServerContainer
    {
        if (!self::$ocaServer && $this->getApp()) {
            $this->setOcaServer($this->getApp()->getContainer()->getServer());
        }

        return self::$ocaServer;
    }

    /**
     * @param IServerContainer $ocaServer
     */
    public function setOcaServer(IServerContainer $ocaServer): void
    {
        self::$ocaServer = $ocaServer;
    }

    /**
     * @return App
     */
    public function getApp(): App
    {
        return self::$app;
    }

    /**
     * @param App $app
     */
    public function setApp(App $app): void
    {
        self::$app = $app;
    }

    /**
     * @return ILogger
     */
    public function getLogger(): ILogger
    {
        if (!self::$logger && $this->getApp()) {
            $this->setLogger($this->getOcaServer()->getLogger());
        }

        return self::$logger;
    }

    /**
     * @param ILogger $logger
     */
    public function setLogger(ILogger $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return self::$server;
    }

    /**
     * @param Server $server
     */
    public function setServer(Server $server): void
    {
        self::$server = $server;
    }
}
