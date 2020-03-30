<?php


namespace OCA\Molnia\Plugin;


use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class DeleteServerPlugin extends ServerPlugin
{
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

    public function onDelete(RequestInterface $request, ResponseInterface $response)
    {
        $this->server->getLogger()->warning(__METHOD__ . ' called');
    }
}
