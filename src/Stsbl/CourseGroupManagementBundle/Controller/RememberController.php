<?php
// src/Stsbl/CourseGroupManagementBundle/Controller/RememberController.php
namespace Stsbl\CourseGroupManagementBundle\Controller;

use Braincrafted\Bundle\BootstrapBundle\Form\Type\FormStaticControlType;
use IServ\CoreBundle\Entity\User;
use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Entity\Group;
use IServ\CoreBundle\Service\Config;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;

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
 * @Route("/admin/coursegroupmanagement/remember")
 * @Security("is_granted('PRIV_MANAGE_PROMOTIONS')")
 *
 */
class RememberController extends PageController
{
    /**
     * Add breadcrumbs for all actions
     *
     */
    private function addBreadcrumbs()
    {
        $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl('admin_coursegroupmanagement_request'));
        $this->addBreadcrumb(_('Promotion Requests'), $this->generateUrl('admin_promotionrequest_index'));
        $this->addBreadcrumb(_('Remember group owners with empty groups'), $this->generateUrl('admin_coursegroupmamangement_remember'));
    }

    /**
     * Find course groups without members
     *
     * @return Group[]
     */
    private function findEmptyGroups()
    {
        /* @var $groupRepo \IServ\CoreBundle\Entity\GroupRepository */
        $groupRepo = $this->getDoctrine()->getRepository(Group::class);

        /* @var $groups \IServ\CoreBundle\Entity\Group[] */
        $groups = $groupRepo->createFindByFlagQueryBuilder(Privilege::FLAG_COURSE_GROUP)
            ->getQuery()
            ->getResult()
        ;

        return $groups;
    }

    /**
     * List of users to remember
     *
     * @Route("", name="admin_coursegroupmamangement_remember")
     * @Template()
     *
     * @return array
     */
    public function indexAction()
    {
        $groups = $this->findEmptyGroups();

        /* @var $emptyGroups Group[] */
        $emptyGroups = [];
        /* @var $users User[] */
        $users = [];

        foreach ($groups as $group) {
            if ($group->getUsers()->count() === 0) {
                if (null !== $group->getOwner()) {
                    if (!isset($emptyGroups[$group->getOwner()->getUsername()])) {
                        $emptyGroups[$group->getOwner()->getUsername()] = [];
                    }

                    if (!isset($users[$group->getOwner()->getUsername()])) {
                        $users[$group->getOwner()->getUsername()] = $group->getOwner();
                    }

                    $emptyGroups[$group->getOwner()->getUsername()][$group->getAccount()] = $group;
                    ksort($emptyGroups[$group->getOwner()->getUsername()], SORT_NATURAL);
                }
            }
        }

        ksort($emptyGroups, SORT_NATURAL);
        ksort($users, SORT_NATURAL);

        $this->addBreadcrumbs();

        return [
            'emptyGroups' => $emptyGroups,
            'users' => $users,
            'controller' => $this,
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }

    /**
     * Mail form to send all group owners with empty course groups an e-mail
     *
     * @Route("/mail", name="admin_coursegroupmamangement_remember_mail")
     * @Template()
     *
     * @param Request $request
     * @return array
     */
    public function mailAction(Request $request)
    {
        $groups = $this->findEmptyGroups();

        /* @var $emptyGroups Group[] */
        $emptyGroups = [];
        /* @var $users User[] */
        $users = [];

        foreach ($groups as $group) {
            if ($group->getUsers()->count() === 0) {
                if (null !== $group->getOwner()) {
                    if (!isset($emptyGroups[$group->getOwner()->getUsername()])) {
                        $emptyGroups[$group->getOwner()->getUsername()] = [];
                    }

                    if (!isset($users[$group->getOwner()->getUsername()])) {
                        $users[$group->getOwner()->getUsername()] = $group->getOwner();
                    }

                    $emptyGroups[$group->getOwner()->getUsername()][$group->getAccount()] = $group;
                    ksort($emptyGroups[$group->getOwner()->getUsername()], SORT_NATURAL);
                }
            }
        }

        ksort($emptyGroups, SORT_NATURAL);
        ksort($users, SORT_NATURAL);

        $form = $this->createMailForm(array_map(function (User $user) {
            return $user->getName();
        }, $users));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /* @var $config Config */
            $config = $this->get('iserv.config');
            $flash = [];

            foreach ($users as $user) {
                $values = [];
                $values['firstame'] = $user->getFirstname();
                $values['lastname'] = $user->getLastname();
                $values['fullname'] = $user->getName();
                $values['groups'] = '* '.join("\n* ", $emptyGroups[$user->getUsername()]);

                $text = $data['text'];
                foreach ($values as $key => $value) {
                    $text = str_replace('%'.$key.'%', $value, $text);
                }

                $msg = new \Swift_Message();
                $msg->setTo([sprintf('%s@%s', $user->getUsername(), $config->get('Domain')) => $user->getName()]);
                $msg->setSender(sprintf('%s@%s', $this->getUser()->getUsername(), $config->get('Domain')), $this->getUser()->getName());
                $msg->setFrom(sprintf('%s@%s', $this->getUser()->getUsername(), $config->get('Domain')), $this->getUser()->getName());
                $msg->setSubject($data['subject']);
                $msg->setBody($text, 'text/plain', 'utf-8');
                $this->get('mailer.local')->send($msg);

                $flash[] = __('Sent e-mail to %s.', (string)$user);
            }

            if (count($flash) > 0) {
                $this->get('iserv.flash')->success(join("\n", $flash));
            }
        }

        $this->addBreadcrumbs();
        $this->addBreadcrumb(_('Send e-mails'), $this->generateUrl('admin_coursegroupmamangement_remember_mail'));

        return [
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }

    /**
     * Create form for mail to all
     *
     * @param array $users
     * @return Form
     */
    private function createMailForm(array $users)
    {
        asort($users);
        $users = join(', ', $users);

        $builder = $this->createFormBuilder();
        
        $builder
            ->add('recipients', FormStaticControlType::class, [
                'label' => _('Recipients'),
                'data' => $users
            ])
            ->add('subject', TextType::class, [
                'label' => _('Subject'),
                'data' => _('Course groups still empty'),
                'constraints' => [new NotBlank()],
                'attr' => [
                    'help_text' => 'The subject of the e-mail to send.'
                ]
            ])
            ->add('text', TextareaType::class, [
                'label' => _('Text'),
                'data' => $this->getBatchMailText(),
                'constraints' => [new NotBlank()],
                'attr' => [
                    'help_text' => 'The text of the e-mail to send. "%groups%" will replaced with the list of empty groups, "%firstname%" with the first name of the recipient, "%lastname%" with the last name of the recipient and "%fullname% with the full name.',
                    'rows' => 20
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => _('Send e-mails'),
                'buttonClass' => 'btn-success',
                'icon' => 'envelope'
            ])
        ;

        return $builder->getForm();
    }

    /**
     * Get remember mail text for single user
     *
     * @param User $user
     * @param array $groups
     * @return string
     */
    public function getSingleMailText(User $user, array $groups)
    {
        return __("Dear %s,\n\nThe following of your course groups are still empty:\n\n* %s\n\nPlease go to Administration > Groups and add the required members to this groups.", (string)$user, join("\n* ", $groups));
    }

    /**
     * Get remember mail text for batch sending
     *
     * @return string
     */
    public function getBatchMailText()
    {
        return __("Dear %s,\n\nThe following of your course groups are still empty:\n\n%s\n\nPlease go to Administration > Groups and add the required members to this groups.", '%fullname%', '%groups%');
    }
}