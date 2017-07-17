<?php
// src/Stsbl/CourseGroupManagementBundle/EventListener/ManageDashboardListener.php
namespace Stsbl\CourseGroupManagementBundle\EventListener;

use Doctrine\ORM\EntityManager;
use IServ\CoreBundle\Entity\Group;
use IServ\CoreBundle\Entity\GroupRepository;
use IServ\CoreBundle\Event\DashboardEvent;
use IServ\CoreBundle\Event\IDeskEvent;
use IServ\CoreBundle\EventListener\IDeskListenerInterface;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;

/*
 * The MIT License
 *
 * Copyright 2017 Felix Jacobi.
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
class DashboardListener implements IDeskListenerInterface
{
    /**
     * @var EntityManager
     */
    private $em;
    
    /**
     * @var boolean
     */
    private $isIDeskEvent = false;
    
    /**
     * The constructor.
     * 
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Get groups which are promotable
     *
     * @param DashboardEvent $event
     * @return Group[]
     */
    private function getGroups(DashboardEvent $event)
    {
        /* @var $er GroupRepository */
        $er = $this->em->getRepository('IServCoreBundle:Group');

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
     * 
     * @param DashboardEvent $event
     */
    public function onBuildManageDashboard(DashboardEvent $event)
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

        $icon = null;
        // display icon on IDesk
        if ($this->isIDeskEvent) {
            $icon = [
                'style' => 'fugue',
                'name' => 'clipboard-block'
            ];
        }


        $event->addContent(
            'manage.stsblcoursegroupmanagement',
            'StsblCourseGroupManagementBundle:Dashboard:pending.html.twig',
            [
                'title' => __n('You can promote one course group for next school year', 'You can promote %d course groups for next school year', count($groups), count($groups)),
                'text' => _('The following groups are in queue for promoting:'),
                'additional_text' => _('You can go to „Request promotion for course groups“ and request a promote for this groups.'),
                'groups' => $groups,
                'panel_class' => 'panel-primary',
                'idesk' => $this->isIDeskEvent,
                'icon' => $icon,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function onBuildIDesk(IDeskEvent $event) 
    {
        $this->isIDeskEvent = true;
        $this->onBuildManageDashboard($event);
    }

}
