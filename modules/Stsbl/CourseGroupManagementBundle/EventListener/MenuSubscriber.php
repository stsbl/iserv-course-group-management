<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle\EventListener;

use IServ\AdminBundle\Event\Events;
use IServ\AdminBundle\EventListener\AdminMenuListenerInterface;
use IServ\CoreBundle\Event\MenuEvent;
use IServ\ManageBundle\Event\MenuEvent as ManageMenuEvent;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/*
 * The MIT License
 *
 * Copyright 2021 Felix Jacobi.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
final class MenuSubscriber implements AdminMenuListenerInterface, EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public function onBuildAdminMenu(MenuEvent $event): void
    {
        // check privilege
        if ($event->getAuthorizationChecker()->isGranted(Privilege::REQUEST_PROMOTIONS)) {
            $menu = $event->getMenu();
            $child = $menu->getChild(self::ADMIN_MODULES);

            $item = $child->addChild('admin_coursegroupmanagement', [
                'route' => 'admin_coursegroupmanagement_request',
                'label' => _('Course Group Management')
            ]);

            $item->setExtra('icon', 'clipboard-block');
            $item->setExtra('icon_style', 'fugue');
        }
    }

    /**
     * @param MenuEvent $event
     */
    public function onBuildManageMenu(MenuEvent $event): void
    {
        // check privilege
        if ($event->getAuthorizationChecker()->isGranted(Privilege::REQUEST_PROMOTIONS)) {
            $menu = $event->getMenu();

            $item = $menu->addChild('manage_coursegroupmanagement', [
                'route' => 'manage_coursegroupmanagement_request',
                'label' => _('Request promotion for course groups')
            ]);

            $item->setExtra('icon', 'clipboard-block');
            $item->setExtra('icon_style', 'fugue');
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::MENU => 'onBuildAdminMenu',
            ManageMenuEvent::MANAGEMENTMENU => 'onBuildManageMenu',
        ];
    }

}
