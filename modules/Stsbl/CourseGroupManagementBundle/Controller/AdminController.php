<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use IServ\BootstrapBundle\Form\Type\BootstrapCollectionType;
use IServ\BootstrapBundle\Form\Type\FormActionsType;
use IServ\CoreBundle\Controller\AbstractPageController;
use IServ\CoreBundle\Entity\Group;
use IServ\CoreBundle\Repository\GroupRepository;
use IServ\Library\Flash\FlashInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;
use Stsbl\CourseGroupManagementBundle\Service\ActCoursePromotion;
use Stsbl\CourseGroupManagementBundle\Util\Form;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

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
 * FIXME Move logic out of AdminController to a service!
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("/admin/coursegroupmanagement")
 * @Security("is_granted('PRIV_MANAGE_PROMOTIONS')")
 */
final class AdminController extends AbstractPageController
{
    /**
     * Convert ArrayCollection or array of group entities to array of group account.
     *
     * @return string[]
     */
    private function groupEntitiesToArray(ArrayCollection|array $groups): array
    {
        if ($groups instanceof ArrayCollection) {
            $groups = $groups->toArray();
        }

        if (!is_array($groups)) {
            throw new \InvalidArgumentException('$groups needs to be array.');
        }

        $res = [];
        foreach ($groups as $group) {
            /* @var $group Group */
            $res[] = $group->getAccount();
        }

        return $res;
    }

    /**
     * Convert array of group entities to ArrayCollection of group entities
     *
     * @param string[] $groups
     * @return ArrayCollection<Group>
     */
    private function arrayToGroupEntities(array $groups): ArrayCollection
    {
        $collection = new ArrayCollection();
        $er = $this->getDoctrine()->getRepository(Group::class);

        foreach ($groups as $group) {
            /* @var $group Group */
            $entity = $er->find($group);

            // TODO really ignore non existing group?
            if ($entity !== null) {
                $collection->add($entity);
            }
        }

        return $collection;
    }

    private function getPrepareForm(): FormInterface
    {
        $builder = $this->get('form.factory')->createNamedBuilder('stsbl_coursegroupmanagement_execute_prepare');

        $builder
            ->add('increase', CheckboxType::class, [
                'label' => _('Increment the first number in every group name for groups with promotion request'),
                'required' => false,
            ])
            ->add('delete', CheckboxType::class, [
                'label' => _('Delete groups without promotion request'),
                'required' => false,
            ])
            ->add('replace', CheckboxType::class, [
                'label' => _('Replace parts of group name for groups with promotion request'),
                'required' => false,
            ])
            ->add('search', BootstrapCollectionType::class, [
                'entry_type' => TextType::class,
                'label' => _('Search for'),
                'attr' => [
                    'help_text' => _('The "Search for" and "Replace with" fields must have the same amounts of values.')
                ]
            ])
            ->add('replacement', BootstrapCollectionType::class, [
                'entry_type' => TextType::class,
                'label' => _('Replace with'),
                'attr' => [
                    'help_text' => _('The "Search for" and "Replace with" fields must have the same amounts of values.')
                ]
            ])
            ->add('actions', FormActionsType::class)
        ;

        $actions = $builder->get('actions');


        $actions
            ->add('cancel', SubmitType::class, [
                'label' => _('Cancel'),
                'buttonClass' => 'btn-default',
                'icon' => 'remove'
            ])
            ->add('next', SubmitType::class, [
                'label' => _('Next'),
                'buttonClass' => 'btn-success',
                'icon' => 'arrow-right'
            ])
        ;

        return $builder->getForm();
    }

    /**
     * Get check form
     *
     * @param string[] $groupActs
     */
    private function getCheckForm(array $groupActs): FormInterface
    {
        $builder = $this->get('form.factory')->createNamedBuilder('stsbl_coursegroupmanagement_execute_check');

        $builder
            ->add('groups', ChoiceType::class, [
                'multiple' => true,
                'choices' => $groupActs,
            ])
            ->add('deletedGroups', ChoiceType::class, [
                'multiple' => true,
                'choices' => $groupActs
            ])
            ->add('actions', FormActionsType::class)
        ;

        $actions = $builder->get('actions');

        $actions
            ->add('cancel', SubmitType::class, [
                'label' => _('Cancel'),
                'buttonClass' => 'btn-default',
                'icon' => 'remove'
            ])
            ->add('back', SubmitType::class, [
                'label' => _('Back'),
                'buttonClass' => 'btn-warning',
                'icon' => 'arrow-left'
            ])
            ->add('next', SubmitType::class, [
                'label' => _('Next'),
                'buttonClass' => 'btn-success',
                'icon' => 'arrow-right'
            ])
        ;

        return $builder->getForm();
    }

