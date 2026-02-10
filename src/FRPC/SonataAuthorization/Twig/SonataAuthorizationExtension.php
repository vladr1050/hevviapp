<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2022 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace FRPC\SonataAuthorization\Twig;

use DateTimeImmutable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SonataAuthorizationExtension extends AbstractExtension
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'time_ago',
                $this->timeAgo(...),
                ['needs_environment' => false]),
        ];
    }

    public function timeAgo(\DateTimeImmutable $ago, bool $full): string
    {
        $now = new DateTimeImmutable();
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => $this->translator->trans('year', [], 'SonataAuthorizationBundle'),
            'm' => $this->translator->trans('month', [], 'SonataAuthorizationBundle'),
            'w' => $this->translator->trans('week', [], 'SonataAuthorizationBundle'),
            'd' => $this->translator->trans('day', [], 'SonataAuthorizationBundle'),
            'h' => $this->translator->trans('hour', [], 'SonataAuthorizationBundle'),
            'i' => $this->translator->trans('minute', [], 'SonataAuthorizationBundle'),
            's' => $this->translator->trans('second', [], 'SonataAuthorizationBundle'),
        ];
        foreach ($string as $k => $v) {
            if ($diff->$k) {
                $string[$k] = $diff->$k.' '.$v.($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }

        return $string ? implode(', ', $string).' '.$this->translator->trans('ago', [],
                'SonataAuthorizationBundle') : $this->translator->trans('just_now', [], 'SonataAuthorizationBundle');
    }
}