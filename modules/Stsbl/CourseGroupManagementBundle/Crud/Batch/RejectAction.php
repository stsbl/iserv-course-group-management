<?php
// src/Stsbl/CourseGroupManagementBundle/Crud/Batch/RejectAction.php
namespace Stsbl\CourseGroupManagementBundle\Crud\Batch;

use Doctrine\Common\Collections\ArrayCollection;
use IServ\CrudBundle\Crud\Batch\DeleteAction;
use IServ\CrudBundle\Entity\FlashMessageBag;
use IServ\CrudBundle\Exception\ObjectManagerException;
use Stsbl\CourseGroupManagementBundle\Admin\PromotionRequestAdmin;
use Stsbl\CourseGroupManagementBundle\Entity\PromotionRequest;
use Swift_Message;

/*
 * The MIT License
 *
 * Copyright 2018 Felix Jacobi.
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
class RejectAction extends DeleteAction
{
    /**
     * @var PromotionRequestAdmin
     */
    protected $crud;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'reject';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return _('Reject');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ArrayCollection $entities)
    {
        $bag = new FlashMessageBag();
        $messageGroups = array();
        /* @var $request PromotionRequest */
        foreach ($entities as $request) {
            // Replicate delete actions code
            if ($this->crud->isAllowedToDelete($request)) {
                $success = false;
                try {
                    $success = $this->crud->delete($request);

                    if (!array_key_exists($request->getUser()->getUsername(), $messageGroups)) {
                        $messageGroups[$request->getUser()->getUsername()] = array();
                    }
                    $messageGroups[$request->getUser()->getUsername()][] = $request->getGroup()->getName();

                    $bag->addMessage('success', __('%s rejected!', (string) $request));
                } catch (ObjectManagerException $e) {
                    // nop
                }
                if (!$success) {
                    $bag->addMessage('error', __('%s could not be rejected!', (string) $request));
                }
            } else {
                $bag->addMessage('error', __('%s cannot be rejected by you!', (string) $request));
            }
        }

        // Send mails out to the user whose requests have been deleted
        $domain = $this->crud->getConfig()->get('domain');
        $srcAddr = $this->crud->getUser()->getUsername().'@'.$domain;

        foreach ($messageGroups as $usr => $groups) {
            $dstAddr = $usr.'@'.$domain;
            $subject = _('Requests for course promotion rejected');
            $message = _('Your requests for promotion of the following course groups have been rejected:')."\n\n  * ".implode("\n  * ", $groups);
            $message .= "\n\n--\n"._('*This e-mail was generated automatically*');
            $msg = new Swift_Message();
            $msg->setTo($dstAddr);
            $msg->setSender($srcAddr, $this->crud->getUser()->getName());
            $msg->setFrom($srcAddr, $this->crud->getUser()->getName());
            $msg->setSubject($subject);
            $msg->setBody($message, 'text/plain', 'utf-8');
            $this->crud->getMailer()->send($msg);
        }

        return $bag;
    }
}