<?php

declare(strict_types=1);

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
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */

// Privileges
_('Course Group Management');
_('Manage Promotions');

_('Course Group Management');
_('Request Promotions');

// Flag
_('Course Group Management');
_('Group is a Course Group');
_('Groups with this flag will promoted if a promotion request was put in. Elsewhere there will deleted a the end of the school year.');

// Validator
_('There is already a promotion request for this group.');
_('Please select the group which should promoted.');
_('Please select the filer of the promotion.');

// actcoursepromotion
_('Deleting group %s ...');
_('No groups will deleted.');
_('One group deleted');
_('%s groups deleted');
_('Deleting promotion request for group %s ...');
_('Group %s can not renamed to %s: Account is already used.');
_('Renaming group %s to %s ...');
_('Error during renaming group %s: %s');
_('No groups will renamed.');
_('One group promoted');
_('%s groups promoted');
_('Send e-mail to %s to inform on deleted groups ...');
_('Send e-mail to %s to inform on renamed groups ...');
_('Done.');

// actcoursepromotion - mails
_("Groups without promotion request deleted");
_("The following groups you own without promotion request were deleted:");
_('*This e-mail was generated automatically*');

_("Groups with promotion request renamed");
_("Your promotion requests were accepted and the following groups were renamed:");
_("%s was renamed to %s");
_('*This e-mail was generated automatically*');

// iservcfg
_('Display promotion notice starting this month');
_('The day and the month will combined to a date. Please enter a valid month (1-12) for a date.');
_('Module: Course Group Management');

_('Display promotion notice starting this day');
_('The day and the month will combined to a date. Please enter a valid day (1-31) for a date.');
_('Module: Course Group Management');

_('Display promotion notice until this month');
_('The day and the month will combined to a date. Please enter a valid month (1-12) for a date.');
_('Module: Course Group Management');

_('Display promotion notice until this day');
_('The day and the month will combined to a date. Please enter a valid day (1-31) for a date.');
_('Module: Course Group Management');
