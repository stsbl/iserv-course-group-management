<?php

declare(strict_types=1);

namespace Stsbl\CourseGroupManagementBundle\Service;

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

use IServ\CoreBundle\Security\Core\SecurityHandler;
use IServ\CoreBundle\Service\Shell;

/**
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
final class ActCoursePromotion
{
    public function __construct(
        private readonly SecurityHandler $securityHandler,
        private readonly Shell $shell,
    ) {
    }

    /**
     * Special shell access for streamed iservchk execution.
     */
    public function run(array $data): void
    {
        // Get user account and sesspw
        $token = $this->securityHandler->getToken();
        $act = $token->getUser()->getUsername();
        $sessPw = $this->securityHandler->getSessionPassword();

        // FIXME: Use real data
        $ip = @$_SERVER["REMOTE_ADDR"];

        // TODO: Check impact of this on Symfony.
        set_time_limit(7200);
        putenv("SESSPW=" . $sessPw);
        putenv("IP=" . $ip);
        putenv("ARG=" . json_encode($data));

        if (!$ph = popen("closefd sudo /usr/lib/iserv/actcoursepromotion " . "'" . $this->shell->quote($act) . "' 2>&1", "r")) {
            throw new \RuntimeException("Could not execute actcoursepromotion.");
        }

        while (is_string($line = fgets($ph))) {
            $this->streamCallBack($line);
        }

        pclose($ph);
    }

    /**
     * Callback for stream output
     */
    private function streamCallBack(string $line): void
    {
        $line = preg_replace("/.\x08/", '', $line);
        $line = preg_replace('/\e\[([01])(?:;3([0-7]))?m/', '', $line);
        $line = preg_replace("/\e/", '', $line);
        echo $line . '<br />';
        ob_flush();
        flush();
    }
}
