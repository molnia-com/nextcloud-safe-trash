<?php


namespace OCA\Molnia\Plugin;


use OC\Group\Manager;
use OCA\Files_Trashbin\Sabre\TrashFile;
use OCA\Files_Trashbin\Sabre\TrashFolder;
use OCA\Files_Trashbin\Sabre\TrashRoot;
use OCP\AppFramework\App;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Share\IShare;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class DeleteServerPlugin extends ServerPlugin
{
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
     * @param Server $server
     */
    public function setServer(Server $server): void
    {
        self::$server = $server;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return self::$server;
    }

    /**
     * @param string $fullUri
     * @return bool
     */
    public function beforeUnbind(string $fullUri): bool
    {
        $this->getLogger()->warning(__METHOD__ . ": path={$fullUri}");
        if (!$user = $this->getUser($fullUri)) {
            return true;
        }

        if ($this->getOcaServer()->getGroupManager()->isAdmin($user->getUID())) {
            return true;
        }

        /** @var Manager $groupManager */
        $groupManager = $this->getOcaServer()->getGroupManager();
        /** @var IUser $trashOwner */
        $trashOwner = null;
        if ($groups = $groupManager->getUserGroups($user)) {
            foreach ($groups as $group) {
                if ($groupManager->getSubAdmin()->isSubAdminOfGroup($user, $group)) {
                    return true;
                }
            }
        } elseif ($serverAdminGroup = $this->getOcaServer()->getGroupManager()->get('admin')) { // fast
            $trashOwner = $serverAdminGroup->getUsers()[0];
        } else {
            $this->getOcaServer()->getUserManager()->callForAllUsers(function (IUser $user) use ($trashOwner) {
                if (!$trashOwner && $this->getOcaServer()->getGroupManager()->isAdmin($user)) { // safe
                    $trashOwner = $user;
                }
            });
        }

        try {
            $node = $this->getServer()->tree->getNodeForPath($fullUri);
        } catch (NotFound $e) {
            $this->getLogger()->warning(__METHOD__ . ": getNodeForPath returns 404; path={$fullUri}");

            return true;
        }

        /** @var IShare[] $allShares */
        $allShares = [];
        foreach (
            [
                IShare::TYPE_USER,
                IShare::TYPE_GROUP,
                IShare::TYPE_USERGROUP,
                IShare::TYPE_LINK,
                IShare::TYPE_EMAIL,
                IShare::TYPE_REMOTE,
                IShare::TYPE_CIRCLE,
                IShare::TYPE_REMOTE_GROUP,
                IShare::TYPE_ROOM,
            ] as $shareType
        ) {
            $allShares = array_merge(
                $allShares,
                $this->getOcaServer()->getShareManager()->getSharedWith($user->getUID(), $shareType)
            );
        }

        switch (true) {
            case ($node instanceof TrashRoot):
                /** @var TrashRoot $node */
                break;

            case ($node instanceof TrashFolder):
                /** @var TrashFolder $node */
                break;

            case ($node instanceof TrashFile):
                /** @var TrashFile $node */
                $originalPath = $node->getOriginalLocation();
                foreach ($allShares as $share) {
                    if (strpos($originalPath, $share->getNode()->getPath()) !== false) {
                        $this->getLogger()->warning(__METHOD__ . ": {$originalPath} was shared in {$share->getNode()->getPath()}");
                    }
                }

                $this->getLogger()->warning(__METHOD__ . ": about to delete {$originalPath}");

                break;

            default:
                $class = get_class($node);
                $this->getLogger()->warning(__METHOD__ . "\$node instanceof {$class}");

                break;
        }

        return true;
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
     * @return App
     */
    public function getApp(): App
    {
        return self::$app;
    }

    /**
     * @param ILogger $logger
     */
    public function setLogger(ILogger $logger): void
    {
        self::$logger = $logger;
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
     * @param string $fullUri
     * @return IUser
     */
    private function getUser(string $fullUri): ?IUser
    {
        $baseUri = $this->getServer()->getBaseUri();
        $userAndFilePath = preg_replace("#{$baseUri}#", '', $fullUri);
        $parts = explode('/', $userAndFilePath);
        array_shift($parts);
        $uid = array_shift($parts);
        if (!$user = $this->getOcaServer()->getUserManager()->get($uid)) {
            $this->getLogger()->warning(__METHOD__ . ': get user by uid fails');

            return null;
        }

        return $user;
    }

    /**
     * @param App $app
     */
    public function setApp(App $app): void
    {
        self::$app = $app;
    }
}
