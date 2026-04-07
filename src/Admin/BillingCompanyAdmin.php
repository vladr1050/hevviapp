<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Admin;

use App\Entity\BillingCompany;
use App\Repository\BillingCompanyRepository;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class BillingCompanyAdmin extends BaseAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('name')
            ->add('issuesInvoices');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
            ->add('registrationNumber')
            ->add('vatRate', null, [
                'label' => 'list.label_vat_rate_percent',
            ])
            ->add('platformFeePercent', null, [
                'label' => 'list.label_platform_fee_percent',
            ])
            ->add('issuesInvoices', null, [
                'label' => 'list.label_issues_invoices',
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('name')
            ->add('registrationNumber')
            ->add('vatNumber')
            ->add('vatRate')
            ->add('platformFeePercent', null, [
                'label' => 'show.label_platform_fee_percent',
            ])
            ->add('addressStreet')
            ->add('addressNumber')
            ->add('addressCity')
            ->add('addressCountry')
            ->add('addressPostalCode')
            ->add('iban')
            ->add('phone')
            ->add('email')
            ->add('representative')
            ->add('paymentDueDays')
            ->add('issuesInvoices')
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('tabs_general')
            ->with('billing_company_identity', ['class' => 'col-md-12'])
            ->add('name', TextType::class, ['required' => true])
            ->add('registrationNumber', TextType::class, ['required' => true])
            ->add('vatNumber', TextType::class, ['required' => false])
            ->add('vatRate', NumberType::class, [
                'required' => true,
                'scale' => 4,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => 0],
                'help' => 'form.help_billing_company_vat_rate',
            ])
            ->add('platformFeePercent', NumberType::class, [
                'required' => false,
                'scale' => 4,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => 0],
                'help' => 'form.help_billing_company_platform_fee_percent',
            ])
            ->end()
            ->end()
            ->tab('tabs_address')
            ->with('billing_company_address', ['class' => 'col-md-12'])
            ->add('addressStreet', TextType::class, ['required' => true])
            ->add('addressNumber', TextType::class, ['required' => true])
            ->add('addressCity', TextType::class, ['required' => true])
            ->add('addressCountry', TextType::class, ['required' => true])
            ->add('addressPostalCode', TextType::class, ['required' => true])
            ->end()
            ->end()
            ->tab('tabs_contacts')
            ->with('billing_company_contacts', ['class' => 'col-md-12'])
            ->add('iban', TextType::class, ['required' => true])
            ->add('phone', TextType::class, ['required' => true])
            ->add('email', EmailType::class, ['required' => true])
            ->add('representative', TextType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('tabs_invoicing')
            ->with('billing_company_invoicing', ['class' => 'col-md-12'])
            ->add('paymentDueDays', IntegerType::class, [
                'required' => false,
                'help' => 'form.help_billing_company_payment_due_days',
            ])
            ->add('issuesInvoices', CheckboxType::class, [
                'required' => false,
                'help' => 'form.help_billing_company_issues_invoices',
            ])
            ->end()
            ->end();
    }

    protected function prePersist(object $object): void
    {
        if ($object instanceof BillingCompany) {
            $this->ensureSingleIssuer($object);
        }
    }

    protected function preUpdate(object $object): void
    {
        if ($object instanceof BillingCompany) {
            $this->ensureSingleIssuer($object);
        }
    }

    private function ensureSingleIssuer(BillingCompany $company): void
    {
        if (!$company->isIssuesInvoices()) {
            return;
        }
        $em = $this->getModelManager()->getEntityManager(BillingCompany::class);
        $repo = $em->getRepository(BillingCompany::class);
        if (!$repo instanceof BillingCompanyRepository) {
            return;
        }
        $repo->demoteOtherIssuers($company->getId());
    }
}
