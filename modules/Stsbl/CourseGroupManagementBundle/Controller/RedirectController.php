<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/coursegroupmanagement/promotionrequests", name="admin_promotionrequest_legacy_redirect")
 */
final class RedirectController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->redirectToRoute('admin_promotionrequest_index', [], Response::HTTP_PERMANENTLY_REDIRECT);
    }
}
