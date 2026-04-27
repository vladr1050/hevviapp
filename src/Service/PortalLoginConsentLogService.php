<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Carrier;
use App\Entity\PortalLoginConsentLog;
use App\Entity\TermsOfUseRevision;
use App\Entity\User;
use App\Enum\TermsAudience;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

final class PortalLoginConsentLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recordSuccessfulLogin(
        Request $request,
        UserInterface $subject,
        string $accountType,
        TermsAudience $portalAudience,
        ?TermsOfUseRevision $termsRevision,
    ): void {
        $log = new PortalLoginConsentLog();
        $log->setEmail($subject->getUserIdentifier());
        $log->setAccountType($accountType);
        $log->setPortalAudience($portalAudience);
        $log->setIpAddress($request->getClientIp() ?? '');
        $log->setUserAgent($request->headers->get('User-Agent'));

        if ($subject instanceof User || $subject instanceof Carrier) {
            $id = $subject->getId();
            if ($id !== null) {
                $log->setSubjectId($id);
            }
        }

        if ($termsRevision !== null) {
            $log->setTermsRevision($termsRevision);
            $log->setTermsVersion($termsRevision->getVersion());
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