    /**
     * Get preview form
     */
    private function getPreviewForm(): FormInterface
    {
        $builder = $this->get('form.factory')->createNamedBuilder('stsbl_coursegroupmanagement_execute_preview');

        $builder
            ->add('actions', FormActionsType::class)
        ;

        $actions = $builder->get('actions');


        $actions
            ->add('cancel', SubmitType::class, [
                'label' => _('Cancel'),
                'buttonClass' => 'btn-default',
                'icon' => 'remove'
            ])
            ->add('back', SubmitType::class, [
                'label' => _('Back'),
                'buttonClass' => 'btn-warning',
                'icon' => 'arrow-left'
            ])
            ->add('next', SubmitType::class, [
                'label' => _('Next'),
                'buttonClass' => 'btn-success',
                'icon' => 'arrow-right'
            ])
        ;

        return $builder->getForm();
    }

    /**
     * Get customize form
     */
    private function getCustomizeForm(array $transition): FormInterface
    {
        $builder = $this->get('form.factory')->createNamedBuilder('stsbl_coursegroupmanagement_execute_customize');

        foreach ($transition as $key => $group) {
            $builder
                ->add('group_' . Form::base64ToSymfonyFormCompatibleName(base64_encode($key)), TextType::class, [
                    'label' => $group['oldName'],
                    'data' => $group['newName']
                ])
            ;
        }

        $builder
            ->add('actions', FormActionsType::class)
        ;

        $actions = $builder->get('actions');


        $actions
            ->add('cancel', SubmitType::class, [
                'label' => _('Cancel'),
                'buttonClass' => 'btn-default',
                'icon' => 'remove'
            ])
            ->add('back', SubmitType::class, [
                'label' => _('Back'),
                'buttonClass' => 'btn-warning',
                'icon' => 'arrow-left'
            ])
            ->add('next', SubmitType::class, [
                'label' => _('Next'),
                'buttonClass' => 'btn-success',
                'icon' => 'arrow-right'
            ])
        ;

        return $builder->getForm();
    }

