<?php
// src/Stsbl/CourseGroupManagementBundle/Admin/PromotionRequestAdmin.php
namespace Stsbl\CourseGroupManagementBundle\Admin;

use Doctrine\ORM\EntityRepository;
use IServ\AdminBundle\Admin\AbstractAdmin;
use IServ\CoreBundle\Entity\Group;
use IServ\CoreBundle\Entity\GroupRepository;
use IServ\CoreBundle\Entity\User;
use IServ\CoreBundle\Service\Config;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Mapper\AbstractBaseMapper;
use IServ\CrudBundle\Mapper\FormMapper;
use IServ\CrudBundle\Mapper\ListMapper;
use Stsbl\CourseGroupManagementBundle\Crud\Batch;
use Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;
use Swift_Mailer;

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
class PromotionRequestAdmin extends AbstractAdmin
{
    use LoggerTrait;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * Inject config
     *
     * @param Config $config
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get config
     *
     * @return Config|null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the Mailer.
     *
     * @param Swift_Mailer $mailer
     */
    public function setMailer(Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Gets the Mailer.
     *
     * @return Swift_Mailer
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->logModule = 'Course Group Management';

        $this->title = _('Promotion Requests');
        $this->itemTitle = _('Promotion Request');
        $this->routesPrefix = 'admin/coursegroupmanagement/';

        $this->options['help'] = 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management';
    }

    /**
     * {@inheritdoc}
     */
    public function configureFormFields(FormMapper $formMapper)
    {
        if ($formMapper->getObject() === null) {
            $formMapper
                ->add('group', null, [
                    'label' => _('Group'),
                    'attr' => [
                        'help_text' => __('Possible groups requires the group flag "%s"', _('Group is a Course Group'))
                    ],
                    'query_builder' => function (EntityRepository $er) {
                        /* @var $er GroupRepository */
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
    public function configureFields(AbstractBaseMapper $mapper)
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
                'label' => _p('course-group-management','Created')
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(CrudInterface $object)
    {
        // default request owner to group owner
        /* @var $object PromotionRequest */
        if ($object->getUser() === null) {
            $object->setUser($object->getGroup()->getOwner());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postPersist(CrudInterface $object)
    {
        /* @var $object \Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest */
        $this->log(sprintf('Versetzungsantrag für Gruppe "%s" von %s hinzugefügt', $object->getGroup(), $object->getUser()));
    }

    /**
     * {@inheritdoc}
     */
    public function postRemove(CrudInterface $object)
    {
        /* @var $object \Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest */
        $this->log(sprintf('Versetzungsantrag für Gruppe "%s" von %s gelöscht', $object->getGroup(), $object->getUser()));
    }

    /**
     * {@inheritdoc}
     */
    public function postUpdate(CrudInterface $object, array $previousData = null)
    {
        /* @var $object \Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest */
        $this->log(sprintf('Kommentar von Versetzungsantrag für Gruppe "%s" von %s bearbeitet', $object->getGroup(), $object->getUser()));
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthorized()
    {
        return $this->isGranted(Privilege::MANAGE_PROMOTIONS) && $this->isGranted(Privilege::REQUEST_PROMOTIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareBreadcrumbs()
    {
        return [
            _('Course Group Management') => $this->router->generate('admin_coursegroupmanagement_request')
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexActions()
    {
        $links = parent::getIndexActions();

        if (is_array($links)) {
            $links['run'] = array($this->getRouter()->generate('admin_coursegroupmanagement_execute_prepare'), _('Promote groups'), 'play');
        }

        return $links;
    }

    /**
     * {@inheritdoc}
     */
    public function loadBatchActions()
    {
        $res = parent::loadBatchActions();

        $res->remove('delete');
        $res->add(new Batch\RejectAction($this));

        return $res;
    }
}