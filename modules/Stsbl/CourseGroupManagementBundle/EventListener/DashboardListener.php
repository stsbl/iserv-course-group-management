<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use IServ\CoreBundle\Entity\Group;
use IServ\CoreBundle\Event\DashboardEvent;
use IServ\CoreBundle\Event\HomePageEvent;
use IServ\CoreBundle\EventListener\HomePageListenerInterface;
use IServ\Library\Config\Config;
use IServ\Library\Zeit\Zeit;
use IServ\ManageBundle\EventListener\ManageDashboardListenerInterface;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;

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
final class DashboardListener implements HomePageListenerInterface, ManageDashboardListenerInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var bool
     */
    private $isIDeskEvent = false;

    public function __construct(ManagerRegistry $doctrine, Config $config)
    {
        $this->doctrine = $doctrine;
        $this->config = $config;
    }

    /**
     * Checks if it is time to display the promotion notice.
     */
    private function isDisplayTime(): bool
    {
        $fromDay = $this->config->get('CourseGroupManagementDisplayFromDay');
        $fromMonth = $this->config->get('CourseGroupManagementDisplayFromMonth');
        $untilDay = $this->config->get('CourseGroupManagementDisplayUntilDay');
        $untilMonth = $this->config->get('CourseGroupManagementDisplayUntilMonth');

        if ($fromDay === 0 && $fromMonth === 0 && $untilDay === 0 && $untilMonth === 0) {
            return true;
        }

        if ($fromDay !== 0 && $fromMonth !== 0 && $untilDay !== 0 && $untilMonth !== 0) {
            $year = date('Y');
            $from = new \DateTime(sprintf('%s-%s-%s', $year, $fromMonth, $fromDay));
            $until = new \DateTime(sprintf('%s-%s-%s', $year, $untilMonth, $untilDay));

            if ($until->getTimestamp() < $from->getTimestamp()) {
                $until->add(new \DateInterval('P1Y'));
            }

            $now = Zeit::now();
            $timestamp = $now->getTimestamp();

            if ($timestamp >= $from->getTimestamp() && $timestamp <= $until->getTimestamp()) {
                return true;
            }
        } else {
            return true;
        }

        return false;
    }

    /**
     * Get groups which are promotable
     *
     * @return Group[]
     */
    private function getGroups(DashboardEvent $event): array
    {
        $er = $this->doctrine->getRepository(Group::class);

        $subQb = $er->createQueryBuilder('r');

        $subQb
            ->resetDQLParts()
            ->select('r')
            ->from('StsblCourseGroupManagementBundle:PromotionRequest', 'r')
            ->where($subQb->expr()->eq('g.account', 'r.group'))
        ;

        return $er->createFindByFlagQueryBuilder(Privilege::FLAG_COURSE_GROUP, 'g')
            ->select('g')
            ->andWhere($subQb->expr()->not($subQb->expr()->exists($subQb)))
            ->andWhere($subQb->expr()->eq('g.owner', ':owner'))
            ->orderBy('g.name', 'ASC')
            ->setParameter('owner', $event->getUser())
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Adds notice if there are unlockable groups for exam plan.
     */
    public function onBuildManageDashboard(DashboardEvent $event): void
    {
        if (!$event->getAuthorizationChecker()->isGranted(Privilege::REQUEST_PROMOTIONS)) {
            // exit if user is not privileged
            return;
        }

        $groups = $this->getGroups($event);

        if (count($groups) === 0) {
            // exit if no groups are available
            return;
        }

        if (!$this->isDisplayTime()) {
            // exit if we are outside of time range
            return;
        }

        $icon = [
            'style' => 'fugue',
            'name' => 'clipboard-block'
        ];


        $event->addContent(
            'manage.stsblcoursegroupmanagement',
            'StsblCourseGroupManagementBundle:Dashboard:pending.html.twig',
            [
                'title' => __n('You can promote one course group for next school year', 'You can promote %d course groups for next school year', count($groups), count($groups)),
                'text' => _('The following groups are in queue for promoting:'),
                'additional_text' => _('You can go to „Request promotion for course groups“ and request a promote for this groups.') . ' ' .
                    _('Groups without promotion request are may get deleted automatically during promotion process.') . ' ' .
                    _('Please ask your administrator for further information.'),
                'groups' => $groups,
                'panel_class' => 'panel-primary',
                'idesk' => $this->isIDeskEvent,
                'icon' => $icon,
            ],
            -2
        );
    }

    /**
     * {@inheritdoc}
     */
    public function onBuildHomePage(HomePageEvent $event): void
    {
        $this->isIDeskEvent = true;
        $this->onBuildManageDashboard($event);
    }

}
