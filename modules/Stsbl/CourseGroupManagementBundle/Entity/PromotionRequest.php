<?php

// src/Stsbl/CourseGroupManagementBundle/EntityPromotionRequest.php

namespace Stsbl\CourseGroupManagementBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use IServ\CoreBundle\Entity\Group;
use IServ\CoreBundle\Entity\User;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Entity\IgnorableConstraintsInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Symfony\Component\Validator\Constraints as Assert;

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
 * StsblCourseGroupManagementBundle:PromotionRequest
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @DoctrineAssert\UniqueEntity(fields="group", message="There is already a promotion request for this group.")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="cgr_management_promotion_requests")
 */
class PromotionRequest implements CrudInterface, IgnorableConstraintsInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int
     */
    private $id;

    /**
     * @Assert\NotBlank(message="Please select the group which should promoted.")
     * @ORM\ManyToOne(targetEntity="\IServ\CoreBundle\Entity\Group", fetch="EAGER")
     * @ORM\JoinColumn(name="actgrp", referencedColumnName="act", nullable=false)
     *
     * @var Group
     */
    private $group;

    /**
     * @Assert\NotBlank(message="Please select the filer of the promotion.", groups={"ignorable"})
     * @ORM\ManyToOne(targetEntity="\IServ\CoreBundle\Entity\User", fetch="EAGER")
     * @ORM\JoinColumn(name="actusr", referencedColumnName="act", nullable=false)
     *
     * @var User
     */
    private $user;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @var \DateTime
     */
    private $created;

    /**
     * @ORM\Column(type="text")
     *
     * @var string
     */
    private $comment;

    /**
     * Lifecycle callback to set the creation date
     *
     * @ORM\PrePersist
     */
    public function onCreate(): void
    {
        $this->setCreated(new \DateTime("now"));

        // default user to group owner
        if ($this->getUser() === null && $this->getGroup()->getOwner() !== null) {
            $this->setUser($this->getGroup()->getOwner());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return __('Promotion request for group %s', (string)$this->group);
    }

    /**
     * Gets a unique ID of the object which can be used to reference the entity in a URI.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return $this
     */
    public function setCreated(\DateTime $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    /**
     * @return $this
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return $this
     */
    public function setGroup(?Group $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * @return $this
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
