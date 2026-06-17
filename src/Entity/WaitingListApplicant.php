<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WaitingListApplicantType;
use App\Repository\WaitingListApplicantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WaitingListApplicantRepository::class)]
#[ORM\Table(name: 'waiting_list_applicant')]
#[ORM\UniqueConstraint(name: 'uniq_waiting_list_applicant_email', columns: ['email'])]
class WaitingListApplicant extends BaseUUID
{
    #[ORM\Column(length: 255)]
    private string $email = '';

    #[ORM\Column(length: 32)]
    private string $phone = '';

    #[ORM\Column(length: 16, enumType: WaitingListApplicantType::class)]
    private WaitingListApplicantType $type = WaitingListApplicantType::Sender;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getType(): WaitingListApplicantType
    {
        return $this->type;
    }

    public function setType(WaitingListApplicantType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function __toString(): string
    {
        if ($this->email !== '') {
            return $this->email;
        }

        return 'Waiting list applicant';
    }
}