    /**
     * Prepare action
     *
     * @Route("/execute/prepare", name="admin_coursegroupmanagement_execute_prepare")
     * @Template()
     */
    public function prepareAction(Request $request, SessionInterface $session): RedirectResponse|array
    {
        // inject session data
        $formData = [];

        if ($this->get('session')->has('course_group_management_replace')) {
            $formData['replace'] = (bool)$this->get('session')->get('course_group_management_replace');
        } else {
            $formData['replace'] = false;
        }

        if ($this->get('session')->has('course_group_management_increase')) {
            $formData['increase'] = (bool)$this->get('session')->get('course_group_management_increase');
        } else {
            $formData['increase'] = false;
        }

        if ($this->get('session')->has('course_group_management_replace_search')) {
            $formData['search'] = $this->get('session')->get('course_group_management_replace_search');
        } else {
            $formData['search'] = [];
        }

        if ($this->get('session')->has('course_group_management_replace_replacement')) {
            $formData['replacement'] = $this->get('session')->get('course_group_management_replace_replacement');
        } else {
            $formData['replacement'] = [];
        }

        if ($this->get('session')->has('course_group_management_delete')) {
            $formData['delete'] = (bool)$this->get('session')->get('course_group_management_delete');
        } else {
            $formData['delete'] = false;
        }

        $form = $this->getPrepareForm();
        $form->handleRequest($request);
        $redirect = false;
        $errors = [];

        if (!$form->isSubmitted()) {
            $form->setData($formData);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $redirect = true;
            $amountError = false;
            $data = $form->getData();

            // redirect on cancel
            if ($form->get('actions')->get('cancel')->isClicked()) {
                return $this->redirectToRoute('admin_promotionrequest_index');
            }

            if ($data['replace'] === true && count($data['search']) !== count($data['replacement'])) {
                $errors[] = _('The "Search for" and "Replace with" fields must have the same amounts of values.');
                $redirect = false;
                $amountError = true;
            }

            $session->set('course_group_management_replace', $data['replace']);
            $session->set('course_group_management_increase', $data['increase']);
            $session->set('course_group_management_delete', $data['delete']);

            if ($data['replace'] === true && !$amountError) {
                $session->set('course_group_management_replace_search', $data['search']);
                $session->set('course_group_management_replace_replacement', $data['replacement']);
            }
        } else {
            foreach ($form->getErrors(true) as $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $this->flash()->error(implode("\n", $errors));
        }

        if ($redirect) {
            return $this->redirectToRoute('admin_coursegroupmanagement_execute_check');
        }

        $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl('admin_coursegroupmanagement_request'));
        $this->addBreadcrumb(_('Promotion Requests'), $this->generateUrl('admin_promotionrequest_index'));
        $this->addBreadcrumb(_('Promote course groups'), $this->generateUrl('admin_coursegroupmanagement_execute_prepare'));
        $this->addBreadcrumb(_('Preparation'), $this->generateUrl('admin_coursegroupmanagement_execute_prepare'));

        return [
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }

    /**
     * Preview action
     *
     * @Route("/execute/preview",name="admin_coursegroupmanagement_execute_preview")
     * @Template()
     */
    public function previewAction(Request $request, SessionInterface $session): RedirectResponse|array
    {
        $form = $this->getPreviewForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // redirect on cancel
            if ($form->get('actions')->get('cancel')->isClicked()) {
                return $this->redirectToRoute('admin_promotionrequest_index');
            }

            // redirect on back
            if ($form->get('actions')->get('back')->isClicked()) {
                return $this->redirectToRoute('admin_coursegroupmanagement_execute_customize');
            }

            return $this->redirectToRoute('admin_coursegroupmanagement_execute_promote');
        }

        $deletedGroups = [];
        $groupsTransition = [];

        if ($session->has('course_group_management_delete')) {
            $deletedGroups = $this->arrayToGroupEntities($session->get('course_group_management_delete'));
        }

        if ($session->has('course_group_management_transition')) {
            $groupsTransition = $session->get('course_group_management_transition');
        }

        // track path
        $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl('admin_coursegroupmanagement_request'));
        $this->addBreadcrumb(_('Promotion Requests'), $this->generateUrl('admin_promotionrequest_index'));
        $this->addBreadcrumb(_('Promote course groups'), $this->generateUrl('admin_coursegroupmanagement_execute_prepare'));
        $this->addBreadcrumb(_('Preview'), $this->generateUrl('admin_coursegroupmanagement_execute_preview'));

        return [
            'transition' => $groupsTransition,
            'deleted' => $deletedGroups,
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }

