<?php
// src/Stsbl/CourseGroupManagementBundle/Admin/PromotionRequestAdmin.php
namespace Stsbl\CourseGroupManagementBundle\Admin;

use IServ\AdminBundle\Admin\AbstractAdmin;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Mapper\AbstractBaseMapper;
use IServ\CrudBundle\Mapper\FormMapper;
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
class PromotionRequestAdmin extends AbstractAdmin
{
    use LoggerTrait;

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
    public function configureFields(AbstractBaseMapper $mapper)
    {
        if ($mapper->getObject() === null || !$mapper instanceof FormMapper) {
            $mapper
                ->add('user', null, [
                    'label' => _('Filer')
                ])
                ->add('group', null, [
                    'label' => _('Group')
                ])
            ;
        }

        if (!$mapper instanceof FormMapper) {
            $mapper
                ->add('created', null, [
                    'label' => _p('course-group-management','Created')
                ])
            ;
        }

        $mapper
            ->add('comment', null, [
                'label' => _p('course-group-management', 'Comment')
            ])
        ;
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
        return $this->isGranted(Privilege::MANAGE_PROMOTIONS);
    }
}