<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enum\TermsAudience;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class PortalLoginConsentLogAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('create')
            ->remove('edit')
            ->remove('delete')
            ->remove('export');
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $accountChoices = [
            'list.label_portal_login_account_user' => 'user',
            'list.label_portal_login_account_carrier' => 'carrier',
        ];
        $portalAudienceChoices = [];
        foreach (TermsAudience::cases() as $case) {
            $portalAudienceChoices['list.label_portal_login_audience_'.$case->value] = $case->value;
        }

        $datagrid
            ->add('createdAt', DateTimeRangeFilter::class, [
                'label' => 'filter.label_portal_login_created_at',
            ])
            ->add('email', null, [
                'label' => 'filter.label_portal_login_email',
            ])
            ->add('accountType', ChoiceFilter::class, [
                'label' => 'filter.label_portal_login_account_type',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => $accountChoices,
                    'translation_domain' => 'AppBundle',
                ],
            ])
            ->add('portalAudience', ChoiceFilter::class, [
                'label' => 'filter.label_portal_login_portal_audience',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => $portalAudienceChoices,
                    'translation_domain' => 'AppBundle',
                ],
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('createdAt', 'datetime', [
                'label' => 'list.label_portal_login_created_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ])
            ->add('email', null, [
                'label' => 'list.label_portal_login_email',
            ])
            ->add('accountType', null, [
                'label' => 'list.label_portal_login_account_type',
            ])
            ->add('portalAudience', null, [
                'label' => 'list.label_portal_login_portal_audience',
            ])
            ->add('termsVersion', null, [
                'label' => 'list.label_portal_login_terms_version',
            ])
            ->add('termsRevision', null, [
                'label' => 'list.label_portal_login_terms_revision',
            ])
            ->add('subjectId', null, [
                'label' => 'list.label_portal_login_subject_id',
            ])
            ->add('ipAddress', null, [
                'label' => 'list.label_portal_login_ip',
            ])
            ->add('userAgent', null, [
                'label' => 'list.label_portal_login_user_agent',
            ]);

        if ($this->hasSameRole()) {
            $list->add('_action', 'actions', [
                'actions' => [
                    'show' => [],
                ],
            ]);
        }
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('createdAt', null, [
                'label' => 'list.label_portal_login_created_at',
            ])
            ->add('email', null, [
                'label' => 'list.label_portal_login_email',
            ])
            ->add('accountType', null, [
                'label' => 'list.label_portal_login_account_type',
            ])
            ->add('portalAudience', null, [
                'label' => 'list.label_portal_login_portal_audience',
            ])
            ->add('subjectId', null, [
                'label' => 'list.label_portal_login_subject_id',
            ])
            ->add('termsVersion', null, [
                'label' => 'list.label_portal_login_terms_version',
            ])
            ->add('termsRevision', null, [
                'label' => 'list.label_portal_login_terms_revision',
            ])
            ->add('ipAddress', null, [
                'label' => 'list.label_portal_login_ip',
            ])
            ->add('userAgent', null, [
                'label' => 'list.label_portal_login_user_agent',
            ])
            ->add('updatedAt');
    }
}