    /**
     * Check action
     *
     * @Route("/execute/check",name="admin_coursegroupmanagement_execute_check")
     * @Template()
     */
    public function checkAction(Request $request, SessionInterface $session): RedirectResponse|array
    {
        /* @var $er GroupRepository */
        $er = $this->getDoctrine()->getRepository(Group::class);
        $groupActs = [];
        foreach ($er->findAll() as $g) {
            $groupActs[] = $g->getAccount();
        }

        $subQb = $er->createQueryBuilder('r');

        $subQb
            ->resetDQLParts()
            ->select('r')
            ->from(\Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest::class, 'r')
            ->where($subQb->expr()->eq('g.account', 'r.group'))
        ;

        $groups = $er->createFindByFlagQueryBuilder(Privilege::FLAG_COURSE_GROUP, 'g')
            ->select('g')
            ->andWhere($subQb->expr()->exists($subQb))
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        if ($session->has('course_group_management_delete') && $session->get('course_group_management_delete')) {
            $deletedGroups = $er->createFindByFlagQueryBuilder(Privilege::FLAG_COURSE_GROUP, 'g')
                ->select('g')
                ->andWhere($subQb->expr()->not($subQb->expr()->exists($subQb)))
                ->orderBy('g.name', 'ASC')
                ->getQuery()
                ->getResult()
            ;
        } else {
            $deletedGroups = [];
        }

        $groupsTransition = [];

        foreach ($groups as $group) {
            $owner = $group->getOwner();
            $ownerAccount = $owner !== null ? $group->getOwner()->getUsername() : null;
            $groupsTransition[$group->getAccount()] = ['oldName' => $group->getName(), 'newName' => $group->getName(), 'owner' => $ownerAccount];
        }

        if (
            $session->has('course_group_management_increase') &&
            $session->get('course_group_management_increase') === true
        ) {
            foreach ($groupsTransition as $key => $group) {
                // magic
                if (preg_match('|\d+|', $group['newName'], $m)) {
                    $groupsTransition[$key]['newName'] = preg_replace(sprintf('|%s|', $m[0]), (string)($m[0] + 1), $group['newName'], 1);
                }
            }
        }

        if (
            $session->has('course_group_management_replace') &&
            $session->get('course_group_management_replace') === true &&
            $session->has('course_group_management_replace_search') &&
            $session->has('course_group_management_replace_replacement')
        ) {
            foreach ($groupsTransition as $key => $group) {
                $groupsTransition[$key]['newName'] = str_replace($session->get('course_group_management_replace_search'), $session->get('course_group_management_replace_replacement'), $group['newName']);
            }
        }

        $form = $this->getCheckForm($groupActs);
        $form->handleRequest($request);
        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            // redirect on cancel
            if ($form->get('actions')->get('cancel')->isClicked()) {
                return $this->redirectToRoute('admin_promotionrequest_index');
            }

            // redirect on back
            if ($form->get('actions')->get('back')->isClicked()) {
                return $this->redirectToRoute('admin_coursegroupmanagement_execute_prepare');
            }

            $data = $form->getData();

            foreach ($groupsTransition as $key => $group) {
                if (!in_array($key, $data['groups'], true)) {
                    unset($groupsTransition[$key]);
                }
            }

            foreach ($deletedGroups as $key => $group) {
                if (!in_array($group->getAccount(), $data['deletedGroups'], true)) {
                    unset($deletedGroups[$key]);
                }
            }

            $session->set('course_group_management_delete', $this->groupEntitiesToArray($deletedGroups));
            $session->set('course_group_management_transition', $groupsTransition);

            return $this->redirectToRoute('admin_coursegroupmanagement_execute_customize');
        }

        foreach ($form->getErrors(true) as $e) {
            $errors[] = $e->getMessage();
        }

        if (count($errors) > 0) {
            $this->flash()->error(implode("\n", $errors));
        }

        $unchangedGroups = [];
        // split changed and unchanged groups
        foreach ($groupsTransition as $key => $groupTransition) {
            if ($groupTransition['oldName'] === $groupTransition['newName']) {
                $unchangedGroups[] = $groupTransition;
                unset($groupsTransition[$key]);
            }
        }

        // add groups with course group flag and w/o request if deleting is disabled
        if ($session->has('course_group_management_delete') && $session->get('course_group_management_delete') === false) {
            $groupsWithFlag = $er->createFindByFlagQueryBuilder(Privilege::FLAG_COURSE_GROUP, 'g')
                ->select('g')
                ->andWhere($subQb->expr()->not($subQb->expr()->exists($subQb)))
                ->orderBy('g.name', 'ASC')
                ->getQuery()
                ->getResult()
            ;

            foreach ($groupsWithFlag as $group) {
                /* @var $group Group */
                $owner = $group->getOwner();
                $ownerAccount = $owner != null ? $group->getOwner()->getUsername() : null;
                $unchangedGroups[] = ['oldName' => $group->getName(), 'newName' => $group->getName(), 'owner' => $ownerAccount];
            }
        }

