<?php


namespace OCA\Molnia\Plugin;


use OCA\Files_Trashbin\Sabre\TrashFile;
use OCA\Files_Trashbin\Sabre\TrashFolder;
use OCA\Files_Trashbin\Sabre\TrashRoot;
use OCA\Files_Trashbin\Trashbin;
use OCP\AppFramework\App;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Share\IShare;
use RuntimeException;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;
use Sabre\DAV\INode;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Throwable;

class DeleteServerPlugin extends ServerPlugin
{
    /** @var Server */
    private static $server;

    /** @var App */
    private static $app;

    /** @var IServerContainer */
    private static $ocaServer;

    /** @var IUser */
    private static $user;

    /** @var string[] */
    private static $shares = [];

    /** @var string[] */
    private static $processedFiles = [];

    /**
     * @return App
     */
    public function getApp(): App
    {
        return self::$app;
    }

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
        // beforeUnbind вызывается так же при перемещении
        if (!$this->isDelete()) {
            return true;
        }

        if (!$this->getUser($fullUri)) {
            return true;
        }

        if ($this->getOcaServer()->getGroupManager()->isAdmin(self::$user->getUID())) {
            return true;
        }

        if (!$su = $this->getSuperAdmin()) {
            throw new RuntimeException('Cant get superadmin');
        }

        try {
            $node = $this->getServer()->tree->getNodeForPath($fullUri);
        } catch (NotFound $e) {
            return true;
        }

        /** @var TrashFile[] $files */
        $files = [];
        switch (true) {
            case ($node instanceof TrashRoot):
            case ($node instanceof TrashFolder):
                /** @var TrashRoot|TrashFolder $node */
                $files = $this->getAllNodesRecursive($node);

                break;

            case ($node instanceof TrashFile):
                /** @var TrashFile $node */
                $files = [$node];

                break;

            default:
                return true;
        }

        $uid = self::$user->getUID();

        $trashbin = new Trashbin();
        $copyFilesToUserCb = function () {
            return static::copyFilesToUser(...func_get_args());
        };
        $copyFilesToUser = $copyFilesToUserCb->bindTo($trashbin, Trashbin::class);

        foreach ($files as $file) {
            $originalLocation = $file->getOriginalLocation();

            if (!$this->wasPathShared($originalLocation)) {
                continue;
            }

            if (in_array($originalLocation, self::$processedFiles)) {
                continue;
            }

            self::$processedFiles[] = $originalLocation;

            $copyFilesToUser($originalLocation, $uid, $originalLocation, $su->getUID(), $file->getDeletionTime());
        }


        return true;
    }

    /**
     * @param App $app
     */
    public function setApp(App $app): void
    {
        self::$app = $app;
    }

    /**
     * @return bool
     */
    private function isDelete(): bool
    {
        return strtolower($this->getOcaServer()->getRequest()->getMethod()) === 'delete';
    }

    /**
     * @return IUser|null
     */
    private function getSuperAdmin(): ?IUser
    {
        // fast
        if (
            ($serverAdminGroup = $this->getOcaServer()
                ->getGroupManager()
                ->get('admin')) && $adminUsers = $serverAdminGroup->getUsers()
        ) {
            return current($adminUsers);
        }

        // safe
        /** @var IUser $superAdmin */
        $superAdmin = null;
        $this->getOcaServer()->getUserManager()->callForAllUsers(function (IUser $user) use (&$superAdmin) {
            if (!$superAdmin && $this->getOcaServer()->getGroupManager()->isAdmin($user)) {
                $superAdmin = $user;
            }
        });

        return $superAdmin;
    }

    /**
     * @return IServerContainer
     */
    private function getOcaServer(): IServerContainer
    {
        if (!self::$ocaServer && $this->getApp()) {
            $this->setOcaServer($this->getApp()->getContainer()->getServer());
        }

        return self::$ocaServer;
    }

    /**
     * @param IServerContainer $ocaServer
     */
    private function setOcaServer(IServerContainer $ocaServer): void
    {
        self::$ocaServer = $ocaServer;
    }

    /**
     * @param Server $server
     */
    private function setServer(Server $server): void
    {
        self::$server = $server;
    }

    /**
     * @return Server
     */
    private function getServer(): Server
    {
        return self::$server;
    }

    /**
     * @param string $fullUri
     * @return IUser|null
     */
    private function getUser(string $fullUri): ?IUser
    {
        if (!self::$user) {
            $baseUri = $this->getServer()->getBaseUri();
            $userAndFilePath = preg_replace("#{$baseUri}#", '', $fullUri);
            $parts = explode('/', $userAndFilePath);
            array_shift($parts);
            $uid = array_shift($parts);

            self::$user = $this->getOcaServer()->getUserManager()->get($uid);
        }

        return self::$user;
    }

    /**
     * @param ICollection $collection
     * @return INode[]
     */
    private function getAllNodesRecursive(ICollection $collection): array
    {
        $nodes = [];
        foreach ($collection->getChildren() as $child) {
            if ($child instanceof ICollection) {
                $nodes = array_merge($nodes, $this->getAllNodesRecursive($child));
            } else {
                $nodes[] = $child;
            }
        }

        return $nodes;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function wasPathShared(string $path): bool
    {
        if (!self::$shares) {
            self::$shares = [];

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
                try {
                    $shares = $this->getOcaServer()
                        ->getShareManager()
                        ->getSharedWith(self::$user->getUID(), $shareType);
                    $sharePaths = array_map(static function (IShare $share) {
                        $sharePath = $share->getNode()->getPath();
                        $sharePath = trim($sharePath, '/');
                        $pathParts = explode('/', $sharePath);
                        array_shift($pathParts); // user
                        array_shift($pathParts); // 'files'

                        return implode('/', $pathParts);
                    },
                        $shares);

                    self::$shares = array_merge(self::$shares, $sharePaths);
                } catch (Throwable $e) {
                    continue;
                }
            }

            self::$shares = array_filter(array_unique(self::$shares));
        }

        foreach (self::$shares as $share) {
            if (strpos($path, $share) !== false) {
                return true;
            }
        }

        return false;
    }
}
