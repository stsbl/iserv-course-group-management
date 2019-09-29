<?php
// src/Stsbl/CourseGroupManagementBundle/Controller/CourseGroupManagementController.php
namespace Stsbl\CourseGroupManagementBundle\Controller;

use Doctrine\ORM\EntityRepository;
use IServ\CoreBundle\Controller\AbstractPageController;
use IServ\CoreBundle\Service\Logger;
use Knp\Menu\ItemInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest;
use Stsbl\CourseGroupManagementBundle\Security\Privilege;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/*
 * The MIT License
 *
 * Copyright 2019 Felix Jacobi.
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
 * Course group promotion request contoller
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class CourseGroupManagementController extends AbstractPageController
{
    /**
     * @var ItemInterface
     */
    private $managementMenu;

    public function __construct(ItemInterface $managementMenu)
    {
        $this->managementMenu = $managementMenu;
    }

    /**
     * Creates form for exam plan group unlocking
     *
     * @return \Symfony\Component\Form\Form
     */
    private function getUnlockForm()
    {
        /* @var $builder \Symfony\Component\Form\FormBuilder */
        $builder = $this->get('form.factory')->createNamedBuilder('course_group_promotion_request');
        
        $builder
            ->add('groups', EntityType::class, [
                'label' => _('Groups'),
                'class' => 'IServCoreBundle:Group',
                'select2-icon' => 'legacy-act-group',
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new NotBlank(['message' => _('Please choose the groups which you want to promote.')]),
                    new Count(['min' => 1, 'minMessage' => _('Please choose the groups which you want to promote.')])
                ],
                'by_reference' => false,
                'query_builder' => function (EntityRepository $er) {
                    /* @var $er \IServ\CoreBundle\Repository\GroupRepository */
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
                        ->setParameter('owner', $this->getUser())
                    ;
                },
                'attr' => [
                    'help_text' => _('Select the groups which you want to promote for the next school year.'),
                ],
            ])
            ->add('comment', TextType::class, [
                'label' => _p('course-group-management', 'Comment'),
                'required' => false
            ])
            ->add('submit', SubmitType::class, [
                'label' => _('Request promotion for groups'),
                'buttonClass' => 'btn-success',
                'icon' => 'ok'
            ])
        ;
        
        return $builder->getForm();
    }
    
    /**
     * Provides page with form for group promotion requests
     * 
     * @param Request $request
     * @return array
     * @Route("/manage/coursegroupmanagement", name="manage_coursegroupmanagement_request")
     * @\Symfony\Component\Routing\Annotation\Route("/admin/coursegroupmanagement", name="admin_coursegroupmanagement_request")
     * @Security("is_granted('PRIV_REQUEST_PROMOTIONS')")
     * @Template()
     */
    public function requestAction(Request $request, Logger $logger)
    {
        $form = $this->getUnlockForm();
        $form->handleRequest($request);
        $routeName = $request->get('_route');
        $messages = [];
        $errors = [];
        
        if ($form->isSubmitted() && $form->isValid()) {
            $groups = $form->getData()['groups'];
            $comment = $form->getData()['comment'];
            $validator = Validation::createValidator();

            /* @var $em \Doctrine\ORM\EntityManager */
            $em = $this->getDoctrine()->getManager();

            foreach ($groups as $group) {
                $promotionRequest = new PromotionRequest();

                $promotionRequest
                    ->setUser($this->getUser())
                    ->setGroup($group)
                    ->setComment($comment)
                ;

                $violations = $validator->validate($promotionRequest);

                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $errors[] = $violation->getMessage();
                    }
                } else {
                    $logger->writeForModule(sprintf('Versetzungsantrag für Gruppe "%s" gestellt', $group), 'Course Group Management');
                    $messages[] = __('Put in promotion request for group "%s".', $group);

                    $em->persist($promotionRequest);
                }
            }

            $em->flush();

            if (count($errors) > 0) {
                $this->addFlash('error', implode("\n", $errors));
            }
            if (count($messages) > 0) {
                $this->addFlash('success', implode("\n", $messages));
            }

        } else {
            foreach ($form->getErrors(true) as $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        
        // move page into admin section for administrators
        if ($routeName === 'admin_coursegroupmanagement_request') {
            $bundle = 'IServAdminBundle';
            $menu = null;
        } else {
            $bundle = 'IServCoreBundle';
            $menu = $this->managementMenu;
        }
        
        // track path
        if ($bundle === 'IServCoreBundle') {
            $this->addBreadcrumb(_('Administration'), $this->generateUrl('manage_index'));
            $this->addBreadcrumb(_('Request promotion for course groups'), $this->generateUrl($routeName));
        } else {
            $this->addBreadcrumb(_('Course Group Management'), $this->generateUrl($routeName));
        }
        
        $view = $form->createView();
        
        return [
            'bundle' => $bundle,
            'menu' => $menu,
            'form' => $view,
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-course-group-management'
        ];
    }
}
