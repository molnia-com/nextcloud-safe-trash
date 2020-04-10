<?php
/**
 * @copyright Copyright (c) 2020, Molnia, LLC.
 *
 * @author Sergey Drobov <sdrobov@molnia.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\Molnia\AppInfo;


use OCA\Molnia\Plugin\DeleteServerPlugin;
use OCP\AppFramework\App;
use OCP\EventDispatcher\Event;
use OCP\SabrePluginEvent;

class Application extends App
{
	/**
	 * Application constructor.
	 */
	public function __construct() {
		parent::__construct('restricted_trashbin');
	}

	public function register(): void {
		$dispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$dispatcher->addListener(
			'OCA\DAV\Connector\Sabre::addPlugin',
			function (Event $ev) {
				/** @var SabrePluginEvent $ev */
				if (!($ev instanceof SabrePluginEvent)) {
					return;
				}

				if ($server = $ev->getServer()) {
					$plugin = new DeleteServerPlugin();
					$plugin->setApp($this);

					$server->addPlugin($plugin);
				}
			});
	}
}