        // track path
        $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl('admin_coursegroupmanagement_request'));
        $this->addBreadcrumb(_('Promotion Requests'), $this->generateUrl('admin_promotionrequest_index'));
        $this->addBreadcrumb(_('Promote course groups'), $this->generateUrl('admin_coursegroupmanagement_execute_prepare'));
        $this->addBreadcrumb(_('Check'), $this->generateUrl('admin_coursegroupmanagement_execute_check'));

        return [
            'transition' => $groupsTransition,
            'unchanged' => $unchangedGroups,
            'deleted' => $deletedGroups,
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management',
            'userRepository' => $this->getDoctrine()->getRepository(\IServ\CoreBundle\Entity\User::class),
        ];
    }

    /**
     * Customize action
     *
     * @Route("/execute/customize", name="admin_coursegroupmanagement_execute_customize")
     * @Template()
     */
    public function customizeAction(Request $request, SessionInterface $session): RedirectResponse|array
    {
        $groupsTransition = [];

        if ($session->has('course_group_management_transition')) {
            $groupsTransition = $session->get('course_group_management_transition', []);
        }

        $form = $this->getCustomizeForm($groupsTransition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // redirect on cancel
            if ($form->get('actions')->get('cancel')->isClicked()) {
                return $this->redirectToRoute('admin_promotionrequest_index');
            }

            // redirect on back
            if ($form->get('actions')->get('back')->isClicked()) {
                return $this->redirectToRoute('admin_coursegroupmanagement_execute_check');
            }

            $data = $form->getData();

            foreach ($groupsTransition as $key => $group) {
                $name = Form::base64ToSymfonyFormCompatibleName(base64_encode($key));
                if (isset($data['group_' . $name])) {
                    $groupsTransition[$key]['newName'] = $data['group_' . $name];
                }
            }

            $session->set('course_group_management_transition', $groupsTransition);

            return $this->redirectToRoute('admin_coursegroupmanagement_execute_preview');
        }

        // track path
        $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl('admin_coursegroupmanagement_request'));
        $this->addBreadcrumb(_('Promotion Requests'), $this->generateUrl('admin_promotionrequest_index'));
        $this->addBreadcrumb(_('Promote course groups'), $this->generateUrl('admin_coursegroupmanagement_execute_prepare'));
        $this->addBreadcrumb(_('Customize'), $this->generateUrl('admin_coursegroupmanagement_execute_customize'));

        return [
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }

    /**
     * Promote run action
     *
     * @Route("/execute/promote/run", name="admin_coursegroupmanagement_execute_promote_run")
     */
    public function promoteRunAction(ActCoursePromotion $coursePromotion, SessionInterface $session): StreamedResponse
    {
        if (!$session->has('course_group_management_delete')) {
            throw new \RuntimeException('key course_group_management_delete is missing!');
        }

        if (!$session->has('course_group_management_transition')) {
            throw new \RuntimeException('key course_group_management_transition is missing!');
        }
        $pre = $this->render('@StsblCourseGroupManagement/Admin/actcoursepromotion/pre.html.twig')->getContent();
        $post = $this->render('@StsblCourseGroupManagement/Admin/actcoursepromotion/post.html.twig')->getContent();
        $data = [];

        $data['rename'] = (object)$session->get('course_group_management_transition', []);

        $groups = $session->get('course_group_management_delete', []);

        $data['delete'] = $groups;

        return new StreamedResponse(function () use ($coursePromotion, $pre, $post, $data) {
            echo $pre;
            ob_flush();
            flush();
            $coursePromotion->run($data);
            echo $post;
            ob_flush();
            flush();
        });
    }

    /**
     * Promote action
     *
     * @Route("/execute/promote", name="admin_coursegroupmanagement_execute_promote")
     * @Template()
     */
    public function promoteAction(): array
    {
        // track path
        $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl('admin_coursegroupmanagement_request'));
        $this->addBreadcrumb(_('Promotion Requests'), $this->generateUrl('admin_promotionrequest_index'));
        $this->addBreadcrumb(_('Promote course groups'), $this->generateUrl('admin_coursegroupmanagement_execute_prepare'));
        $this->addBreadcrumb(_p('course-group-management', 'Promote'), $this->generateUrl('admin_coursegroupmanagement_execute_promote'));

        return [
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        $deps = parent::getSubscribedServices();
        $deps[] = FlashInterface::class;

        return $deps;
    }

    private function flash(): FlashInterface
    {
        return $this->get(FlashInterface::class);
    }
}
