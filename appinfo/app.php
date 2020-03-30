<?php

use Molnia\Nextcloud\AppInfo\Application;

/** @var Application $app */
$app = OC::$server->query(Application::class);
$app->register();
