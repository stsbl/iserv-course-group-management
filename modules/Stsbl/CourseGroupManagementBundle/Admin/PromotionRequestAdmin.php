<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle\Admin;

use IServ\AdminBundle\Admin\AdminServiceCrud;
use IServ\CoreBundle\Repository\GroupRepository;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\CoreBundle\Util\Collection\OrderedCollection;
use IServ\CrudBundle\Crud\Action\Link;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Exception\ObjectManagerException;
use IServ\CrudBundle\Mapper\AbstractBaseMapper;
use IServ\CrudBundle\Mapper\FormMapper;
use IServ\CrudBundle\Mapper\ListMapper;
use IServ\CrudBundle\Routing\RoutingDefinition;
use IServ\Library\Config\Config;
use Stsbl\CourseGroupManagementBundle\Crud\Batch;
use Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest;
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
final class PromotionRequestAdmin extends AdminServiceCrud
{
    use LoggerTrait;

    /**
     * {@inheritDoc}
     */
    protected static $entityClass = PromotionRequest::class;

    public function config(): Config
    {
        return $this->locator->get(Config::class);
    }

    public function mailer(): \Swift_Mailer
    {
        return $this->locator->get(\Swift_Mailer::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->logModule = 'Course Group Management';

        $this->title = _('Promotion Requests');
        $this->itemTitle = _('Promotion Request');
        $this->options['help'] = 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management';
    }

    /**
     * {@inheritDoc}
     */
    public static function defineRoutes(): RoutingDefinition
    {
        return parent::defineRoutes()
            ->setPathPrefix('/admin/coursegroupmanagement/')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureFormFields(FormMapper $formMapper): void
    {
        if ($formMapper->getObject() === null) {
            $formMapper
                ->add('group', null, [
                    'label' => _('Group'),
                    'attr' => [
                        'help_text' => __('Possible groups requires the group flag "%s"', _('Group is a Course Group'))
                    ],
                    'query_builder' => function (GroupRepository $er) {
                        $subQb = $er->createQueryBuilder('r');

                        $subQb
                            ->resetDQLParts()
                            ->select('r')
                            ->from('StsblCourseGroupManagementBundle:PromotionRequest', 'r')
                            ->where($subQb->expr()->eq('g.account', 'r.group'));

                        return $er->createFindByFlagQueryBuilder(Privilege::FLAG_COURSE_GROUP, 'g')
                            ->select('g')
                            ->andWhere($subQb->expr()->not($subQb->expr()->exists($subQb)))
                            ->orderBy('g.name', 'ASC');
                    },
                ])
                ->add('user', null, [
                    'label' => _('Filer'),
                    'required' => false,
                    'attr' => [
                        'help_text' => _('The filer will informed via e-mail if the request is accepted. If you not select a user here, the group owner will be used.')
                    ]
                ])
            ;
        }

        $formMapper
            ->add('comment', null, [
                'label' => _p('course-group-management', 'Comment'),
                'attr' => [
                    'help_text' => _('Additional explanation for this request'),
                    'required' => false
                ]
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureFields(AbstractBaseMapper $mapper): void
    {
        if ($mapper instanceof FormMapper) {
            // form mapper is configured via configureFormFields
            return;
        }

        if ($mapper instanceof ListMapper) {
            $mapper
                ->addIdentifier('group', null, [
                    'label' => _('Group'),
                    'icon' => true
                ])
            ;
        } else {
            $mapper
                ->add('group', null, [
                    'label' => _('Group'),
                    'icon' => true
                ])
            ;
        }

        $mapper
            ->add('user', null, [
                'label' => _('Filer'),
                'icon' => true,
            ])
            ->add('created', null, [
                'label' => _p('course-group-management', 'Created'),
                'responsive' => 'min-tablet'
            ])
            ->add('comment', null, [
                'label' => _p('course-group-management', 'Comment'),
                'responsive' => 'min-tablet'
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(CrudInterface $object): void
    {
        /** @var PromotionRequest $object */
        // default request owner to group owner
        if (null === $object->getUser()) {
            $object->setUser($object->getGroup()->getOwner());
        }

        if (null === $object->getUser()) {
            /** @noinspection PhpUnhandledExceptionInspection */
            throw new ObjectManagerException(_('Group does not have an owner. Please select manually.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postPersist(CrudInterface $object): void
    {
        /** @var PromotionRequest $object */
        $this->log(sprintf('Versetzungsantrag für Gruppe "%s" von %s hinzugefügt', $object->getGroup(), $object->getUser()));
    }

    /**
     * {@inheritdoc}
     */
    public function postRemove(CrudInterface $object): void
    {
        /** @var PromotionRequest $object */
        $this->log(sprintf('Versetzungsantrag für Gruppe "%s" von %s gelöscht', $object->getGroup(), $object->getUser()));
    }

    /**
     * {@inheritdoc}
     */
    public function postUpdate(CrudInterface $object, array $previousData = null): void
    {
        /** @var PromotionRequest $object */
        $this->log(sprintf('Kommentar von Versetzungsantrag für Gruppe "%s" von %s bearbeitet', $object->getGroup(), $object->getUser()));
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthorized(): bool
    {
        return $this->isGranted(Privilege::MANAGE_PROMOTIONS) && $this->isGranted(Privilege::REQUEST_PROMOTIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareBreadcrumbs(): array
    {
        return [
            _('Course Group Management') => $this->router()->generate('admin_coursegroupmanagement_request')
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexActions(): OrderedCollection
    {
        $links = parent::getIndexActions();
        $links->add(Link::create(
            $this->router()->generate('admin_coursegroupmanagement_execute_prepare'),
            _('Promote groups'),
            'play'
        ));
        $links->add(Link::create(
            $this->router()->generate('admin_coursegroupmamangement_remember'),
            _('Remember group owners with empty groups'),
            'pro-bell'
        ));

        return $links;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadBatchActions(): void
    {
        parent::loadBatchActions();

        $this->batchActions->remove(self::ACTION_DELETE);
        $this->batchActions->add(new Batch\RejectAction($this));
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        $deps = parent::getSubscribedServices();
        $deps[] = Config::class;
        $deps[] = \Swift_Mailer::class;

        return $deps;
    }
}
