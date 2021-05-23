<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle;

use IServ\CoreBundle\Routing\AutoloadRoutingBundleInterface;
use Stsbl\CourseGroupManagementBundle\DependencyInjection\StsblCourseGroupManagementExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class StsblCourseGroupManagementBundle extends Bundle implements AutoloadRoutingBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new StsblCourseGroupManagementExtension();
    }
}
