<?php

namespace Stsbl\CourseGroupManagementBundle;

use IServ\CoreBundle\Routing\AutoloadRoutingBundleInterface;
use Stsbl\CourseGroupManagementBundle\DependencyInjection\StsblCourseGroupManagementExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class StsblCourseGroupManagementBundle extends Bundle implements AutoloadRoutingBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new StsblCourseGroupManagementExtension();
    }
}
