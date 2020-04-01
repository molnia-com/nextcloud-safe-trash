<?php


namespace OCA\Molnia\Plugin;


use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class DeleteServerPlugin extends ServerPlugin
{
    public const TRASHBIN = 'trashbin';

    /** @var Server */
    private $server;

    /**
     * @inheritDoc
     */
    public function initialize(Server $server): void
    {
        $this->server = $server;
        $this->server->on('beforeMethod:DELETE', [$this, 'onDelete']);
    }

    public function onDelete(RequestInterface $request, ResponseInterface $response): bool
    {
        $baseUri = $this->server->getBaseUri();
        $fullUri = $request->getPath();
        $userAndFilePath = preg_replace("#{$baseUri}#", '', $fullUri);
        $parts = explode('/', $userAndFilePath);
        $where = array_shift($parts);
        if ($where !== self::TRASHBIN) {
            return true;
        }

        $user = array_shift($parts);
        $path = implode('/', $parts);

        trigger_error(__METHOD__ . "; where={$where}; user={$user}; path={$path}");

        return true;
    }
}
